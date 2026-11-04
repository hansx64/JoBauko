<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Philippine mobile numbers, stored in the local 11-digit form (09XXXXXXXXX).
 */
final class MobileNumber
{
    /**
     * A number may be on one account of each type: a resident's number can
     * also be given when registering their organization, but two job seeker
     * accounts (or two organization accounts) cannot share it.
     */
    public static function uniqueFor(AccountType $type): Unique
    {
        return Rule::unique(User::class, 'mobile_number')->where('account_type', $type->value);
    }

    /**
     * Normalize common input formats (0917..., 917..., +63917..., 63917...,
     * with spaces or dashes) to 09XXXXXXXXX. Returns the input unchanged when
     * it doesn't look like a Philippine mobile number, so validation can
     * reject it with a useful message.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-().]/', '', $value);

        return match (true) {
            (bool) preg_match('/^\+?639\d{9}$/', $digits) => '0'.substr($digits, -10),
            (bool) preg_match('/^9\d{9}$/', $digits) => '0'.$digits,
            default => $digits,
        };
    }

    /**
     * Mask a number for display, e.g. 0917•••4567.
     */
    public static function mask(string $number): string
    {
        return substr($number, 0, 4).'•••'.substr($number, -4);
    }
}
