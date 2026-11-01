<?php

namespace Tests\Feature\Organization;

use App\Enums\Classification;
use App\Enums\EmploymentType;
use App\Enums\InterestCategory;
use App\Enums\NotificationTopic;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Enums\TrainingType;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOpportunityMatch;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingOpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $organization = Organization::factory()->verified()->create();
        $user = User::factory()->organization()->create();
        $organization->members()->create(['user_id' => $user->id, 'role' => OrganizationRole::Admin, 'is_active' => true]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function trainingData(array $overrides = [], array $training = []): array
    {
        return [
            'category' => 'training',
            'title' => 'Shielded Metal Arc Welding NC I Training',
            'description' => 'A free welding course for Bauko residents.',
            'location_text' => 'Municipal Training Center, Poblacion',
            'application_deadline' => now()->addWeeks(2)->toDateString(),
            'slots' => 25,
            'training' => [
                'training_type' => 'skills_training',
                'starts_on' => now()->addWeeks(3)->toDateString(),
                'ends_on' => now()->addWeeks(6)->toDateString(),
                'schedule' => 'Monday to Friday, 8:00 AM – 5:00 PM',
                'duration_hours' => 120,
                'fee' => null,
                'certificate' => 'TESDA National Certificate I',
                'topics' => "Welding safety\nFillet welds",
                'inclusions' => 'Protective gear and meals.',
                'target_participants' => 'Out-of-school youth and unemployed residents.',
                'target_classifications' => ['out_of_school_youth'],
                ...$training,
            ],
            ...$overrides,
        ];
    }

    private function seekerWithInterest(Classification $classification, InterestCategory $interest): User
    {
        $user = User::factory()->seeker()->create();
        $user->applicantProfile->update(['classification' => $classification]);
        $user->applicantProfile->interests()->create(['category' => $interest]);

        return $user->fresh();
    }

    private function score(User $user, Opportunity $opportunity)
    {
        return app(MatchScorer::class)->score(
            SeekerSnapshot::fromUser($user),
            $opportunity->load(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail', 'trainingDetail']),
        );
    }

    public function test_an_organization_can_publish_a_training()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->trainingData(['intent' => 'publish']))
            ->assertRedirect('/org/opportunities');

        $opportunity = Opportunity::sole();
        $this->assertSame(OpportunityCategory::Training, $opportunity->category);
        $this->assertSame(OpportunityStatus::Published, $opportunity->status);
        $this->assertSame(TrainingType::SkillsTraining, $opportunity->trainingDetail->training_type);
        $this->assertSame(120, $opportunity->trainingDetail->duration_hours);
        $this->assertSame(['out_of_school_youth'], $opportunity->trainingDetail->target_classifications);
        $this->assertNull($opportunity->livelihoodDetail);
    }

    public function test_publishing_requires_the_type_and_what_participants_will_learn()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->trainingData(['intent' => 'publish'], ['training_type' => null, 'topics' => null]))
            ->assertSessionHasErrors(['training.training_type', 'training.topics']);

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_a_training_cannot_end_before_it_starts()
    {
        $this->actingAs($this->member())
            ->post('/org/opportunities', $this->trainingData([], ['starts_on' => now()->addWeeks(4)->toDateString(), 'ends_on' => now()->addWeeks(3)->toDateString()]))
            ->assertSessionHasErrors('training.ends_on');
    }

    public function test_the_form_offers_training_types()
    {
        $this->actingAs($this->member())
            ->get('/org/opportunities/create')
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.trainingTypes', count(TrainingType::cases()))
                ->where('options.categories', fn ($categories) => collect($categories)->pluck('value')->contains('training')));
    }

    public function test_the_details_page_shows_the_training_details()
    {
        $training = Opportunity::factory()->training()->published()->create();
        $training->trainingDetail->update([
            'starts_on' => '2027-03-01',
            'ends_on' => '2027-03-26',
            'duration_hours' => 120,
            'certificate' => 'TESDA National Certificate I',
        ]);

        $this->get("/opportunities/{$training->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('opportunity.category', 'training')
                ->where('opportunity.category_label', 'Training / Seminar')
                ->where('opportunity.training.training_type', 'Skills training')
                ->where('opportunity.training.dates', 'Mar 1 – Mar 26, 2027')
                ->where('opportunity.training.fee', 'Free')
                ->where('opportunity.training.duration_hours', 120)
                ->where('opportunity.training.certificate', 'TESDA National Certificate I'));
    }

    public function test_fees_are_shown_in_pesos()
    {
        $training = Opportunity::factory()->training()->published()->create();
        $training->trainingDetail->update(['fee' => 1500]);

        $this->assertSame('₱1,500', $training->trainingDetail->fresh()->feeLabel());

        $training->trainingDetail->update(['fee' => 250.5]);
        $this->assertSame('₱250.50', $training->trainingDetail->fresh()->feeLabel());
    }

    public function test_trainings_have_their_own_browse_filter()
    {
        Opportunity::factory()->job()->published()->create(['title' => 'On-the-job training assistant']);
        Opportunity::factory()->training()->published()->create(['title' => 'Bookkeeping seminar']);

        $this->get('/opportunities?category=training')
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.title', 'Bookkeeping seminar'));
    }

    public function test_the_training_interest_matches_trainings_only()
    {
        $seeker = $this->seekerWithInterest(Classification::Unemployed, InterestCategory::Training);
        $training = Opportunity::factory()->training()->published()->create(['title' => 'Bookkeeping seminar']);
        $job = Opportunity::factory()->published()->create(['title' => 'On-the-job training assistant']);
        $job->jobDetail()->create(['employment_type' => EmploymentType::FullTime]);

        $this->assertContains("You're interested in Training programs", $this->score($seeker, $training)->reasons);
        // A job with "training" in its title is not a training.
        $this->assertNotContains("You're interested in Training programs", $this->score($seeker, $job)->reasons);
    }

    public function test_trainings_favor_their_target_groups()
    {
        $training = Opportunity::factory()->training()->published()->create();
        $training->trainingDetail->update(['target_classifications' => [Classification::OutOfSchoolYouth->value]]);

        $youth = $this->seekerWithInterest(Classification::OutOfSchoolYouth, InterestCategory::Job);
        $employed = $this->seekerWithInterest(Classification::Employed, InterestCategory::Job);

        $this->assertContains('This training is meant for Out-of-School Youth residents', $this->score($youth, $training)->reasons);
        $this->assertGreaterThan($this->score($employed, $training)->score, $this->score($youth, $training)->score);
    }

    public function test_training_alerts_use_the_training_programs_topic()
    {
        $training = Opportunity::factory()->training()->published()->create();

        $this->assertSame(NotificationTopic::Training, NewOpportunityMatch::topicFor($training));
    }
}
