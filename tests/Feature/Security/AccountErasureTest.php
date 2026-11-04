<?php

namespace Tests\Feature\Security;

use App\Models\Application;
use App\Models\Resume;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\UploadPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountErasureTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_account_erases_its_records_and_files()
    {
        $disk = Storage::fake('local');
        $seeker = User::factory()->seeker()->create();
        $resumeFolder = UploadPaths::resumes($seeker);
        $resume = Resume::factory()->for($seeker)->create(['disk_path' => "{$resumeFolder}/My resume (a1b2c3d4).pdf"]);
        $disk->put($resume->disk_path, '%PDF-1.4 resume');
        $application = Application::factory()->for($seeker)->create(['resume_id' => $resume->id]);
        $applicationFolder = UploadPaths::application($application);
        $document = $application->documents()->create([
            'label' => 'Barangay clearance',
            'original_name' => 'clearance.pdf',
            'disk_path' => "{$applicationFolder}/Barangay clearance (e5f6a7b8).pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
        ]);
        $disk->put($document->disk_path, '%PDF-1.4 clearance');

        $someoneElse = User::factory()->seeker()->create();
        $otherFile = UploadPaths::resumes($someoneElse).'/Resume (11112222).pdf';
        $disk->put($otherFile, '%PDF-1.4 other');

        $this->actingAs($seeker);
        app(AuditLogger::class)->log('application.withdrawn', $application);

        $this->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $seeker->id]);
        $this->assertDatabaseMissing('applicant_profiles', ['user_id' => $seeker->id]);
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('resumes', ['id' => $resume->id]);
        $disk->assertMissing($resume->disk_path);
        $disk->assertMissing($document->disk_path);

        // Folders are named after the person, so they go too; other people's stay.
        $this->assertFalse($disk->directoryExists($resumeFolder));
        $this->assertFalse($disk->directoryExists($applicationFolder));
        $disk->assertExists($otherFile);

        // The audit trail is kept, no longer linked to the person.
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.withdrawn', 'user_id' => null]);
    }
}
