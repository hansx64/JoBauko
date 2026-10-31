<?php

namespace Database\Factories;

use App\Enums\Classification;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicantProfile>
 */
class ApplicantProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'classification' => fake()->randomElement(Classification::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-16 years'),
            'gender' => fake()->randomElement(['female', 'male']),
        ];
    }
}
