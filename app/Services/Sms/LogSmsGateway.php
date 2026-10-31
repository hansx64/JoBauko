<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development gateway: writes messages to the application log instead of
 * sending them.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $mobileNumber, string $message): SmsResult
    {
        Log::info("[SMS to {$mobileNumber}] {$message}");

        return SmsResult::sent();
    }

    public function name(): string
    {
        return 'log';
    }
}
