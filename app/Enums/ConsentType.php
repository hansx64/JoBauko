<?php

namespace App\Enums;

enum ConsentType: string
{
    case PrivacyNotice = 'privacy_notice';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::PrivacyNotice => 'Privacy notice',
            self::Sms => 'SMS notifications',
        };
    }

    /**
     * The version of the statement the user is currently agreeing to.
     */
    public function currentVersion(): string
    {
        return match ($this) {
            self::PrivacyNotice => config('jobauko.privacy_notice_version'),
            self::Sms => config('jobauko.sms_consent_version'),
        };
    }
}
