<?php

namespace App\Services\Resumes;

use App\Enums\EducationLevel;
use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Rule-based extraction of profile data from resume text.
 *
 * Results are suggestions only: they are shown to the seeker for review and
 * correction, and nothing is added to the profile until they confirm.
 */
class ResumeDataExtractor
{
    private const MONTH = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?';

    private const SCHOOL = '/\b(university|college|school|institute|academy|polytechnic)\b/i';

    private const PRESENT = '/\b(present|current|ongoing|to date|now)\b/i';

    private const CERTIFICATION = '/\b(nc\s?(i{1,3}|iv)|national certificate|certified|certificate of|certification|licen[sc]e[ds]?|civil service|eligibility|board exam|passer)\b/i';

    /**
     * Degree keywords, checked in order from highest to lowest level.
     *
     * @var array<string, string>
     */
    private const DEGREE_PATTERNS = [
        'doctorate' => '/\b(doctor of|doctorate|ph\.?\s?d|ed\.?\s?d)\b/i',
        'masters' => "/\\b(master(?:'s)? of|masters? (?:in|degree)|mba|maed|mpa)\\b/i",
        'college_graduate' => '/(\bbachelor\b|\bbs\s|\bbs[a-z]{1,5}\b|\bb\.s\.|\bbsc\b|\bab\s+(?:in\s+)?[a-z]|\ba\.b\.)/i',
        'college_level' => '/\b(undergraduate|college level|units in)\b/i',
        'vocational' => '/\b(tesda|vocational|nc\s?(i{1,3}|iv)|national certificate|tech(?:nical)?[- ]voc(?:ational)?)\b/i',
        'senior_high' => '/\b(senior high|shs|grade 1[12])\b/i',
        'junior_high' => '/\b(junior high|high school|secondary)\b/i',
        'elementary' => '/\b(elementary|primary school|grade school)\b/i',
    ];

    private const HEADING_WORDS = [
        'education', 'educational', 'background', 'attainment', 'academic', 'history', 'qualification', 'qualifications',
        'work', 'professional', 'employment', 'experience', 'experiences', 'relevant', 'job', 'skill', 'skills',
        'technical', 'key', 'core', 'other', 'competencies', 'abilities', 'and', 'certification', 'certifications',
        'certificates', 'license', 'licenses', 'eligibility', 'eligibilities', 'training', 'trainings', 'seminar',
        'seminars', 'attended', 'workshops', 'objective', 'career', 'summary', 'profile', 'personal', 'information',
        'data', 'details', 'reference', 'references', 'character', 'affiliation', 'affiliations', 'award', 'awards',
        'recognition', 'recognitions', 'achievement', 'achievements', 'language', 'languages', 'hobbies', 'interests',
        'contact', 'of', 'the', 'my',
    ];

    private const STOPWORDS = [
        'about', 'above', 'after', 'again', 'also', 'among', 'and', 'any', 'been', 'being', 'both', 'but', 'can', 'could',
        'date', 'does', 'during', 'each', 'from', 'have', 'having', 'here', 'into', 'just', 'more', 'most', 'must', 'name',
        'only', 'other', 'over', 'same', 'should', 'some', 'such', 'than', 'that', 'their', 'them', 'then', 'there', 'these',
        'they', 'this', 'those', 'through', 'under', 'until', 'very', 'were', 'what', 'when', 'where', 'which', 'while', 'will',
        'with', 'within', 'would', 'your', 'yours', 'present', 'january', 'february', 'march', 'april', 'june', 'july',
        'august', 'september', 'october', 'november', 'december', 'resume', 'curriculum', 'vitae', 'email', 'address',
        'contact', 'number', 'mobile', 'phone', 'birth', 'status', 'single', 'married', 'religion', 'citizenship',
        'filipino', 'references', 'reference', 'available', 'upon', 'request', 'hereby', 'certify', 'information',
        'personal', 'objective', 'seeking', 'position', 'years', 'year', 'month', 'months', 'philippines', 'province',
    ];

