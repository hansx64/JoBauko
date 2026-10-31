<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_seekers_can_visit_the_dashboard()
    {
        $this->actingAs(User::factory()->seeker()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard'));
    }

    public function test_unverified_email_is_sent_to_the_verification_notice()
    {
        $this->actingAs(User::factory()->seeker()->unverified()->create());

        $this->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_admins_are_redirected_to_the_admin_dashboard()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/dashboard')->assertRedirect('/admin/dashboard');
    }
}
