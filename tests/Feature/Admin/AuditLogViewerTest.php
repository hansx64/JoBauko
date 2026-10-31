<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_browse_and_filter_the_audit_log()
    {
        $admin = User::factory()->admin()->create(['name' => 'Rosa Admin']);
        $organization = Organization::factory()->verified()->create(['name' => 'Bauko Coop']);
        $opportunity = Opportunity::factory()->create(['organization_id' => $organization->id]);
        $seeker = User::factory()->seeker()->create();

        $this->actingAs($admin);
        $audit = app(AuditLogger::class);
        $audit->log('user.deactivated', $seeker, ['is_active' => true], ['is_active' => false, 'reason' => 'Spam']);
        $audit->log('opportunity.suspended', $opportunity, ['status' => 'published'], ['status' => 'suspended'], $organization->id);

        $this->get('/admin/audit-logs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/audit-logs')
                ->has('logs.data', 2)
                ->where('logs.data.0.action_label', 'Suspended an opportunity')
                ->where('logs.data.0.subject.url', "/admin/opportunities/{$opportunity->id}")
                ->where('logs.data.0.organization.name', 'Bauko Coop')
                ->where('logs.data.0.actor.name', 'Rosa Admin')
                ->where('logs.data.1.new_values.reason', 'Spam'));

        $this->get('/admin/audit-logs?category=user')->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'user.deactivated'));
        $this->get('/admin/audit-logs?action=opportunity.suspended')->assertInertia(fn ($page) => $page->has('logs.data', 1));
        $this->get("/admin/audit-logs?organization={$organization->id}")->assertInertia(fn ($page) => $page->has('logs.data', 1));
        $this->get('/admin/audit-logs?actor=rosa')->assertInertia(fn ($page) => $page->has('logs.data', 2));
        $this->get('/admin/audit-logs?actor=nobody')->assertInertia(fn ($page) => $page->has('logs.data', 0));
        $this->get('/admin/audit-logs?from=2000-01-01&to=2000-12-31')->assertInertia(fn ($page) => $page->has('logs.data', 0));
        $this->get('/admin/audit-logs?category=passwords')->assertSessionHasErrors('category');
    }

    public function test_the_log_stays_read_only()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        app(AuditLogger::class)->log('user.viewed', $admin);
        $entry = AuditLog::sole();

        // There is no route that writes to the log.
        $this->put('/admin/audit-logs/'.$entry->id, ['action' => 'changed'])->assertNotFound();
        $this->delete('/admin/audit-logs/'.$entry->id)->assertNotFound();
        $this->assertSame('user.viewed', $entry->refresh()->action);
    }

    public function test_only_admins_see_the_audit_log()
    {
        $this->actingAs(User::factory()->organization()->create())->get('/admin/audit-logs')->assertForbidden();
    }
}
