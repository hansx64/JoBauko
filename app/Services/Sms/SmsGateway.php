<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * Deliver one message to a 09XXXXXXXXX mobile number.
     */
    public function send(string $mobileNumber, string $message): SmsResult;

    /**
     * Short provider name recorded in sms_logs.
     */
    public function name(): string;
}
