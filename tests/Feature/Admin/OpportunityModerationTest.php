<?php

namespace Tests\Feature\Admin;

use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Notifications\OpportunityModerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OpportunityModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_every_opportunity_with_reported_ones_first()
    {
        $admin = User::factory()->admin()->create();
        Opportunity::factory()->create(['title' => 'A draft']);
        $reported = Opportunity::factory()->job()->published()->create(['title' => 'Reported job']);
        Report::factory()->against($reported)->create();
        Application::factory()->count(2)->for($reported)->create();

        $this->actingAs($admin)
            ->get('/admin/opportunities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/opportunities/index')
                ->has('opportunities.data', 2)
                ->where('opportunities.data.0.title', 'Reported job')
                ->where('opportunities.data.0.open_reports_count', 1)
                ->where('opportunities.data.0.applications_count', 2)
                ->where('counts.reported', 1)
                ->where('counts.draft', 1));

        $this->get('/admin/opportunities?status=draft')
            ->assertInertia(fn ($page) => $page->has('opportunities.data', 1)->where('opportunities.data.0.title', 'A draft'));
    }

    public function test_suspending_hides_locks_and_notifies_the_organization()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create();

        $this->actingAs($admin)
            ->post("/admin/opportunities/{$job->id}/suspend", ['reason' => 'Asks applicants for a placement fee.'])
            ->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame(OpportunityStatus::Suspended, $job->status);
        $this->assertSame(OpportunityStatus::Published, $job->status_before_suspension);
        $this->assertSame($admin->id, $job->suspended_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.suspended', 'auditable_id' => $job->id, 'organization_id' => $organization->id]);
        Notification::assertSentTo($staff, OpportunityModerated::class);

        $this->get("/opportunities/{$job->slug}")->assertNotFound();

        // The organization sees why and can no longer edit it.
        $this->actingAs($staff)
            ->get("/org/opportunities/{$job->id}/edit")
            ->assertInertia(fn ($page) => $page->where('opportunity.suspension_reason', 'Asks applicants for a placement fee.'));
        $this->post("/org/opportunities/{$job->id}/archive")->assertSessionHasErrors('status');
        $this->post("/org/opportunities/{$job->id}/unpublish")->assertSessionHasErrors('status');
        $this->assertSame(OpportunityStatus::Suspended, $job->refresh()->status);
    }

    public function test_reinstating_restores_the_previous_status()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $job = Opportunity::factory()->job()->closed()->create();

        $this->actingAs($admin)->post("/admin/opportunities/{$job->id}/suspend", ['reason' => 'Under investigation for fraud.']);
        $this->post("/admin/opportunities/{$job->id}/reinstate")->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame(OpportunityStatus::Closed, $job->status);
        $this->assertNull($job->suspension_reason);
        $this->assertNull($job->status_before_suspension);
        $this->assertDatabaseHas('audit_logs', ['action' => 'opportunity.reinstated', 'auditable_id' => $job->id]);
    }

    public function test_suspension_needs_a_reason_and_a_suspendable_status()
    {
        $admin = User::factory()->admin()->create();
        $job = Opportunity::factory()->job()->published()->create();
        $archived = Opportunity::factory()->create(['status' => OpportunityStatus::Archived]);

        $this->actingAs($admin)->post("/admin/opportunities/{$job->id}/suspend", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/opportunities/{$archived->id}/suspend", ['reason' => 'Should not be possible.'])->assertSessionHasErrors('status');
        $this->post("/admin/opportunities/{$job->id}/reinstate")->assertSessionHasErrors('status');

        $this->assertSame(OpportunityStatus::Published, $job->refresh()->status);
    }

    public function test_the_detail_page_shows_reports_applications_and_history()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $job = Opportunity::factory()->job()->published()->create();
        Report::factory()->against($job)->create(['details' => 'They asked me for 500 pesos.']);
        Application::factory()->for($job)->create();

        $this->actingAs($admin)->post("/admin/opportunities/{$job->id}/suspend", ['reason' => 'Asks applicants for a placement fee.']);

        $this->get("/admin/opportunities/{$job->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/opportunities/show')
                ->where('opportunity.is_public', false)
                ->where('opportunity.suspension.reason', 'Asks applicants for a placement fee.')
                ->where('opportunity.suspension.previous_label', 'Published')
                ->where('applications.total', 1)
                ->where('reports.0.details', 'They asked me for 500 pesos.')
                ->where('history.0.action', 'opportunity.suspended'));
    }

    public function test_non_admins_cannot_moderate()
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create();

        $this->actingAs($staff)->post("/admin/opportunities/{$job->id}/suspend", ['reason' => 'Trying to suspend.'])->assertForbidden();
        $this->assertSame(OpportunityStatus::Published, $job->refresh()->status);
    }
}
