<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'type' => fake()->randomElement(OrganizationType::cases()),
            'official_email' => fake()->companyEmail(),
            'contact_number' => '09'.fake()->numerify('#########'),
            'office_address' => fake()->streetAddress(),
            'description' => fake()->sentence(12),
            'representative_name' => fake()->name(),
            'representative_position' => 'HR Manager',
            'verification_status' => VerificationStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state([
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state([
            'verification_status' => VerificationStatus::Suspended,
            'status_reason' => 'Reported listings under review.',
        ]);
    }

    /**
     * Add an active member with the given role and return the organization.
     */
    public function withMember(OrganizationRole $role = OrganizationRole::Admin, ?User $user = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($role, $user) {
            $organization->members()->create([
                'user_id' => ($user ?? User::factory()->organization()->create())->id,
                'role' => $role,
                'is_active' => true,
            ]);
        });
    }
}
