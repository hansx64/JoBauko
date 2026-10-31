<?php

namespace Tests\Feature\Security;

use App\Models\Resume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers_and_a_nonce_based_policy()
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);

        // The inline route script carries the same nonce the policy allows.
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([^']+)'/", $policy);
        preg_match("/'nonce-([^']+)'/", $policy, $match);
        $this->assertSame(Vite::cspNonce(), $match[1]);
        $this->assertStringContainsString('nonce="'.$match[1].'"', $response->getContent());
    }

    public function test_file_responses_keep_the_browser_viewer_working()
    {
        Storage::fake('local');
        $resume = Resume::factory()->create();
        Storage::disk('local')->put($resume->disk_path, '%PDF-1.4');

        $response = $this->actingAs($resume->user)->get(route('seeker.resume.file', ['resume' => $resume->id]));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_hsts_is_only_sent_over_https_in_production()
    {
        $this->assertFalse($this->get('/')->headers->has('Strict-Transport-Security'));

        $this->app['env'] = 'production';
        $this->assertTrue($this->get('https://localhost/')->headers->has('Strict-Transport-Security'));
    }
}
