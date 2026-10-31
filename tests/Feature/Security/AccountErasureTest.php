<?php

namespace Tests\Feature\Security;

use App\Models\Application;
use App\Models\Resume;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
        $resume = Resume::factory()->for($seeker)->create();
        $disk->put($resume->disk_path, '%PDF-1.4 resume');
        $application = Application::factory()->for($seeker)->create(['resume_id' => $resume->id]);
        $document = $application->documents()->create([
            'label' => 'Barangay clearance',
            'original_name' => 'clearance.pdf',
            'disk_path' => "applications/{$application->id}/clearance.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
        ]);
        $disk->put($document->disk_path, '%PDF-1.4 clearance');

        $this->actingAs($seeker);
        app(AuditLogger::class)->log('application.withdrawn', $application);

        $this->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $seeker->id]);
        $this->assertDatabaseMissing('applicant_profiles', ['user_id' => $seeker->id]);
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('resumes', ['id' => $resume->id]);
        $disk->assertMissing($resume->disk_path);
        $disk->assertMissing($document->disk_path);

        // The audit trail is kept, no longer linked to the person.
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.withdrawn', 'user_id' => null]);
    }
}
