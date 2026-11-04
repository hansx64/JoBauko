<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses passwords built from a common word with digits or symbols around
 * it (password2026, Bauko@12345) and ones that are mostly one repeated
 * character or a simple run like 1234567890. The word list is shared with
 * the strength meter: resources/js/lib/common-passwords.json.
 */
class NotCommonPassword implements ValidationRule
{
    /** @var list<string>|null */
    private static ?array $words = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isCommon($value)) {
            $fail('This password is too easy to guess. Avoid common words like "password" and simple runs like "12345".');
        }
    }

    public static function isCommon(string $password): bool
    {
        $lower = mb_strtolower($password);
        $letters = preg_replace('/[^a-z]/', '', $lower) ?? '';

        if (in_array($letters, self::words(), true)) {
            return true;
        }

        // Few different characters (aaaa1111) or an ascending/descending run.
        $characters = mb_str_split($lower);
        if (count(array_unique($characters)) <= 3) {
            return true;
        }

        $steps = 0;
        for ($i = 1; $i < count($characters); $i++) {
            if (abs(mb_ord($characters[$i]) - mb_ord($characters[$i - 1])) === 1) {
                $steps++;
            }
        }

        return $steps >= count($characters) - 2;
    }

    /**
     * @return list<string>
     */
    private static function words(): array
    {
        /** @var list<string> */
        return self::$words ??= json_decode((string) file_get_contents(resource_path('js/lib/common-passwords.json')), true);
    }
}
