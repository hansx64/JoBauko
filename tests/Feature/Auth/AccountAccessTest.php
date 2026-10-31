<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_admin_dashboard()
    {
        User::factory()->seeker()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/dashboard')
                ->where('stats.totalUsers', 3)
                ->where('stats.seekers', 2));
    }

    public function test_seekers_cannot_open_the_admin_area()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_organization_accounts_cannot_open_the_admin_area()
    {
        $this->actingAs(User::factory()->organization()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_deactivated_accounts_cannot_log_in()
    {
        $user = User::factory()->seeker()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_accounts_deactivated_mid_session_are_logged_out()
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_records_last_login_time()
    {
        $user = User::factory()->seeker()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_shared_user_props_do_not_expose_sensitive_fields()
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->where('auth.user.account_type', 'seeker'));
    }
}
