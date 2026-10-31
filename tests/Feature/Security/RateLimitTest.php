<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['jobauko.requests_per_minute' => 3]);
    }

    public function test_visitors_are_limited_per_ip_address()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->get('/privacy')->assertOk();
        }

        $this->get('/privacy')->assertStatus(429);
    }

    public function test_signed_in_users_have_their_own_limit()
    {
        $first = User::factory()->seeker()->create();
        $second = User::factory()->seeker()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($first)->get('/privacy')->assertOk();
        }
        $this->actingAs($first)->get('/privacy')->assertStatus(429);

        $this->actingAs($second)->get('/privacy')->assertOk();
    }

    public function test_in_app_navigation_gets_a_message_instead_of_an_error_page()
    {
        $user = User::factory()->seeker()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->get('/privacy');
        }

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/privacy', ['X-Inertia' => 'true'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }

    public function test_sign_in_keeps_its_own_stricter_limit()
    {
        $user = User::factory()->seeker()->create();
        config(['jobauko.requests_per_minute' => 100]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