    /**
     * @return array{skills: list<string>, education: list<array<string, mixed>>, experience: list<array<string, mixed>>, certifications: list<array<string, mixed>>, trainings: list<array<string, mixed>>, keywords: list<string>}
     */
    public function extract(string $text): array
    {
        $lines = $this->lines($text);
        $sections = $this->splitSections($lines);

        $educationLines = $sections['education']
            ?? array_values(array_filter($lines, fn (array $l) => $this->degreeLevel($l['text']) !== null));

        return [
            'skills' => $this->skills($text, $sections['skills'] ?? []),
            'education' => $this->education($educationLines),
            'experience' => $this->experience($sections['experience'] ?? []),
            'certifications' => $this->certifications($sections['certifications'] ?? [], $lines),
            'trainings' => $this->trainings($sections['trainings'] ?? []),
            'keywords' => $this->keywords($text),
        ];
    }

    /**
     * @return list<array{text: string, bullet: bool}>
     */
    private function lines(string $text): array
    {
        $lines = [];

        foreach (preg_split('/\n/', $text) as $raw) {
            $bullet = (bool) preg_match('/^\s*[•●▪◦*·\-–]\s*/u', $raw);
            $line = trim(preg_replace('/^[\s•●▪◦*·\-–]+|[\s•●▪◦*·]+$/u', '', $raw));

            if ($line !== '') {
                $lines[] = ['text' => $line, 'bullet' => $bullet];
            }
        }

        return $lines;
    }

    /**
     * Group lines under the resume headings they follow.
     *
     * @param  list<array{text: string, bullet: bool}>  $lines
     * @return array<string, list<array{text: string, bullet: bool}>>
     */
    private function splitSections(array $lines): array
    {
        $sections = [];
        $current = null;

        foreach ($lines as $line) {
            $heading = $this->headingSection($line['text']);

            if ($heading !== null) {
                $current = $heading;
                $sections[$current] ??= [];

                continue;
            }

            if ($current !== null) {
                $sections[$current][] = $line;
            }
        }

        unset($sections['other']);

        return $sections;
    }

    private function headingSection(string $line): ?string
    {
        $heading = Str::of($line)->replace('&', ' and ')->replaceMatches('/[^a-z ]/i', '')->squish()->lower()->toString();

        if ($heading === '' || str_word_count($heading) > 5 || strlen($line) > 45 || preg_match('/\d/', $line)) {
            return null;
        }

        // Every word must be heading vocabulary, so that entries such as
        // "Financial Literacy Seminar" aren't mistaken for headings.
        if (array_diff(explode(' ', $heading), self::HEADING_WORDS) !== []) {
            return null;
        }

        return match (true) {
            Str::contains($heading, ['training', 'seminar', 'workshop']) => 'trainings',
            Str::contains($heading, ['certif', 'licens', 'eligib']) => 'certifications',
            Str::contains($heading, ['education', 'academic']) => 'education',
            Str::contains($heading, ['experience', 'employment', 'work history']) => 'experience',
            Str::contains($heading, ['skill', 'competenc']) => 'skills',
            (bool) preg_match('/^(career )?(objective|summary|profile|personal (information|data|details|background)|references?|character references?|affiliations?|awards?( and recognitions?)?|achievements?|languages?|hobbies|interests|contact( information)?)$/', $heading) => 'other',
            default => null,
        };
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $skillLines
     * @return list<string>
     */
    private function skills(string $text, array $skillLines): array
    {
        $found = [];

        foreach (Skill::query()->pluck('name') as $name) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', $text)) {
                $found[mb_strtolower($name)] = $name;
            }
        }

        foreach ($skillLines as $line) {
            foreach (preg_split('/\s*[,;|•\/]\s*/u', $line['text']) as $item) {
                $item = trim(preg_replace('/^[\s:]+|[\s.:]+$/u', '', $item));
                $words = count(preg_split('/\s+/', $item));

                if (mb_strlen($item) >= 2 && mb_strlen($item) <= 40 && $words <= 4 && ! preg_match('/\d{4}/', $item)) {
                    $found[mb_strtolower($item)] ??= $item;
                }
            }
        }

