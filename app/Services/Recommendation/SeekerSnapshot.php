<?php

namespace App\Services\Recommendation;

use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\InterestCategory;
use App\Models\User;
use App\Support\TextTokens;

/**
 * The parts of a seeker's profile used for matching, loaded once and reused
 * for every opportunity scored. Only data the seeker entered or confirmed is
 * included; unreviewed resume suggestions are not.
 */
final readonly class SeekerSnapshot
{
    /**
     * @param  array<string, string>  $skills  lowercase name => display name
     * @param  list<string>  $courses
     * @param  list<array{position: string, industry: ?string}>  $experience
     * @param  list<array{category: InterestCategory, field: ?string}>  $interests
     */
    public function __construct(
        public ?int $barangayId,
        public ?Classification $classification,
        public ?EducationLevel $highestEducation,
        public array $skills,
        public array $courses,
        public array $experience,
        public array $interests,
    ) {}

    public static function fromUser(User $user): self
    {
        $profile = $user->applicantProfile;
        $profile?->loadMissing(['skills', 'education', 'experience', 'interests']);

        return new self(
            barangayId: $user->barangay_id,
            classification: $profile?->classification,
            highestEducation: $profile?->highest_education,
            skills: $profile ? $profile->skills->mapWithKeys(fn ($s) => [mb_strtolower($s->name) => $s->name])->all() : [],
            courses: $profile ? $profile->education->pluck('course')->filter()->values()->all() : [],
            experience: $profile ? $profile->experience->map(fn ($e) => ['position' => $e->position, 'industry' => $e->industry])->all() : [],
            interests: $profile ? $profile->interests->map(fn ($i) => ['category' => $i->category, 'field' => $i->field])->all() : [],
        );
    }

    /**
     * Labelled pieces of background text, used to explain field matches.
     *
     * @return list<array{label: string, text: string, tokens: list<string>}>
     */
    public function backgroundSources(): array
    {
        $sources = [];

        foreach ($this->courses as $course) {
            $sources[] = ['label' => 'course', 'text' => $course, 'tokens' => TextTokens::from($course)];
        }

        foreach ($this->experience as $job) {
            $sources[] = ['label' => 'experience as', 'text' => $job['position'], 'tokens' => TextTokens::from($job['position'], $job['industry'])];
        }

        foreach ($this->interests as $interest) {
            if ($interest['field']) {
                $sources[] = ['label' => 'interest in', 'text' => $interest['field'], 'tokens' => TextTokens::from($interest['field'])];
            }
        }

        return $sources;
    }
}
