<?php

namespace Tests\Feature\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicationStatusUpdated;
use App\Services\Applications\ApplicationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Application, User}
     */
    private function application(OrganizationRole $role = OrganizationRole::Admin): array
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember($role, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create();

        return [Application::factory()->for($job)->create(), $staff];
    }

    public function test_status_changes_are_recorded_with_timestamps_history_and_audit()
    {
        Notification::fake();
        [$application, $staff] = $this->application();
        $workflow = app(ApplicationWorkflow::class);
        $this->actingAs($staff);

        $workflow->transition($application, ApplicationStatus::UnderReview, $staff);
        $this->assertNotNull($application->first_reviewed_at);
        $this->assertNull($application->decided_at);

        $workflow->transition($application, ApplicationStatus::ForInterview, $staff, '  Interview on Monday, 9 AM at the municipal hall.  ');
        $workflow->transition($application, ApplicationStatus::Approved, $staff);

        $application->refresh();
        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertNotNull($application->decided_at);

        $this->assertSame(
            ['submitted', 'under_review', 'for_interview', 'approved'],
            $application->statusHistory->map(fn ($entry) => $entry->to_status->value)->all(),
        );
        $this->assertSame('Interview on Monday, 9 AM at the municipal hall.', $application->statusHistory[2]->remarks);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.status_changed',
            'auditable_id' => $application->id,
            'organization_id' => $application->opportunity->organization_id,
            'user_id' => $staff->id,
        ]);
        Notification::assertSentToTimes($application->user, ApplicationStatusUpdated::class, 3);
    }

    public function test_invalid_transitions_are_refused()
    {
        [$application, $staff] = $this->application();
        $workflow = app(ApplicationWorkflow::class);

        try {
            $workflow->transition($application, ApplicationStatus::ForInterview, $staff);
            $this->fail('Submitted applications must be reviewed before an interview.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $workflow->transition($application, ApplicationStatus::Rejected, $staff);

        foreach ([ApplicationStatus::UnderReview, ApplicationStatus::Approved, ApplicationStatus::Withdrawn] as $to) {
            try {
                $workflow->transition($application, $to, $staff);
                $this->fail("A rejected application must not move to {$to->value}.");
            } catch (ValidationException) {
                $this->assertSame(ApplicationStatus::Rejected, $application->refresh()->status);
            }
        }
    }

    public function test_further_requirements_need_remarks_for_the_applicant()
    {
        [$application, $staff] = $this->application();

        $this->expectException(ValidationException::class);

        app(ApplicationWorkflow::class)->transition($application, ApplicationStatus::ForFurtherRequirements, $staff, '   ');
    }

    public function test_only_final_statuses_block_withdrawal()
    {
        foreach (ApplicationStatus::cases() as $status) {
            $application = Application::factory()->make(['status' => $status]);

            $this->assertSame(! in_array($status, [ApplicationStatus::Approved, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn], true), $application->canBeWithdrawn());
        }
    }

    public function test_reviewers_cannot_approve_or_reject()
    {
        [$application, $reviewer] = $this->application(OrganizationRole::Reviewer);
        $officer = User::factory()->organization()->create();
        $application->opportunity->organization->members()->create(['user_id' => $officer->id, 'role' => OrganizationRole::RecruitmentOfficer, 'is_active' => true]);

        $this->assertTrue(Gate::forUser($reviewer)->allows('changeStatus', [$application, ApplicationStatus::Shortlisted]));
        $this->assertFalse(Gate::forUser($reviewer)->allows('changeStatus', [$application, ApplicationStatus::Approved]));
        $this->assertFalse(Gate::forUser($reviewer)->allows('changeStatus', [$application, ApplicationStatus::Rejected]));
        $this->assertTrue(Gate::forUser($officer)->allows('changeStatus', [$application, ApplicationStatus::Approved]));
    }

    public function test_other_organizations_and_inactive_members_cannot_touch_an_application()
    {
        [$application, $staff] = $this->application();
        [, $otherStaff] = $this->application();

        $this->assertTrue(Gate::forUser($staff)->allows('view', $application));
        $this->assertTrue(Gate::forUser($otherStaff)->inspect('view', $application)->status() === 404);
        $this->assertFalse(Gate::forUser($otherStaff)->allows('changeStatus', [$application, ApplicationStatus::UnderReview]));

        $staff->organizationMembership->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($staff->refresh())->allows('view', $application));
    }
}
