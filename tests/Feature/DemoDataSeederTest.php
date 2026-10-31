<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_can_be_seeded_repeatedly_without_duplicates()
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(6, Opportunity::query()->visible()->open()->count());
        $this->assertSame(3, User::where('email', 'like', 'demo.%@jobauko.test')->count());
        $this->assertSame(2, Application::count());

        $application = Application::whereHas('user', fn ($q) => $q->where('email', 'demo.seeker@jobauko.test'))->sole();
        $this->assertSame(ApplicationStatus::UnderReview, $application->status);
        $this->assertGreaterThan(50, $application->match_score);
        $this->assertCount(2, $application->statusHistory);

        // Both demo applications go to the municipal office's listings.
        $organization = User::where('email', 'demo.org@jobauko.test')->sole();
        $this->actingAs($organization)
            ->get('/org/dashboard')
            ->assertInertia(fn ($page) => $page->where('applications.total', 2)->where('applications.awaitingReview', 1));
    }
}
