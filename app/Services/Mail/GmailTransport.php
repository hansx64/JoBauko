<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;

/**
 * Sends mail through the Gmail API (MAIL_MAILER=gmail), as the Google account
 * connected with `php artisan jobauko:google-drive`. It goes over HTTPS, so it
 * works on hosts that block SMTP ports, such as Render's free plan.
 */
class GmailTransport extends AbstractTransport
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    /**
     * @param  array<string, mixed>  $config  the mailer's entry in config/mail.php
     */
    public static function make(array $config): self
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (blank($config[$key] ?? null)) {
                throw new InvalidArgumentException('Gmail sending is not configured: GOOGLE_DRIVE_'.strtoupper($key).' is missing. Run php artisan jobauko:google-drive.');
            }
        }

        return new self($config['client_id'], $config['client_secret'], $config['refresh_token']);
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();

        // Bcc is not part of the prepared message; Gmail reads recipients from
        // the headers, so add the envelope recipients that would be lost.
        $hidden = array_diff($this->addresses($message->getEnvelope()->getRecipients()), $this->listedRecipients($message));
        if ($hidden !== []) {
            $raw = 'Bcc: '.implode(', ', $hidden)."\r\n".$raw;
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->post(self::SEND_URL, ['raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=')]);

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
        }

        if ($response->failed()) {
            $reason = $response->json('error.message') ?? "HTTP {$response->status()}";
            $hint = $response->status() === 403 ? ' Run php artisan jobauko:google-drive again and allow sending email.' : '';

            throw new TransportException("Gmail did not send the message: {$reason}.{$hint}");
        }

        if ($id = $response->json('id')) {
            $message->setMessageId($id);
        }
    }

    public function __toString(): string
    {
        return 'gmail://api';
    }

    /**
     * Access tokens last an hour; keep one (encrypted) instead of asking Google for every message.
     */
    private function accessToken(): string
    {
        if ($cached = Cache::get($this->cacheKey())) {
            return decrypt($cached);
        }

        $response = Http::asForm()->acceptJson()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token)) {
            $reason = $response->json('error') ?? "HTTP {$response->status()}";

            throw new TransportException("Google refused the stored sign-in ({$reason}). Run php artisan jobauko:google-drive again.");
        }

        $lifetime = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($this->cacheKey(), encrypt($token), $lifetime);

        return $token;
    }

    private function cacheKey(): string
    {
        return 'gmail-access-token:'.sha1($this->clientId.'|'.$this->refreshToken);
    }

    /**
     * @return list<string>
     */
    private function listedRecipients(SentMessage $message): array
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            return [];
        }

        $listed = [];
        foreach (['To', 'Cc'] as $name) {
            $header = $original->getHeaders()->get($name);
            if ($header !== null) {
                /** @var list<Address> $found */
                $found = $header->getBody();
                $listed = [...$listed, ...$this->addresses($found)];
            }
        }

        return $listed;
    }

    /**
     * @param  array<Address>  $addresses
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(fn (Address $address) => strtolower($address->getAddress()), $addresses));
    }
}
