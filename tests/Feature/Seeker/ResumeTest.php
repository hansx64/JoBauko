<?php

namespace Tests\Feature\Seeker;

use App\Enums\ResumeExtractionStatus;
use App\Models\Resume;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\SkillSeeder;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    private const RESUME_LINES = [
        'MARIA SANTOS',
        'EDUCATION',
        'Bachelor of Science in Agriculture',
        'Benguet State University',
        '2016 - 2020',
        'SKILLS',
        'Organic Farming, Highland Vegetable Farming, Teamwork',
        'CERTIFICATIONS',
        'Organic Agriculture Production NC II',
    ];

    private LocalFilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
        $this->seed(SkillSeeder::class);
    }

    private function pdfUpload(): UploadedFile
    {
        $html = '<p>'.implode('</p><p>', self::RESUME_LINES).'</p>';
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, Pdf::loadHTML($html)->output());

        return new UploadedFile($path, 'maria-santos.pdf', null, null, true);
    }

    private function docxUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $paragraphs = implode('', array_map(fn ($l) => '<w:p><w:r><w:t>'.htmlspecialchars($l).'</w:t></w:r></w:p>', self::RESUME_LINES));
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'maria-santos.docx', null, null, true);
    }

    public function test_a_pdf_resume_is_stored_privately_and_read()
    {
        $user = User::factory()->seeker()->create();

        $response = $this->actingAs($user)->post('/resume', ['resume' => $this->pdfUpload()]);

        $resume = Resume::sole();
        $response->assertRedirect("/resume/{$resume->id}/review");

        $this->disk->assertExists($resume->disk_path);
        $this->assertStringStartsWith("resumes/{$user->id}/", $resume->disk_path);
        $this->assertSame('application/pdf', $resume->mime_type);
        $this->assertSame(ResumeExtractionStatus::Extracted, $resume->extraction_status);
        $this->assertContains('Organic Farming', $resume->extracted_data['skills']);
    }

    public function test_a_docx_resume_is_accepted_and_read()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->post('/resume', ['resume' => $this->docxUpload()])
            ->assertSessionHasNoErrors();

        $resume = Resume::sole();
        $this->assertSame(ResumeExtractionStatus::Extracted, $resume->extraction_status);
        $this->assertSame('Benguet State University', $resume->extracted_data['education'][0]['school']);
    }

    public function test_other_file_types_are_rejected()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->post('/resume', ['resume' => UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg')])
            ->assertSessionHasErrors('resume');

        $this->assertSame(0, Resume::count());
    }

    public function test_a_renamed_non_pdf_file_is_rejected()
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, 'just plain text pretending to be a PDF');

        $this->actingAs(User::factory()->seeker()->create())
            ->post('/resume', ['resume' => new UploadedFile($path, 'resume.pdf', null, null, true)])
            ->assertSessionHasErrors('resume');
    }

    public function test_files_over_5_mb_are_rejected()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->post('/resume', ['resume' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors('resume');
    }

    public function test_an_unreadable_resume_is_kept_but_marked_failed()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->post('/resume', ['resume' => UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf')])
            ->assertRedirect('/profile');

        $resume = Resume::sole();
        $this->assertSame(ResumeExtractionStatus::Failed, $resume->extraction_status);
        $this->assertNotEmpty($resume->extracted_data['error']);
    }

    public function test_uploading_a_new_resume_replaces_the_old_one()
    {
        $user = User::factory()->seeker()->create();
        $this->actingAs($user);

        $this->post('/resume', ['resume' => $this->pdfUpload()]);
        $first = Resume::sole();

        $this->post('/resume', ['resume' => $this->docxUpload()]);

        $this->assertModelMissing($first);
        $this->disk->assertMissing($first->disk_path);
        $this->assertSame('maria-santos.docx', $user->currentResume()->first()->original_name);
    }

    public function test_the_owner_can_download_and_preview_the_resume()
    {
        $user = User::factory()->seeker()->create();
        $this->actingAs($user)->post('/resume', ['resume' => $this->pdfUpload()]);
        $resume = Resume::sole();

        $this->get("/resume/{$resume->id}/file")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('maria-santos.pdf');

        $preview = $this->get("/resume/{$resume->id}/file?preview=1")->assertOk();
        $this->assertStringStartsWith('inline', $preview->headers->get('Content-Disposition'));
    }

    public function test_other_users_cannot_access_a_resume()
    {
        $owner = User::factory()->seeker()->create();
        $this->actingAs($owner)->post('/resume', ['resume' => $this->pdfUpload()]);
        $resume = Resume::sole();

        $this->actingAs(User::factory()->seeker()->create());

        $this->get("/resume/{$resume->id}/file")->assertForbidden();
        $this->get("/resume/{$resume->id}/review")->assertForbidden();
        $this->delete("/resume/{$resume->id}")->assertForbidden();

        Auth::guard('web')->logout();
        $this->get("/resume/{$resume->id}/file")->assertRedirect('/login');
    }

    public function test_extracted_data_is_only_added_to_the_profile_after_review()
    {
        $user = User::factory()->seeker()->create();
        $this->actingAs($user)->post('/resume', ['resume' => $this->pdfUpload()]);
        $resume = Resume::sole();
        $profile = $user->applicantProfile;

        $this->assertSame(0, $profile->skills()->count());
        $this->assertSame(0, $profile->education()->count());

        $this->get("/resume/{$resume->id}/review")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('seeker/resume-review')->has('extracted.education', 1));

        // The seeker keeps one skill, corrects the course name and confirms.
        $this->post("/resume/{$resume->id}/review", [
            'skills' => ['Organic Farming'],
            'education' => [[
                'level' => 'college_graduate',
                'school' => 'Benguet State University',
                'course' => 'BS Agriculture',
                'year_graduated' => 2020,
                'is_current' => false,
            ]],
            'certifications' => [['name' => 'Organic Agriculture Production NC II', 'issuer' => 'TESDA']],
        ])->assertRedirect('/profile');

        $this->assertSame(['Organic Farming'], $profile->skills()->pluck('name')->all());
        $this->assertSame('resume', $profile->skills()->first()->pivot->source);
        $this->assertSame('BS Agriculture', $profile->education()->sole()->course);
        $this->assertSame('college_graduate', $profile->fresh()->highest_education->value);
        $this->assertSame(ResumeExtractionStatus::Reviewed, $resume->fresh()->extraction_status);
    }

    public function test_review_rejects_invalid_items()
    {
        $user = User::factory()->seeker()->create();
        $this->actingAs($user)->post('/resume', ['resume' => $this->pdfUpload()]);
        $resume = Resume::sole();

        $this->post("/resume/{$resume->id}/review", [
            'education' => [['level' => null, 'school' => 'Benguet State University']],
        ])->assertSessionHasErrors('education.0.level');

        $this->assertSame(0, $user->applicantProfile->education()->count());
    }

    public function test_a_resume_can_be_removed()
    {
        $user = User::factory()->seeker()->create();
        $this->actingAs($user)->post('/resume', ['resume' => $this->pdfUpload()]);
        $resume = Resume::sole();

        $this->delete("/resume/{$resume->id}")->assertRedirect('/profile');

        $this->assertModelMissing($resume);
        $this->disk->assertMissing($resume->disk_path);
    }
}
