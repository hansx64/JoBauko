<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory()->job()->published(),
            'user_id' => User::factory()->seeker(),
            'status' => ApplicationStatus::Submitted,
            'data_sharing_consented_at' => now(),
            'submitted_at' => now(),
        ];
    }

    /**
     * Give every application the status history it would have had.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Application $application) {
            $application->statusHistory()->create(['to_status' => ApplicationStatus::Submitted, 'changed_by' => $application->user_id]);

            if ($application->status !== ApplicationStatus::Submitted) {
                $application->statusHistory()->create(['from_status' => ApplicationStatus::Submitted, 'to_status' => $application->status]);
            }
        });
    }

    public function status(ApplicationStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'first_reviewed_at' => $status === ApplicationStatus::Submitted ? null : now(),
            'decided_at' => $status->isDecision() ? now() : null,
            'withdrawn_at' => $status === ApplicationStatus::Withdrawn ? now() : null,
        ]);
    }
}
