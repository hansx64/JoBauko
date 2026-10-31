<?php

namespace App\Support;

/**
 * Philippine mobile numbers, stored in the local 11-digit form (09XXXXXXXXX).
 */
final class MobileNumber
{
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
