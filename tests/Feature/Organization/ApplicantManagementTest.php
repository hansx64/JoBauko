<?php

namespace Tests\Feature\Organization;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use Database\Seeders\SkillSeeder;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantManagementTest extends TestCase
{
    use RefreshDatabase;

    private LocalFilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
    }

    /**
     * @return array{organization: Organization, staff: User, job: Opportunity}
     */
    private function organizationWithJob(OrganizationRole $role = OrganizationRole::Admin): array
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember($role, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create(['title' => 'Farm Technician']);

        return ['organization' => $organization, 'staff' => $staff, 'job' => $job];
    }

    private function member(Organization $organization, OrganizationRole $role): User
    {
        $user = User::factory()->organization()->create();
        $organization->members()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true]);

        return $user;
    }

    public function test_the_applicant_list_only_shows_the_organizations_own_applications()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        $mine = Application::factory()->for($job)->create();
        Application::factory()->create();

        $this->actingAs($staff)
            ->get('/org/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('organization/applications/index')
                ->has('applications.data', 1)
                ->where('applications.data.0.id', $mine->id)
                ->where('counts.all', 1)
                ->has('options.opportunities', 1));
    }

    public function test_applicants_can_be_filtered_and_sorted_by_match()
    {
        ['organization' => $organization, 'staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        $other = Opportunity::factory()->job()->published()->for($organization)->create();
        $low = Application::factory()->for($job)->create(['match_score' => 20]);
        $high = Application::factory()->for($job)->status(ApplicationStatus::Shortlisted)->create(['match_score' => 90]);
        Application::factory()->for($other)->create();

        $this->actingAs($staff)
            ->get("/org/applications?opportunity={$job->id}&sort=match")
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 2)
                ->where('applications.data.0.id', $high->id)
                ->where('applications.data.1.id', $low->id)
                ->where('counts.all', 2));

        $this->get('/org/applications?status=shortlisted')
            ->assertInertia(fn ($page) => $page->has('applications.data', 1)->where('applications.data.0.id', $high->id));

        $this->get('/org/applications?q='.urlencode($low->user->name))
            ->assertInertia(fn ($page) => $page->where('applications.data.0.id', $low->id));
    }

    public function test_the_match_score_is_recorded_when_applying()
    {
        $this->seed(SkillSeeder::class);
        ['job' => $job] = $this->organizationWithJob();
        $job->skills()->attach(Skill::where('name', 'Organic Farming')->value('id'));
        $seeker = User::factory()->seeker()->create();
        $seeker->applicantProfile->skills()->attach(Skill::where('name', 'Organic Farming')->value('id'));
        Resume::factory()->for($seeker)->create();

        $this->actingAs($seeker)->post("/opportunities/{$job->slug}/apply", ['consent' => '1'])->assertSessionHasNoErrors();

        $this->assertGreaterThan(0, Application::sole()->match_score);
    }

    public function test_personnel_can_review_an_applicant_and_access_is_logged()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        $application = Application::factory()->for($job)->create();
        $application->user->applicantProfile->experience()->create(['position' => 'Farm Hand', 'company' => 'Abatan Farms', 'start_date' => '2022-01-01']);

        $this->actingAs($staff)
            ->get("/org/applications/{$application->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('organization/applications/show')
                ->where('applicant.name', $application->user->name)
                ->where('applicant.experience.0.position', 'Farm Hand')
                ->has('match.score')
                ->where('nextStatuses', fn ($statuses) => collect($statuses)->pluck('value')->all() === ['under_review', 'shortlisted', 'for_further_requirements', 'rejected'])
                ->where('canRemark', true));

        $this->assertDatabaseHas('audit_logs', ['action' => 'application.viewed', 'auditable_id' => $application->id, 'user_id' => $staff->id]);
    }

    public function test_other_organizations_cannot_open_an_application_or_its_files()
    {
        ['job' => $job] = $this->organizationWithJob();
        $resume = Resume::factory()->create();
        $application = Application::factory()->for($job)->for($resume->user)->create(['resume_id' => $resume->id]);
        ['staff' => $outsider] = $this->organizationWithJob();

        $this->actingAs($outsider)->get("/org/applications/{$application->id}")->assertNotFound();
        $this->get("/org/applications/{$application->id}/resume")->assertNotFound();
        $this->post("/org/applications/{$application->id}/status", ['status' => 'under_review'])->assertNotFound();
        $this->post("/org/applications/{$application->id}/remarks", ['body' => 'x'])->assertNotFound();

        $this->actingAs($application->user)->get("/org/applications/{$application->id}")->assertForbidden();
    }

    public function test_personnel_can_download_the_submitted_resume()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        $resume = Resume::factory()->create();
        $this->disk->put($resume->disk_path, '%PDF-1.4 resume');
        $application = Application::factory()->for($job)->for($resume->user)->create(['resume_id' => $resume->id]);

        $this->actingAs($staff)
            ->get("/org/applications/{$application->id}/resume")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_status_changes_follow_the_role_and_notify_the_applicant()
    {
        Notification::fake();
        ['organization' => $organization, 'job' => $job] = $this->organizationWithJob();
        $reviewer = $this->member($organization, OrganizationRole::Reviewer);
        $officer = $this->member($organization, OrganizationRole::RecruitmentOfficer);
        $application = Application::factory()->for($job)->create();

        $this->actingAs($reviewer)
            ->post("/org/applications/{$application->id}/status", ['status' => 'shortlisted', 'remarks' => 'You made the shortlist.'])
            ->assertSessionHasNoErrors();
        $this->post("/org/applications/{$application->id}/status", ['status' => 'approved'])->assertForbidden();

        $this->get("/org/applications/{$application->id}")
            ->assertInertia(fn ($page) => $page->where('nextStatuses', fn ($statuses) => ! collect($statuses)->pluck('value')->contains('approved')));

        $this->actingAs($officer)
            ->post("/org/applications/{$application->id}/status", ['status' => 'for_further_requirements'])
            ->assertSessionHasErrors('remarks');
        $this->post("/org/applications/{$application->id}/status", ['status' => 'approved'])->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Approved, $application->refresh()->status);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'to_status' => 'shortlisted', 'changed_by' => $reviewer->id, 'remarks' => 'You made the shortlist.']);
        Notification::assertSentToTimes($application->user, ApplicationStatusUpdated::class, 2);
    }

    public function test_internal_remarks_are_never_shown_to_the_applicant()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        $application = Application::factory()->for($job)->create();

        $this->actingAs($staff)
            ->post("/org/applications/{$application->id}/remarks", ['body' => 'Check the NC II certificate.'])
            ->assertSessionHasNoErrors();

        $this->get("/org/applications/{$application->id}")
            ->assertInertia(fn ($page) => $page->where('application.remarks.0.body', 'Check the NC II certificate.'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'application.remark_added', 'auditable_id' => $application->id]);

        $this->actingAs($application->user)
            ->get("/applications/{$application->id}")
            ->assertOk()
            ->assertDontSee('Check the NC II certificate.')
            ->assertInertia(fn ($page) => $page->missing('application.remarks'));
    }

    public function test_the_dashboard_summarizes_applications_with_chart_data()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        Application::factory()->for($job)->create();
        Application::factory()->for($job)->create(['submitted_at' => now()->subDays(3)]);
        Application::factory()->for($job)->status(ApplicationStatus::Approved)->create(['submitted_at' => now()->subDays(40)]);
        Application::factory()->create();

        $this->actingAs($staff)
            ->get('/org/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 3)
                ->where('applications.awaitingReview', 2)
                ->where('applications.approved', 1)
                ->where('applications.last30Days', 2)
                ->has('applications.perDay', 30)
                ->where('applications.perDay.29.count', 1)
                ->where('applications.byStatus.0', ['status' => 'submitted', 'label' => 'Submitted', 'count' => 2])
                ->where('applications.topOpportunities.0', ['id' => $job->id, 'title' => 'Farm Technician', 'total' => 3, 'new' => 2]));
    }

    public function test_the_opportunity_list_shows_applicant_counts()
    {
        ['staff' => $staff, 'job' => $job] = $this->organizationWithJob();
        Application::factory()->count(2)->for($job)->create();

        $this->actingAs($staff)
            ->get('/org/opportunities')
            ->assertInertia(fn ($page) => $page->where('opportunities.data.0.applications_count', 2));
    }
}
