<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends SMS through Semaphore (https://semaphore.co), a Philippine gateway.
 */
class SemaphoreSmsGateway implements SmsGateway
{
    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    public function __construct(private readonly string $apiKey, private readonly ?string $senderName = null) {}

    public function send(string $mobileNumber, string $message): SmsResult
    {
        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(self::ENDPOINT, array_filter([
                    'apikey' => $this->apiKey,
                    'number' => $mobileNumber,
                    'message' => $message,
                    'sendername' => $this->senderName,
                ]));
        } catch (ConnectionException $e) {
            return SmsResult::failed('Could not reach Semaphore: '.$e->getMessage());
        }

        $first = $response->json('0');

        if ($response->successful() && is_array($first) && isset($first['message_id'])) {
            return SmsResult::sent((string) $first['message_id']);
        }

        // Semaphore answers validation problems with a 200 and an object of
        // field errors, so the body is recorded either way.
        return SmsResult::failed(substr("HTTP {$response->status()}: {$response->body()}", 0, 500));
    }

    public function name(): string
    {
        return 'semaphore';
    }
}
