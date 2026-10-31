<?php

namespace Tests\Feature\Public;

use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseOpportunitiesTest extends TestCase
{
    use RefreshDatabase;

    private function titles($response): array
    {
        return collect($response->viewData('page')['props']['opportunities']['data'])->pluck('title')->sort()->values()->all();
    }

    public function test_the_landing_page_shows_real_open_opportunities()
    {
        Opportunity::factory()->job()->published()->create(['title' => 'Open job']);
        Opportunity::factory()->job()->create(['title' => 'Draft job']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('welcome')
                ->has('latest', 1)
                ->where('latest.0.title', 'Open job')
                ->where('totals.open', 1));
    }

    public function test_only_public_opportunities_are_listed()
    {
        $pending = Organization::factory()->create();
        $suspended = Organization::factory()->suspended()->create();

        Opportunity::factory()->job()->published()->create(['title' => 'Visible']);
        Opportunity::factory()->job()->create(['title' => 'Draft']);
        Opportunity::factory()->job()->published()->create(['title' => 'Archived', 'status' => OpportunityStatus::Archived]);
        Opportunity::factory()->job()->published()->create(['title' => 'Pending org', 'organization_id' => $pending->id]);
        Opportunity::factory()->job()->published()->create(['title' => 'Suspended org', 'organization_id' => $suspended->id]);
        Opportunity::factory()->job()->expired()->create(['title' => 'Expired']);
        Opportunity::factory()->job()->closed()->create(['title' => 'Closed']);

        $this->assertSame(['Visible'], $this->titles($this->get('/opportunities')));
        $this->assertSame(['Closed', 'Expired', 'Visible'], $this->titles($this->get('/opportunities?status=all')));
    }

    public function test_search_matches_title_organization_and_skills()
    {
        $org = Organization::factory()->verified()->create(['name' => 'Highland Growers Cooperative']);
        Opportunity::factory()->job()->published()->create(['title' => 'Farm Supervisor', 'organization_id' => $org->id]);
        $weaver = Opportunity::factory()->job()->published()->create(['title' => 'Production Staff']);
        $weaver->skills()->attach(Skill::findOrCreateByName('Weaving')->id);
        Opportunity::factory()->job()->published()->create(['title' => 'Bookkeeper']);

        $this->assertSame(['Bookkeeper'], $this->titles($this->get('/opportunities?q=bookkeeper')));
        $this->assertSame(['Farm Supervisor'], $this->titles($this->get('/opportunities?q=Highland')));
        $this->assertSame(['Production Staff'], $this->titles($this->get('/opportunities?q=weaving')));
    }

    public function test_filters_by_category_education_and_salary()
    {
        $job = Opportunity::factory()->published()->create(['title' => 'Well-paid job', 'min_education' => EducationLevel::CollegeGraduate]);
        $job->jobDetail()->create(['employment_type' => EmploymentType::FullTime, 'salary_min' => 20000, 'salary_max' => 25000]);
        $low = Opportunity::factory()->published()->create(['title' => 'Entry job', 'min_education' => EducationLevel::JuniorHigh]);
        $low->jobDetail()->create(['employment_type' => EmploymentType::PartTime, 'salary_min' => 9000]);
        $scholarship = Opportunity::factory()->published()->create(['title' => 'Scholarship', 'category' => OpportunityCategory::Scholarship]);
        $scholarship->scholarshipDetail()->create(['education_level' => EducationLevel::CollegeLevel]);

        $this->assertSame(['Scholarship'], $this->titles($this->get('/opportunities?category=scholarship')));
        $this->assertSame(['Entry job', 'Scholarship'], $this->titles($this->get('/opportunities?education=senior_high')));
        $this->assertSame(['Well-paid job'], $this->titles($this->get('/opportunities?salary_min=15000')));
        $this->assertSame(['Entry job'], $this->titles($this->get('/opportunities?employment_type=part_time')));
    }

    public function test_details_page_is_public_for_visible_opportunities_only()
    {
        $visible = Opportunity::factory()->job()->published()->create();
        $draft = Opportunity::factory()->job()->create();

        $this->get("/opportunities/{$visible->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('opportunities/show')->where('opportunity.title', $visible->title)->where('opportunity.match', null));

        $this->get("/opportunities/{$draft->slug}")->assertNotFound();
    }

    public function test_closed_opportunities_show_that_applications_are_not_accepted()
    {
        $closed = Opportunity::factory()->job()->closed()->create();

        $this->get("/opportunities/{$closed->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('opportunity.accepting', false)->where('opportunity.status', 'closed'));
    }

    public function test_seekers_see_an_explained_match_on_the_details_page()
    {
        $seeker = User::factory()->seeker()->create();
        $opportunity = Opportunity::factory()->job()->published()->create(['is_remote' => true]);

        $this->actingAs($seeker)
            ->get("/opportunities/{$opportunity->slug}")
            ->assertInertia(fn ($page) => $page
                ->has('opportunity.match.score')
                ->where('opportunity.match.reasons', fn ($reasons) => collect($reasons)->contains('Can be done remotely')));
    }
}
