<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_pages_are_not_cached_and_their_history_is_encrypted()
    {
        $seeker = User::factory()->seeker()->create();

        $response = $this->actingAs($seeker)->get('/dashboard');

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
        $this->assertTrue($response->viewData('page')['encryptHistory']);
    }

    public function test_public_pages_for_visitors_may_be_cached_and_are_not_encrypted()
    {
        $response = $this->get('/');

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse($response->viewData('page')['encryptHistory']);
    }

    public function test_signing_out_clears_the_browser_history_and_protects_the_dashboard()
    {
        $seeker = User::factory()->seeker()->create();

        $this->actingAs($seeker)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertTrue($this->get('/')->viewData('page')['clearHistory']);
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_the_back_button_recheck_sends_a_signed_out_user_to_sign_in()
    {
        $seeker = User::factory()->seeker()->create();
        $this->actingAs($seeker)->get('/dashboard')->assertOk();
        $this->post('/logout');

        // The partial reload the app sends when Back restores a signed-in page.
        $this->get('/dashboard', ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'auth', 'X-Inertia-Partial-Component' => 'dashboard'])
            ->assertRedirect(route('login'));
    }

    public function test_changing_the_password_signs_out_other_devices()
    {
        $user = User::factory()->seeker()->create();
        DB::table('sessions')->insert([
            ['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $token = $user->remember_token;

        $this->actingAs($user)
            ->put('/settings/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertNotSame($token, $user->refresh()->remember_token);
    }

    public function test_an_expired_session_asks_the_visitor_to_sign_in_again()
    {
        Route::middleware('web')->post('/_expired', fn () => throw new TokenMismatchException);

        $this->post('/_expired')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session has ended. Please sign in again.');
    }

    public function test_a_stale_form_for_a_signed_in_user_returns_with_a_message()
    {
        Route::middleware('web')->post('/_expired', fn () => throw new TokenMismatchException);

        $this->actingAs(User::factory()->seeker()->create())
            ->from('/dashboard')
            ->post('/_expired')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }
}
