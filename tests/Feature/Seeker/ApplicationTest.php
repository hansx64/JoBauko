<?php

namespace Tests\Feature\Seeker;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\OpportunityDocument;
use App\Models\Organization;
use App\Models\Resume;
use App\Models\User;
use App\Notifications\ApplicationActivity;
use App\Services\Applications\ApplicationWorkflow;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private LocalFilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
    }

    private function seekerWithResume(): User
    {
        $seeker = User::factory()->seeker()->create();
        Resume::factory()->for($seeker)->create();

        return $seeker;
    }

    /**
     * A published job of a verified organization with one required and one
     * optional document, and an organization administrator.
     *
     * @return array{Opportunity, User}
     */
    private function jobWithDocuments(): array
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create();
        $job->requiredDocuments()->create(['name' => 'Transcript of Records', 'is_required' => true, 'accepted_types' => 'pdf']);
        $job->requiredDocuments()->create(['name' => 'Certificate of Employment', 'is_required' => false]);

        return [$job, $staff];
    }

    private function pdf(string $name = 'transcript.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    public function test_a_seeker_can_apply_with_resume_documents_and_cover_letter()
    {
        Notification::fake();
        [$job, $staff] = $this->jobWithDocuments();
        $seeker = $this->seekerWithResume();
        $transcript = $job->requiredDocuments()->where('is_required', true)->value('id');

        $this->actingAs($seeker)
            ->get("/opportunities/{$job->slug}/apply")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('seeker/applications/create')->has('documents', 2)->where('resumeRequired', true));

        $response = $this->post("/opportunities/{$job->slug}/apply", [
            'cover_letter' => 'I would love to help the municipal hall.',
            'consent' => '1',
            'documents' => [$transcript => $this->pdf()],
        ]);

        $application = Application::sole();
        $response->assertRedirect(route('seeker.applications.show', $application));

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame($seeker->currentResume->id, $application->resume_id);
        $this->assertNotNull($application->data_sharing_consented_at);
        $this->assertSame('I would love to help the municipal hall.', $application->cover_letter);

        $document = $application->documents()->sole();
        $this->assertSame('Transcript of Records', $document->label);
        $this->disk->assertExists($document->disk_path);
        $this->assertStringStartsWith("Application documents/{$application->id} - {$seeker->name} - {$job->title}/Transcript of Records (", $document->disk_path);

        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'from_status' => null, 'to_status' => 'submitted', 'changed_by' => $seeker->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.submitted', 'auditable_id' => $application->id, 'organization_id' => $job->organization_id]);
        Notification::assertSentTo($staff, ApplicationActivity::class, fn ($n) => $n->event === 'submitted');
    }

    public function test_required_documents_and_consent_are_enforced()
    {
        [$job] = $this->jobWithDocuments();
        $seeker = $this->seekerWithResume();
        $transcript = $job->requiredDocuments()->where('is_required', true)->value('id');

        $this->actingAs($seeker)
            ->post("/opportunities/{$job->slug}/apply", [])
            ->assertSessionHasErrors(['consent', "documents.{$transcript}"]);

        // The transcript accepts only PDF files.
        $this->post("/opportunities/{$job->slug}/apply", [
            'consent' => '1',
            'documents' => [$transcript => UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg')],
        ])->assertSessionHasErrors("documents.{$transcript}");

        $this->post("/opportunities/{$job->slug}/apply", [
            'consent' => '1',
            'documents' => [$transcript => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')],
        ])->assertSessionHasErrors("documents.{$transcript}");

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_a_resume_document_is_covered_by_the_profile_resume()
    {
        [$job, $staff] = $this->jobWithDocuments();
        $resumeDocument = $job->requiredDocuments()->create(['name' => 'Resume / CV', 'is_required' => true]);
        $transcript = $job->requiredDocuments()->where('name', 'Transcript of Records')->value('id');
        $seeker = $this->seekerWithResume();

        $this->actingAs($seeker)
            ->get("/opportunities/{$job->slug}/apply")
            ->assertInertia(fn ($page) => $page
                ->where('documents', fn ($documents) => collect($documents)->firstWhere('id', $resumeDocument->id)['is_resume'] === true
                    && collect($documents)->firstWhere('id', $transcript)['is_resume'] === false));

        $this->post("/opportunities/{$job->slug}/apply", ['consent' => '1', 'documents' => [$transcript => $this->pdf()]])
            ->assertSessionHasNoErrors();

        $application = Application::sole();
        $this->assertSame($seeker->currentResume->id, $application->resume_id);
        $this->assertSame(['Transcript of Records'], $application->documents()->pluck('label')->all());

        // The organization does not see the resume document as missing.
        $this->actingAs($staff)
            ->get(route('org.applications.show', $application))
            ->assertInertia(fn ($page) => $page->where('application.missing_documents', []));
    }

    public function test_a_resume_document_must_be_uploaded_when_no_profile_resume_is_sent()
    {
        $scholarship = Opportunity::factory()->published()->create(['category' => OpportunityCategory::Scholarship]);
        $resumeDocument = $scholarship->requiredDocuments()->create(['name' => 'Updated résumé', 'is_required' => true]);

        $this->actingAs(User::factory()->seeker()->create())
            ->post("/opportunities/{$scholarship->slug}/apply", ['consent' => '1'])
            ->assertSessionHasErrors("documents.{$resumeDocument->id}");

        $this->actingAs($this->seekerWithResume())
            ->post("/opportunities/{$scholarship->slug}/apply", ['consent' => '1', 'include_resume' => '0'])
            ->assertSessionHasErrors("documents.{$resumeDocument->id}");

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_resume_documents_are_recognized_by_name()
    {
        $isResume = fn (string $name) => (new OpportunityDocument(['name' => $name]))->isResume();

        foreach (['Resume / CV', 'Updated Résumé', 'Curriculum Vitae', 'CV with 2x2 photo'] as $name) {
            $this->assertTrue($isResume($name), $name);
        }
        foreach (['Transcript of Records', 'Barangay Certificate of Residency', 'Certificate of Employment'] as $name) {
            $this->assertFalse($isResume($name), $name);
        }
    }

    public function test_a_job_application_needs_a_resume_but_a_scholarship_does_not()
    {
        $seeker = User::factory()->seeker()->create();
        $job = Opportunity::factory()->job()->published()->create();
        $scholarship = Opportunity::factory()->published()->create(['category' => OpportunityCategory::Scholarship]);

        $this->actingAs($seeker)
            ->post("/opportunities/{$job->slug}/apply", ['consent' => '1'])
            ->assertSessionHasErrors('resume');

        $this->post("/opportunities/{$scholarship->slug}/apply", ['consent' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame([$scholarship->id], Application::pluck('opportunity_id')->all());
        $this->assertNull(Application::sole()->resume_id);
    }

    public function test_a_seeker_cannot_apply_twice()
    {
        $seeker = $this->seekerWithResume();
        $job = Opportunity::factory()->job()->published()->create();
        $this->actingAs($seeker);

        $this->post("/opportunities/{$job->slug}/apply", ['consent' => '1'])->assertSessionHasNoErrors();
        $this->post("/opportunities/{$job->slug}/apply", ['consent' => '1'])->assertSessionHasErrors('application');

        $this->get("/opportunities/{$job->slug}/apply")->assertRedirect(route('seeker.applications.show', Application::sole()));
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_applications_are_refused_outside_the_application_period()
    {
        $seeker = $this->seekerWithResume();
        $expired = Opportunity::factory()->job()->expired()->create();
        $closed = Opportunity::factory()->job()->closed()->create();
        $notYetOpen = Opportunity::factory()->job()->published()->create(['application_start' => now()->addWeek()->toDateString()]);
        $this->actingAs($seeker);

        foreach ([$expired, $closed, $notYetOpen] as $opportunity) {
            $this->post("/opportunities/{$opportunity->slug}/apply", ['consent' => '1'])->assertSessionHasErrors('application');
            $this->get("/opportunities/{$opportunity->slug}/apply")->assertRedirect(route('opportunities.show', $opportunity));
        }

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_hidden_opportunities_cannot_be_applied_to()
    {
        $seeker = $this->seekerWithResume();
        $draft = Opportunity::factory()->job()->create(['status' => OpportunityStatus::Draft]);
        $unverified = Opportunity::factory()->job()->published()->for(Organization::factory())->create();
        $this->actingAs($seeker);

        foreach ([$draft, $unverified] as $opportunity) {
            $this->get("/opportunities/{$opportunity->slug}/apply")->assertNotFound();
            $this->post("/opportunities/{$opportunity->slug}/apply", ['consent' => '1'])->assertNotFound();
        }
    }

    public function test_only_seekers_can_apply()
    {
        $job = Opportunity::factory()->job()->published()->create();

        $this->get("/opportunities/{$job->slug}/apply")->assertRedirect(route('login'));
        $this->actingAs(User::factory()->organization()->create())
            ->post("/opportunities/{$job->slug}/apply", ['consent' => '1'])
            ->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->get("/opportunities/{$job->slug}/apply")
            ->assertForbidden();
    }

    public function test_the_details_page_shows_the_apply_button_state()
    {
        $seeker = $this->seekerWithResume();
        $job = Opportunity::factory()->job()->published()->create();

        $this->actingAs($seeker)
            ->get("/opportunities/{$job->slug}")
            ->assertInertia(fn ($page) => $page->where('application', null)->where('opportunity.accepting', true));

        $this->post("/opportunities/{$job->slug}/apply", ['consent' => '1']);

        $this->get("/opportunities/{$job->slug}")
            ->assertInertia(fn ($page) => $page->where('application.id', Application::sole()->id)->where('application.status', 'submitted'));
    }

    public function test_seekers_see_their_own_applications_with_history()
    {
        $seeker = User::factory()->seeker()->create();
        $mine = Application::factory()->for($seeker)->create();
        $approved = Application::factory()->for($seeker)->status(ApplicationStatus::Approved)->create();
        Application::factory()->create();

        $this->actingAs($seeker)
            ->get('/applications')
            ->assertInertia(fn ($page) => $page
                ->component('seeker/applications/index')
                ->has('applications.data', 2)
                ->where('counts.all', 2)
                ->where('counts.active', 1)
                ->where('counts.approved', 1));

        $this->get('/applications?tab=approved')
            ->assertInertia(fn ($page) => $page->has('applications.data', 1)->where('applications.data.0.id', $approved->id));

        $this->get("/applications/{$mine->id}")
            ->assertInertia(fn ($page) => $page
                ->component('seeker/applications/show')
                ->where('application.can_withdraw', true)
                ->has('application.history', 1)
                ->where('application.history.0.by_applicant', true));
    }

    public function test_seekers_cannot_see_other_peoples_applications_or_files()
    {
        $application = Application::factory()->create();
        $document = $application->documents()->create([
            'label' => 'Transcript', 'disk_path' => 'applications/x/file.pdf', 'original_name' => 'file.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10,
        ]);
        $other = User::factory()->seeker()->create();

        $this->actingAs($other)->get("/applications/{$application->id}")->assertNotFound();
        $this->post("/applications/{$application->id}/withdraw")->assertNotFound();
        $this->get("/application-documents/{$document->id}")->assertNotFound();
    }

    public function test_application_files_are_served_to_the_applicant_and_the_organization_only()
    {
        Notification::fake();
        [$job, $staff] = $this->jobWithDocuments();
        $seeker = $this->seekerWithResume();
        $transcript = $job->requiredDocuments()->where('is_required', true)->value('id');

        $this->actingAs($seeker)->post("/opportunities/{$job->slug}/apply", ['consent' => '1', 'documents' => [$transcript => $this->pdf()]]);
        $document = Application::sole()->documents()->sole();

        $cache = (string) $this->get("/application-documents/{$document->id}")->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
        $this->actingAs($staff)->get("/application-documents/{$document->id}?preview=1")->assertOk();

        $outsider = User::factory()->organization()->create();
        Organization::factory()->verified()->withMember(OrganizationRole::Admin, $outsider)->create();
        $this->actingAs($outsider)->get("/application-documents/{$document->id}")->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get("/application-documents/{$document->id}")->assertNotFound();
    }

    public function test_a_seeker_can_withdraw_an_application_in_progress()
    {
        Notification::fake();
        [$job, $staff] = $this->jobWithDocuments();
        $seeker = User::factory()->seeker()->create();
        $application = Application::factory()->for($seeker)->for($job)->status(ApplicationStatus::UnderReview)->create();

        $this->actingAs($seeker)
            ->post("/applications/{$application->id}/withdraw", ['reason' => 'I accepted another offer.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Withdrawn, $application->status);
        $this->assertNotNull($application->withdrawn_at);
        $this->assertSame('I accepted another offer.', $application->withdrawal_reason);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'from_status' => 'under_review', 'to_status' => 'withdrawn']);
        Notification::assertSentTo($staff, ApplicationActivity::class, fn ($n) => $n->event === 'withdrawn');

        // Withdrawing is final: no second withdrawal and no new application.
        $transcript = $job->requiredDocuments()->where('is_required', true)->value('id');
        $this->post("/applications/{$application->id}/withdraw")->assertForbidden();
        $this->post("/opportunities/{$job->slug}/apply", ['consent' => '1', 'documents' => [$transcript => $this->pdf()]])
            ->assertSessionHasErrors('application');
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_decided_applications_cannot_be_withdrawn()
    {
        $seeker = User::factory()->seeker()->create();
        $application = Application::factory()->for($seeker)->status(ApplicationStatus::Approved)->create();

        $this->actingAs($seeker)->post("/applications/{$application->id}/withdraw")->assertForbidden();
        $this->assertSame(ApplicationStatus::Approved, $application->refresh()->status);
    }

    public function test_seekers_can_add_documents_only_when_further_requirements_are_requested()
    {
        Notification::fake();
        [$job, $staff] = $this->jobWithDocuments();
        $seeker = User::factory()->seeker()->create();
        $application = Application::factory()->for($seeker)->for($job)->create();
        $this->actingAs($seeker);

        $this->post("/applications/{$application->id}/documents", ['label' => 'Barangay Clearance', 'file' => $this->pdf('clearance.pdf')])
            ->assertForbidden();

        app(ApplicationWorkflow::class)->transition($application, ApplicationStatus::ForFurtherRequirements, $staff, 'Please send your barangay clearance.');

        $this->post("/applications/{$application->id}/documents", ['label' => 'Barangay Clearance', 'file' => $this->pdf('clearance.pdf')])
            ->assertSessionHasNoErrors();

        $document = $application->documents()->sole();
        $this->assertSame('Barangay Clearance', $document->label);
        $this->assertNull($document->opportunity_document_id);
        $this->disk->assertExists($document->disk_path);
        Notification::assertSentTo($staff, ApplicationActivity::class, fn ($n) => $n->event === 'documents_added');

        $this->get("/applications/{$application->id}")
            ->assertInertia(fn ($page) => $page
                ->where('application.accepts_documents', true)
                ->where('application.history.1.remarks', 'Please send your barangay clearance.')
                ->where('application.history.1.by_applicant', false));
    }

    public function test_the_dashboard_summarizes_applications()
    {
        $seeker = User::factory()->seeker()->create();
        Application::factory()->for($seeker)->create();
        Application::factory()->for($seeker)->status(ApplicationStatus::Approved)->create();
        Application::factory()->for($seeker)->status(ApplicationStatus::Withdrawn)->create();

        $this->actingAs($seeker)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 3)
                ->where('applications.active', 1)
                ->where('applications.approved', 1)
                ->has('applications.recent', 3));
    }

    public function test_a_resume_used_in_an_application_is_kept_when_replaced()
    {
        $seeker = $this->seekerWithResume();
        $resume = $seeker->currentResume;
        $this->disk->put($resume->disk_path, '%PDF-1.4 test');
        $job = Opportunity::factory()->job()->published()->create();

        $this->actingAs($seeker)->post("/opportunities/{$job->slug}/apply", ['consent' => '1']);
        $this->delete("/resume/{$resume->id}");

        $this->assertModelExists($resume);
        $this->disk->assertExists($resume->disk_path);
        $this->get('/applications/'.Application::sole()->id)
            ->assertInertia(fn ($page) => $page->where('application.resume.original_name', $resume->original_name));
    }
}
