<?php

namespace Tests\Feature\Seeker;

use App\Enums\ResumeExtractionStatus;
use App\Models\Application;
use App\Models\Barangay;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class ResumeBuilderTest extends TestCase
{
    use RefreshDatabase;

    private LocalFilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
        $this->seed(LocationSeeder::class);
    }

    private function seeker(): User
    {
        $user = User::factory()->seeker()->create([
            'name' => 'Maria Santos',
            'mobile_number' => '09171234567',
            'barangay_id' => Barangay::where('name', 'Abatan')->sole()->id,
        ]);

        $profile = $user->applicantProfile;
        $profile->update([
            'date_of_birth' => '2000-05-14',
            'gender' => 'female',
            'address_line' => 'Sitio Lamagan',
            'summary' => 'Hardworking graduate looking for office work.',
        ]);
        $profile->education()->create([
            'level' => 'college_graduate',
            'school' => 'Mountain Province State University',
            'course' => 'BS Office Administration',
            'year_graduated' => 2022,
        ]);
        $profile->experience()->create([
            'position' => 'Records Assistant',
            'company' => 'Municipal Treasurer Office',
            'start_date' => '2022-07-01',
            'description' => "- Encoded tax records\n• Answered client inquiries",
        ]);
        $profile->trainings()->create(['title' => 'Basic Bookkeeping', 'provider' => 'TESDA', 'hours' => 40]);
        $profile->skills()->attach(Skill::create(['name' => 'Data Encoding', 'slug' => 'data-encoding']));

        return $user->refresh();
    }

    private function pdfText(string $pdf): string
    {
        return preg_replace('/\s+/', ' ', (new Parser)->parseContent($pdf)->getText());
    }

    private function validDetails(array $overrides = []): array
    {
        return array_merge([
            'desired_position' => 'Office Clerk',
            'objective' => 'To work as an office clerk and grow with the organization.',
            'languages' => ['English', ' Kankanaey '],
            'references' => [
                ['name' => 'Juan Dela Cruz', 'position' => 'Municipal Treasurer', 'organization' => 'LGU Bauko', 'contact' => '09181234567'],
            ],
            'civil_status' => 'single',
            'nationality' => 'Filipino',
            'show_birthdate' => false,
            'show_sex' => false,
            'include_certification' => true,
        ], $overrides);
    }

    public function test_the_builder_shows_what_comes_from_the_profile()
    {
        $this->actingAs($this->seeker())
            ->get('/resume-builder')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('seeker/resume-builder')
                ->where('details.objective', 'Hardworking graduate looking for office work.')
                ->where('details.include_certification', true)
                ->where('contents.address', 'Sitio Lamagan, Abatan, Bauko, Mountain Province')
                ->where('contents.experience', 1)
                ->where('contents.education', 1)
                ->where('contents.skills', 1)
                ->where('gaps', [])
                ->where('currentResume', null));
    }

    public function test_an_empty_profile_lists_what_is_missing()
    {
        $this->actingAs(User::factory()->seeker()->create(['mobile_number' => null]))
            ->get('/resume-builder')
            ->assertInertia(fn (Assert $page) => $page->has('gaps', 6));
    }

    public function test_details_are_saved()
    {
        $user = $this->seeker();

        $this->actingAs($user)->put('/resume-builder', $this->validDetails())->assertSessionHasNoErrors();

        $details = $user->resumeDetail()->sole();
        $this->assertSame('Office Clerk', $details->desired_position);
        $this->assertSame(['English', 'Kankanaey'], $details->languages);
        $this->assertSame('Juan Dela Cruz', $details->references[0]['name']);
        $this->assertSame('single', $details->civil_status->value);
    }

    public function test_details_are_validated()
    {
        $reference = ['name' => 'A', 'contact' => '0917'];

        $this->actingAs($this->seeker())
            ->put('/resume-builder', $this->validDetails([
                'languages' => ['English', 'english'],
                'references' => [['name' => 'No Contact'], $reference, $reference, $reference],
                'civil_status' => 'complicated',
            ]))
            ->assertSessionHasErrors(['languages.1', 'references', 'references.0.contact', 'civil_status']);
    }

    public function test_the_pdf_follows_the_standard_format()
    {
        $user = $this->seeker();
        $this->actingAs($user)->put('/resume-builder', $this->validDetails());

        $response = $this->get('/resume-builder/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline; filename="maria-santos-resume.pdf"', $response->headers->get('Content-Disposition'));

        $text = $this->pdfText($response->getContent());
        foreach ([
            'MARIA SANTOS',
            'Sitio Lamagan, Abatan, Bauko, Mountain Province',
            '09171234567',
            'Applying for: Office Clerk',
            'To work as an office clerk',
            'Records Assistant',
            'Jul 2022 – Present',
            'Encoded tax records',
            'BS Office Administration',
            'Basic Bookkeeping',
            'TESDA · 40 hours',
            'Data Encoding',
            'English, Kankanaey',
            'Civil status Single',
            'Juan Dela Cruz',
            'I hereby certify',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringNotContainsString('- Encoded', $text);
        // Work experience comes before education when there is any.
        $this->assertLessThan(strpos($text, 'EDUCATION'), strpos($text, 'WORK EXPERIENCE'));
        // Age and sex are left out unless the seeker opts in.
        $this->assertStringNotContainsString('Date of birth', $text);
        $this->assertStringNotContainsString('Female', $text);
    }

    public function test_optional_personal_details_appear_only_when_chosen()
    {
        $user = $this->seeker();
        $this->actingAs($user)->put('/resume-builder', $this->validDetails([
            'show_birthdate' => true,
            'show_sex' => true,
            'references' => [],
            'include_certification' => false,
        ]));

        $text = $this->pdfText($this->get('/resume-builder/pdf?download=1')->getContent());

        $this->assertStringContainsString('May 14, 2000', $text);
        $this->assertStringContainsString('Female', $text);
        $this->assertStringContainsString('Available upon request', $text);
        $this->assertStringNotContainsString('I hereby certify', $text);
    }

    public function test_without_work_experience_education_comes_first()
    {
        $user = $this->seeker();
        $user->applicantProfile->experience()->delete();

        $text = $this->pdfText($this->actingAs($user)->get('/resume-builder/pdf')->getContent());

        $this->assertStringNotContainsString('WORK EXPERIENCE', $text);
        $this->assertLessThan(strpos($text, 'TRAININGS'), strpos($text, 'EDUCATION'));
    }

    public function test_the_pdf_can_be_used_as_the_current_resume()
    {
        $user = $this->seeker();
        $uploaded = Resume::factory()->for($user)->create();
        $this->disk->put($uploaded->disk_path, 'old');

        $this->actingAs($user)->post('/resume-builder/save')->assertRedirect('/profile');

        $resume = $user->refresh()->currentResume;
        $this->assertTrue($resume->is_generated);
        $this->assertSame('maria-santos-resume.pdf', $resume->original_name);
        $this->assertSame(ResumeExtractionStatus::Reviewed, $resume->extraction_status);
        $this->assertStringContainsString('Records Assistant', $this->pdfText($this->disk->get($resume->disk_path)));

        // The unused uploaded resume is replaced.
        $this->assertModelMissing($uploaded);
        $this->disk->assertMissing($uploaded->disk_path);
    }

    public function test_a_resume_already_sent_with_an_application_is_kept()
    {
        $user = $this->seeker();
        $sent = Resume::factory()->for($user)->create();
        Application::factory()->for($user)->create(['resume_id' => $sent->id]);

        $this->actingAs($user)->post('/resume-builder/save');

        $this->assertFalse($sent->refresh()->is_current);
        $this->assertTrue($user->refresh()->currentResume->is_generated);
    }

    public function test_the_resume_card_shows_a_generated_resume()
    {
        $user = $this->seeker();
        $this->actingAs($user)->post('/resume-builder/save');

        $this->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('resume.is_generated', true)
            ->where('completion.sections', fn ($sections) => collect($sections)->firstWhere('key', 'resume')['complete'] === true));
    }

    public function test_only_seekers_can_use_the_builder()
    {
        $this->get('/resume-builder')->assertRedirect(route('login'));
        $this->get('/resume-builder/pdf')->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/resume-builder')->assertForbidden();
        $this->actingAs($admin)->post('/resume-builder/save')->assertForbidden();
    }
}
