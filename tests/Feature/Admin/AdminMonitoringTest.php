<?php

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\Classification;
use App\Models\Application;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_platform_figures()
    {
        $this->seed(LocationSeeder::class);
        $admin = User::factory()->admin()->create();
        $barangayId = Barangay::orderBy('id')->value('id');
        $seeker = User::factory()->seeker()->create(['barangay_id' => $barangayId]);
        $seeker->applicantProfile->update(['classification' => Classification::Student]);
        Organization::factory()->create();
        $job = Opportunity::factory()->job()->published()->create();
        Application::factory()->for($job)->for($seeker)->create();
        Report::factory()->against($job)->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/dashboard')
                ->where('stats.openOpportunities', 1)
                ->where('stats.applications', 1)
                ->where('stats.verifiedOrganizations', 1)
                ->where('attention.pendingOrganizations', 1)
                ->where('attention.openReports', 1)
                ->where('attention.stalledApplications', 0)
                ->has('registrationsPerDay', 30)
                ->has('applicationsPerDay', 30)
                ->where('applicationsPerDay.29.count', 1)
                ->where('openByCategory.0', ['key' => 'job', 'label' => 'Job', 'value' => 1])
                ->where('applicationsByStatus.0.key', 'submitted')
                ->where('seekersByBarangay', fn ($rows) => count($rows) >= 22 && collect($rows)->firstWhere('key', (string) $barangayId)['value'] >= 1)
                ->where('classifications', fn ($rows) => collect($rows)->firstWhere('key', 'student')['value'] >= 1));
    }

    public function test_applications_can_be_monitored_and_stalled_ones_found()
    {
        $admin = User::factory()->admin()->create();
        $fresh = Application::factory()->create();
        $stalled = Application::factory()->create(['submitted_at' => now()->subDays(20)]);
        $stalled->statusHistory()->update(['created_at' => now()->subDays(20)]);
        $decided = Application::factory()->status(ApplicationStatus::Approved)->create();
        $decided->statusHistory()->update(['created_at' => now()->subDays(30)]);

        $this->actingAs($admin)
            ->get('/admin/applications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/applications/index')
                ->has('applications.data', 3)
                ->where('counts.stalled', 1)
                ->where('counts.active', 2)
                ->missing('applications.data.0.cover_letter'));

        $this->get('/admin/applications?status=stalled')
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 1)
                ->where('applications.data.0.id', $stalled->id)
                ->where('applications.data.0.stalled', true));

        $this->get('/admin/applications?q='.urlencode($fresh->user->name))
            ->assertInertia(fn ($page) => $page->where('applications.data.0.id', $fresh->id));

        $this->get("/admin/applications?organization={$fresh->opportunity->organization_id}")
            ->assertInertia(fn ($page) => $page->has('applications.data', 1));
    }

    public function test_monitoring_pages_are_admin_only()
    {
        $seeker = User::factory()->seeker()->create();

        foreach (['/admin/applications', '/admin/opportunities', '/admin/reports', '/admin/users'] as $url) {
            $this->actingAs($seeker)->get($url)->assertForbidden();
        }
    }
}