        return array_slice(array_values($found), 0, 40);
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $lines
     * @return list<array<string, mixed>>
     */
    private function education(array $lines): array
    {
        $entries = [];
        $current = null;
        $blank = ['level' => null, 'school' => null, 'course' => null, 'year_graduated' => null, 'is_current' => false];

        foreach ($lines as $line) {
            [$course, $school] = $this->courseAndSchool($this->withoutYears($line['text']));

            if ($course !== null) {
                if ($current !== null && $current['course'] !== null) {
                    $entries[] = $current;
                    $current = null;
                }
                $current ??= $blank;
                $current['course'] = $course;
                $current['level'] = $this->degreeLevel($course);
            }

            if ($school !== null) {
                if ($current !== null && $current['school'] !== null) {
                    $entries[] = $current;
                    $current = null;
                }
                $current ??= $blank;
                $current['school'] = $school;
                $current['level'] ??= $this->degreeLevel($school) ?? EducationLevel::CollegeLevel->value;
            }

            if ($current === null) {
                continue;
            }

            if ($years = $this->years($line['text'])) {
                $current['year_graduated'] = max($years);
            }

            if (preg_match(self::PRESENT, $line['text'])) {
                $current['is_current'] = true;
                $current['year_graduated'] = null;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return array_slice($entries, 0, 10);
    }

    /**
     * Split a line such as "BS Information Technology - Benguet State
     * University" into its course and school parts.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function courseAndSchool(string $text): array
    {
        $course = null;
        $school = null;

        foreach (preg_split('/\s+[|–—-]\s+|\s*,\s+(?=[A-Z])/u', $text) as $segment) {
            $segment = trim($segment, " ,()\t");

            if ($segment === '') {
                continue;
            }

            if ($school === null && preg_match(self::SCHOOL, $segment)) {
                $school = $segment;
            } elseif ($course === null && $this->degreeLevel($segment) !== null) {
                $course = $segment;
            }
        }

        return [$course, $school];
    }

    private function degreeLevel(string $text): ?string
    {
        foreach (self::DEGREE_PATTERNS as $level => $pattern) {
            if (preg_match($pattern, $text)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $lines
     * @return list<array<string, mixed>>
     */
    private function experience(array $lines): array
    {
        $entries = [];
        $current = null;
        $blank = ['position' => null, 'company' => null, 'start_date' => null, 'end_date' => null, 'description' => null];
        $date = '(?:'.self::MONTH.'\s+)?(?:19|20)\d{2}';
        $range = "/({$date})\s*(?:-|–|—|to)\s*({$date}|present|current|to date|now)/iu";

        foreach ($lines as $line) {
            $text = $line['text'];

            if (preg_match($range, $text, $match)) {
                if ($current !== null && $current['start_date'] !== null) {
                    $entries[] = $current;
                    $current = null;
                }
                $current ??= $blank;
                $current['start_date'] = $this->parseDate($match[1]);
                $current['end_date'] = preg_match(self::PRESENT, $match[2]) ? null : $this->parseDate($match[2]);

                $rest = trim(str_replace($match[0], '', $text), " ,|()–—-\t");
                if ($rest !== '') {
                    $current = $this->assignRole($current, $rest);
                }

                continue;
            }

            $isDescription = $line['bullet'] || str_word_count($text) > 8;

            if ($isDescription && $current !== null) {
                $current['description'] = trim(($current['description'] ?? '')."\n".$text);

                continue;
            }

            if ($current === null || ($current['position'] !== null && $current['company'] !== null && $current['start_date'] !== null)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = $blank;
            }

            $current = $this->assignRole($current, $text);
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return array_slice(array_values(array_filter($entries, fn (array $e) => $e['position'] !== null)), 0, 15);
    }

    /**
     * Fill position, then company, from a line like "Sales Clerk | ABC Store".
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function assignRole(array $entry, string $text): array
    {
        $parts = preg_split('/\s+(?:\||at|@)\s+|\s+[–—-]\s+/u', $text, 2);

        foreach ($parts as $part) {
            $part = trim($part, " ,|()\t");

            if ($part === '') {
                continue;
            }

            if ($entry['position'] === null) {
                $entry['position'] = $part;
            } elseif ($entry['company'] === null) {
                $entry['company'] = $part;
            } else {
                $entry['description'] = trim(($entry['description'] ?? '')."\n".$part);
            }
        }

        return $entry;
    }

    private function parseDate(string $value): ?string
    {
        if (! preg_match('/(19|20)\d{2}/', $value, $year)) {
            return null;
        }

        $month = 1;
        if (preg_match('/[a-z]{3}/i', $value, $name)) {
            $index = array_search(strtolower($name[0]), ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], true);
            $month = $index === false ? 1 : $index + 1;
        }

        return sprintf('%s-%02d-01', $year[0], $month);
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $sectionLines
     * @param  list<array{text: string, bullet: bool}>  $allLines
     * @return list<array<string, mixed>>
     */
    private function certifications(array $sectionLines, array $allLines): array
    {
        $candidates = [
            ...array_column($sectionLines, 'text'),
            ...array_filter(array_column($allLines, 'text'), fn (string $t) => strlen($t) <= 120 && preg_match(self::CERTIFICATION, $t)),
        ];

        $found = [];

        foreach ($candidates as $text) {
            if ($this->headingSection($text) !== null) {
                continue;
            }

            $name = trim($this->withoutYears($text), " ,|()–—-\t");

            if ($name === '' || isset($found[mb_strtolower($name)])) {
                continue;
            }

            $found[mb_strtolower($name)] = [
                'name' => Str::limit($name, 250, ''),
                'issuer' => match (true) {
                    (bool) preg_match('/tesda|\bnc\s?(i{1,3}|iv)\b|national certificate/i', $text) => 'TESDA',
                    (bool) preg_match('/civil service/i', $text) => 'Civil Service Commission',
                    (bool) preg_match('/\b(prc|licensure|board exam)\b/i', $text) => 'Professional Regulation Commission',
                    default => null,
                },
            ];
        }

        return array_slice(array_values($found), 0, 20);
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $lines
     * @return list<array<string, mixed>>
     */
    private function trainings(array $lines): array
    {
        $found = [];

        foreach ($lines as $line) {
            $hours = preg_match('/(\d{1,4})\s*(?:hours|hrs)\b/i', $line['text'], $match) ? (int) $match[1] : null;
            $text = trim(preg_replace('/\(?\d{1,4}\s*(?:hours|hrs)\)?/i', '', $this->withoutYears($line['text'])), " ,|()–—-\t");
            $parts = preg_split('/\s+[|–—-]\s+/u', $text, 2);
            $title = trim($parts[0] ?? '');

            if ($title === '' || isset($found[mb_strtolower($title)])) {
                continue;
            }

            $found[mb_strtolower($title)] = [
                'title' => Str::limit($title, 250, ''),
                'provider' => isset($parts[1]) ? Str::limit(trim($parts[1]), 250, '') : null,
                'hours' => $hours,
            ];
        }

        return array_slice(array_values($found), 0, 20);
    }

    /**
     * @return list<string>
     */
    private function keywords(string $text): array
    {
        preg_match_all('/\p{L}{4,}/u', mb_strtolower($text), $matches);

        $counts = array_count_values(array_diff($matches[0], self::STOPWORDS));
        arsort($counts);

        return array_slice(array_keys($counts), 0, 15);
    }

    /**
     * @return list<int>
     */
    private function years(string $text): array
    {
        preg_match_all('/\b(19[5-9]\d|20\d{2})\b/', $text, $matches);

        return array_values(array_filter(
            array_map('intval', $matches[1]),
            fn (int $year) => $year <= (int) date('Y') + 6,
        ));
    }

    private function withoutYears(string $text): string
    {
        $date = '(?:'.self::MONTH.'\s+)?(?:19|20)\d{2}';

        $text = preg_replace("/\\(?{$date}\\s*(?:-|–|—|to)\\s*(?:{$date}|present|current|to date|now)\\)?/iu", '', $text);
        $text = preg_replace("/\\(?\\b{$date}\\b\\)?/iu", '', $text);

        return Str::squish($text);
    }
}
