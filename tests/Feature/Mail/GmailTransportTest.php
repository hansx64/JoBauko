<?php

namespace Tests\Feature\Mail;

use App\Services\Mail\GmailTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class GmailTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'gmail',
            'mail.mailers.gmail.client_id' => 'client-id',
            'mail.mailers.gmail.client_secret' => 'client-secret',
            'mail.mailers.gmail.refresh_token' => 'refresh-token',
            'mail.from.address' => 'jobauko@gmail.example',
        ]);
    }

    private function decodedRaw(Request $request): string
    {
        return base64_decode(strtr($request['raw'], '-_', '+/'));
    }

    public function test_mail_is_sent_through_the_gmail_api_with_a_cached_access_token()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'gmail-message-1']),
        ]);

        Mail::raw('Your application was received.', fn ($m) => $m->to('seeker@example.test')->bcc('records@example.test')->subject('Application received'));
        Mail::raw('Second message.', fn ($m) => $m->to('other@example.test')->subject('Second'));

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'refresh-token');

        $sends = Http::recorded(fn (Request $r) => str_contains($r->url(), 'gmail.googleapis.com'));
        $this->assertCount(2, $sends);

        [$first] = $sends->first();
        $this->assertSame('Bearer access-1', $first->header('Authorization')[0]);
        $raw = $this->decodedRaw($first);
        $this->assertStringContainsString('Subject: Application received', $raw);
        $this->assertStringContainsString('To: seeker@example.test', $raw);
        $this->assertStringStartsWith('Bcc: records@example.test', $raw);

        // The token is kept encrypted, never as plain text.
        $this->assertStringNotContainsString('access-1', (string) cache()->get('gmail-access-token:'.sha1('client-id|refresh-token')));
    }

    public function test_a_revoked_sign_in_explains_how_to_reconnect_without_revealing_secrets()
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            Mail::raw('Hello', fn ($m) => $m->to('seeker@example.test')->subject('Hi'));
            $this->fail('Sending should have failed.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
            $this->assertStringContainsString('jobauko:google-drive', $e->getMessage());
            $this->assertStringNotContainsString('client-secret', $e->getMessage());
            $this->assertStringNotContainsString('refresh-token', $e->getMessage());
        }
    }

    public function test_missing_permission_to_send_is_explained()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Request had insufficient authentication scopes']], 403),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('allow sending email');

        Mail::raw('Hello', fn ($m) => $m->to('seeker@example.test')->subject('Hi'));
    }

    public function test_missing_settings_give_a_clear_message()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GOOGLE_DRIVE_REFRESH_TOKEN is missing');

        GmailTransport::make(['client_id' => 'id', 'client_secret' => 'secret', 'refresh_token' => '']);
    }
}
