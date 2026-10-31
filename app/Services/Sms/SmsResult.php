<?php

namespace App\Services\Sms;

final readonly class SmsResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, error: $error);
    }
}
