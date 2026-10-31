<?php

namespace Tests\Feature\Admin;

use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Enums\ReportStatus;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Notifications\OpportunityModerated;
use App\Notifications\ReportReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seeker_can_report_a_listing_once()
    {
        $seeker = User::factory()->seeker()->create();
        $job = Opportunity::factory()->job()->published()->create();

        $this->actingAs($seeker)
            ->get("/opportunities/{$job->slug}")
            ->assertInertia(fn ($page) => $page->where('reporting.alreadyReported', false)->has('reporting.reasons', 7));

        $this->post("/opportunities/{$job->slug}/report", ['reason' => 'fees', 'details' => 'They asked for a processing fee.'])
            ->assertSessionHasNoErrors();

        $report = Report::sole();
        $this->assertSame($seeker->id, $report->reporter_id);
        $this->assertTrue($report->reportable->is($job));
        $this->assertSame(ReportStatus::Open, $report->status);
        // The organization is not told who reported.
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.submitted', 'organization_id' => null]);

        $this->post("/opportunities/{$job->slug}/report", ['reason' => 'scam'])->assertSessionHasErrors('reason');
        $this->get("/opportunities/{$job->slug}")->assertInertia(fn ($page) => $page->where('reporting.alreadyReported', true));
    }

    public function test_vague_reasons_need_details()
    {
        $seeker = User::factory()->seeker()->create();
        $job = Opportunity::factory()->job()->published()->create();

        $this->actingAs($seeker)->post("/opportunities/{$job->slug}/report", ['reason' => 'other'])->assertSessionHasErrors('details');
        $this->post("/opportunities/{$job->slug}/report", ['reason' => 'not-a-reason'])->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_who_can_report()
    {
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $own = Opportunity::factory()->job()->published()->for($organization)->create();
        $draft = Opportunity::factory()->create();

        $this->post("/opportunities/{$own->slug}/report", ['reason' => 'fees'])->assertRedirect(route('login'));

        $this->actingAs($staff)->get("/opportunities/{$own->slug}")->assertInertia(fn ($page) => $page->where('reporting', null));
        $this->post("/opportunities/{$own->slug}/report", ['reason' => 'fees'])->assertSessionHasErrors('reason');

        $this->actingAs(User::factory()->admin()->create())->post("/opportunities/{$own->slug}/report", ['reason' => 'fees'])->assertForbidden();
        $this->actingAs(User::factory()->seeker()->create())->post("/opportunities/{$draft->slug}/report", ['reason' => 'fees'])->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_the_queue_lists_open_reports_oldest_first()
    {
        $admin = User::factory()->admin()->create();
        $older = Report::factory()->create(['created_at' => now()->subDays(2)]);
        Report::factory()->create();
        Report::factory()->create(['status' => ReportStatus::Dismissed]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/index')
                ->has('reports.data', 2)
                ->where('reports.data.0.id', $older->id)
                ->where('counts.dismissed', 1));

        $this->get('/admin/reports?status=dismissed')->assertInertia(fn ($page) => $page->has('reports.data', 1));
    }

    public function test_suspending_from_a_report_closes_all_open_reports_on_the_listing()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->organization()->create();
        $organization = Organization::factory()->verified()->withMember(OrganizationRole::Admin, $staff)->create();
        $job = Opportunity::factory()->job()->published()->for($organization)->create();
        $first = Report::factory()->against($job)->create();
        $second = Report::factory()->against($job)->create(['reason' => 'scam']);
        $elsewhere = Report::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/reports/{$first->id}")
            ->assertInertia(fn ($page) => $page
                ->component('admin/reports/show')
                ->where('opportunity.can_suspend', true)
                ->has('related', 1));

        $this->post("/admin/reports/{$first->id}/decision", ['decision' => 'suspend', 'notes' => 'Confirmed: they charge a placement fee.'])
            ->assertRedirect(route('admin.reports.index'));

        $this->assertSame(OpportunityStatus::Suspended, $job->refresh()->status);
        $this->assertSame('Confirmed: they charge a placement fee.', $job->suspension_reason);

        foreach ([$first, $second] as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::ActionTaken, $report->status);
            $this->assertSame('opportunity_suspended', $report->outcome);
            $this->assertSame($admin->id, $report->resolved_by);
        }
        $this->assertSame(ReportStatus::Open, $elsewhere->refresh()->status);

        Notification::assertSentTo([$first->reporter, $second->reporter], ReportReviewed::class);
        Notification::assertSentTo($staff, OpportunityModerated::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.reviewed', 'auditable_id' => $first->id]);
    }

    public function test_reports_can_be_dismissed_without_touching_the_listing()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $report = Report::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/reports/{$report->id}/decision", ['decision' => 'dismiss', 'notes' => 'Fee is a legitimate TESDA assessment fee.'])
            ->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame(ReportStatus::Dismissed, $report->status);
        $this->assertSame(OpportunityStatus::Published, $report->reportable->status);
        Notification::assertSentTo($report->reporter, ReportReviewed::class, fn ($n, $channels, $notifiable) => ! str_contains($n->toArray($notifiable)['message'], 'TESDA'));

        $this->post("/admin/reports/{$report->id}/decision", ['decision' => 'resolve', 'notes' => 'Trying to decide twice.'])->assertSessionHasErrors('decision');
    }

    public function test_a_suspended_listing_cannot_be_suspended_again_from_a_report()
    {
        $admin = User::factory()->admin()->create();
        $job = Opportunity::factory()->job()->create(['status' => OpportunityStatus::Suspended, 'status_before_suspension' => OpportunityStatus::Published]);
        $report = Report::factory()->against($job)->create();

        $this->actingAs($admin)
            ->post("/admin/reports/{$report->id}/decision", ['decision' => 'suspend', 'notes' => 'Already suspended listing.'])
            ->assertSessionHasErrors('decision');

        $this->post("/admin/reports/{$report->id}/decision", ['decision' => 'resolve', 'notes' => 'Already suspended separately.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->refresh()->outcome);
    }
}
