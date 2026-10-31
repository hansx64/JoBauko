<?php

namespace Tests\Feature\Seeker;

use App\Enums\Classification;
use App\Enums\EmploymentType;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Services\Opportunities\OpportunityService;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecommendationsAndSavedTest extends TestCase
{
    use RefreshDatabase;

    private function weavingJob(): Opportunity
    {
        $job = Opportunity::factory()->published()->create(['title' => 'Loom Weaver', 'is_remote' => false]);
        $job->jobDetail()->create(['employment_type' => EmploymentType::FullTime]);
        $job->skills()->attach(Skill::findOrCreateByName('Weaving')->id);

        return $job;
    }

    public function test_the_dashboard_recommends_matching_opportunities_with_reasons()
    {
        $this->seed(SkillSeeder::class);
        $seeker = User::factory()->seeker()->create();
        $seeker->applicantProfile->update(['classification' => Classification::Unemployed]);
        $seeker->applicantProfile->skills()->attach(Skill::where('name', 'Weaving')->value('id'));
        $job = $this->weavingJob();

        $this->actingAs($seeker)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('recommended', 1)
                ->where('recommended.0.id', $job->id)
                ->where('recommended.0.match.reasons', fn ($reasons) => collect($reasons)->contains('You have 1 of 1 listed skills: Weaving')));
    }

    public function test_recommendations_update_after_the_profile_changes()
    {
        $this->seed(SkillSeeder::class);
        $seeker = User::factory()->seeker()->create();
        $seeker->applicantProfile->update(['classification' => Classification::Student]);
        $job = $this->weavingJob();
        $this->actingAs($seeker);

        $this->get('/dashboard');
        $before = DB::table('opportunity_matches')->where('user_id', $seeker->id)->value('score');

        $this->put('/profile/skills', ['skills' => ['Weaving']])->assertSessionHasNoErrors();
        $this->get('/dashboard');
        $after = DB::table('opportunity_matches')->where(['user_id' => $seeker->id, 'opportunity_id' => $job->id])->value('score');

        $this->assertGreaterThan($before, $after);
    }

    public function test_newly_published_opportunities_are_scored()
    {
        $seeker = User::factory()->seeker()->create();
        $this->actingAs($seeker)->get('/dashboard');
        $this->assertSame(0, DB::table('opportunity_matches')->where('user_id', $seeker->id)->count());

        $draft = Opportunity::factory()->job()->create();
        app(OpportunityService::class)->publish($draft);

        $this->get('/dashboard');
        $this->assertSame(1, DB::table('opportunity_matches')->where('user_id', $seeker->id)->count());
    }

    public function test_browse_can_sort_by_best_match_for_seekers()
    {
        $this->seed(SkillSeeder::class);
        $seeker = User::factory()->seeker()->create();
        $seeker->applicantProfile->skills()->attach(Skill::where('name', 'Weaving')->value('id'));
        $other = Opportunity::factory()->job()->published()->create(['title' => 'Unrelated', 'published_at' => now()]);
        $other->skills()->attach(Skill::findOrCreateByName('Welding')->id);
        $this->weavingJob()->update(['published_at' => now()->subWeek()]);

        $this->actingAs($seeker)
            ->get('/opportunities?sort=match')
            ->assertInertia(fn ($page) => $page->where('opportunities.data.0.title', 'Loom Weaver')->has('opportunities.data.0.match.score'));
    }

    public function test_seekers_can_save_and_unsave_opportunities()
    {
        $seeker = User::factory()->seeker()->create();
        $opportunity = Opportunity::factory()->job()->published()->create();
        $this->actingAs($seeker);

        $this->post("/opportunities/{$opportunity->id}/save")->assertSessionHasNoErrors();
        $this->post("/opportunities/{$opportunity->id}/save");
        $this->assertSame(1, $seeker->savedOpportunities()->count());

        $this->get('/saved')->assertInertia(fn ($page) => $page->component('seeker/saved')->has('opportunities', 1));

        $this->delete("/opportunities/{$opportunity->id}/save");
        $this->assertSame(0, $seeker->savedOpportunities()->count());
    }

    public function test_hidden_opportunities_cannot_be_saved()
    {
        $draft = Opportunity::factory()->job()->create();

        $this->actingAs(User::factory()->seeker()->create())
            ->post("/opportunities/{$draft->id}/save")
            ->assertNotFound();
    }

    public function test_only_seekers_can_save()
    {
        $opportunity = Opportunity::factory()->job()->published()->create();

        $this->actingAs(User::factory()->admin()->create())->post("/opportunities/{$opportunity->id}/save")->assertForbidden();
        auth()->guard('web')->logout();
        $this->post("/opportunities/{$opportunity->id}/save")->assertRedirect('/login');
    }
}
