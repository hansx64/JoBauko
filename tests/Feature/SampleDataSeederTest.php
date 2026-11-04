<?php

namespace Tests\Feature;

use App\Enums\OpportunityCategory;
use App\Jobs\NotifyMatchingSeekers;
use App\Models\Opportunity;
use App\Models\Organization;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_data_can_be_seeded_repeatedly_without_duplicates_or_alerts()
    {
        Queue::fake();

        $this->seed(SampleDataSeeder::class);
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(4, Organization::verified()->count());
        foreach (OpportunityCategory::cases() as $category) {
            $this->assertSame(3, Opportunity::query()->visible()->open()->where('category', $category)->count(), $category->value);
        }
        $this->assertSame(0, Opportunity::where('title', 'like', '%demo%')->count());
        Queue::assertNotPushed(NotifyMatchingSeekers::class);

        $this->get('/opportunities')->assertOk();
        foreach (Opportunity::all() as $opportunity) {
            $this->get(route('opportunities.show', $opportunity))->assertOk();
        }
    }
}
