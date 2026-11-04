<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\EducationLevel;
use App\Models\ApplicantProfile;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'mobile_number' => '09'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_type' => AccountType::Seeker,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A seeker with an applicant profile.
     */
    public function seeker(): static
    {
        return $this->state(['account_type' => AccountType::Seeker])
            ->afterCreating(function (User $user) {
                if (! $user->applicantProfile()->exists()) {
                    ApplicantProfile::factory()->for($user)->create();
                }
            });
    }

    /**
     * A seeker whose profile has everything required to apply
     * (ProfileCompletion::REQUIRED_TO_APPLY): personal details, education,
     * a skill and a current resume.
     */
    public function readyToApply(): static
    {
        return $this->seeker()->afterCreating(function (User $user) {
            $profile = $user->applicantProfile()->firstOrFail();
            $profile->update([
                'date_of_birth' => $profile->date_of_birth ?? '2000-01-15',
                'gender' => $profile->gender ?? 'female',
                'address_line' => $profile->address_line ?: 'Poblacion, Bauko',
                'highest_education' => $profile->highest_education ?? EducationLevel::CollegeGraduate,
            ]);

            if (! $profile->education()->exists()) {
                $profile->education()->create(['level' => EducationLevel::CollegeGraduate, 'school' => 'Mountain Province State University', 'course' => 'BS Information Technology']);
            }
            if (! $profile->skills()->exists()) {
                $profile->skills()->attach(Skill::findOrCreateByName('Customer Service'), ['source' => 'manual']);
            }
            if ($user->currentResume()->doesntExist()) {
                Resume::factory()->for($user)->create();
            }
        });
    }

    public function admin(): static
    {
        return $this->state([
            'account_type' => AccountType::Admin,
            'mobile_number' => null,
        ]);
    }

    public function organization(): static
    {
        return $this->state(['account_type' => AccountType::Organization]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
