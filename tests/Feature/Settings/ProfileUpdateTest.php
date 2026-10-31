<?php

namespace Tests\Feature\Settings;

use App\Models\Barangay;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'mobile_number' => '0917 555 0001',
                'barangay_id' => Barangay::where('name', 'Sinto')->value('id'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('09175550001', $user->mobile_number);
        $this->assertSame('Sinto', $user->barangay->name);
    }

    public function test_seekers_must_provide_mobile_number_and_barangay()
    {
        $user = User::factory()->seeker()->create();

        $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => '',
                'barangay_id' => '',
            ])
            ->assertSessionHasErrors(['mobile_number', 'barangay_id']);
    }

    public function test_admins_may_leave_mobile_number_and_barangay_empty()
    {
        $user = User::factory()->admin()->create();

        $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Admin',
                'email' => $user->email,
                'mobile_number' => '',
                'barangay_id' => '',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'barangay_id' => Barangay::value('id'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
