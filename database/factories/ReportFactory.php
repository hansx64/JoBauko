<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\Opportunity;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory()->seeker(),
            'reportable_type' => (new Opportunity)->getMorphClass(),
            'reportable_id' => Opportunity::factory()->job()->published(),
            'reason' => ReportReason::Fees,
            'details' => fake()->sentence(),
            'status' => 'open',
        ];
    }

    public function against(Opportunity $opportunity): static
    {
        return $this->state(['reportable_type' => $opportunity->getMorphClass(), 'reportable_id' => $opportunity->id]);
    }
}
