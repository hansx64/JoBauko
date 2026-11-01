<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\TrainingType;
use App\Enums\WorkSetup;
use App\Models\Opportunity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'organization_id' => Organization::factory()->verified(),
            'category' => OpportunityCategory::Job,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'location_text' => 'Bauko, Mountain Province',
            'application_deadline' => now()->addMonth()->toDateString(),
            'status' => OpportunityStatus::Draft,
        ];
    }

    /**
     * A job with its details row, ready to publish.
     */
    public function job(): static
    {
        return $this->state(['category' => OpportunityCategory::Job])
            ->afterCreating(fn (Opportunity $o) => $o->jobDetail()->create([
                'employment_type' => EmploymentType::FullTime,
                'work_setup' => WorkSetup::Onsite,
            ]));
    }

    /**
     * A training with its details row, ready to publish.
     */
    public function training(): static
    {
        return $this->state(['category' => OpportunityCategory::Training])
            ->afterCreating(fn (Opportunity $o) => $o->trainingDetail()->create([
                'training_type' => TrainingType::SkillsTraining,
                'topics' => 'Hands-on practice of the skills listed.',
            ]));
    }

    public function published(): static
    {
        return $this->state(['status' => OpportunityStatus::Published, 'published_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(['status' => OpportunityStatus::Closed, 'published_at' => now()->subWeek()]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => OpportunityStatus::Published,
            'published_at' => now()->subMonths(2),
            'application_deadline' => now()->subDay()->toDateString(),
        ]);
    }
}
