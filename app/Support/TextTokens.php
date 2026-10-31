<?php

namespace App\Support;

/**
 * Turns free text (course names, job titles, fields of interest) into a set
 * of meaningful lowercase words for overlap comparisons.
 */
final class TextTokens
{
    private const STOPWORDS = [
        'and', 'the', 'for', 'with', 'from', 'into', 'this', 'that', 'your', 'our', 'their', 'are', 'was', 'will',
        'bachelor', 'science', 'arts', 'major', 'program', 'programs', 'course', 'courses', 'degree', 'level',
        'assistant', 'staff', 'officer', 'worker', 'position', 'job', 'jobs', 'work', 'opportunity', 'bauko',
        'mountain', 'province', 'municipal', 'municipality', 'barangay', 'office', 'national', 'school', 'university',
        'college', 'state', 'training', 'seminar', 'other',
    ];

    /**
     * Common abbreviations expanded to the words people also write out.
     */
    private const SYNONYMS = [
        'it' => ['information', 'technology'],
        'ict' => ['information', 'technology', 'communication'],
        'cs' => ['computer'],
        'bsit' => ['information', 'technology'],
        'bscs' => ['computer'],
        'bsa' => ['agriculture'],
        'agri' => ['agriculture'],
        'agricultural' => ['agriculture'],
        'farming' => ['agriculture', 'farming'],
        'farm' => ['agriculture', 'farming'],
        'educ' => ['education'],
        'bsed' => ['education'],
        'beed' => ['education'],
        'teacher' => ['education', 'teaching'],
        'nurse' => ['nursing', 'health'],
        'bsn' => ['nursing', 'health'],
        'accountancy' => ['accounting'],
        'bookkeeper' => ['accounting', 'bookkeeping'],
        'bsba' => ['business', 'administration'],
        'programmer' => ['programming', 'computer'],
        'developer' => ['programming', 'computer'],
    ];

    /**
     * @return list<string>
     */
    public static function from(?string ...$texts): array
    {
        $tokens = [];

        foreach ($texts as $text) {
            preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower((string) $text), $matches);

            foreach ($matches[0] as $word) {
                foreach (self::SYNONYMS[$word] ?? [$word] as $token) {
                    if ((mb_strlen($token) >= 3 || isset(self::SYNONYMS[$word])) && ! in_array($token, self::STOPWORDS, true)) {
                        $tokens[$token] = true;
                    }
                }
            }
        }

        return array_keys($tokens);
    }

    /**
     * Shared tokens between two token lists.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    public static function overlap(array $a, array $b): array
    {
        return array_values(array_intersect($a, $b));
    }
}
