<?php

namespace App\Services\Recommendation;

use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EmploymentType;
use App\Enums\InterestCategory;
use App\Enums\OpportunityCategory;
use App\Models\Opportunity;
use App\Support\TextTokens;
use Illuminate\Support\Str;

/**
 * Explainable, deterministic scoring of how well an opportunity fits a seeker.
 *
 * Each criterion yields a fraction (0-1) of its weight plus the reason or gap
 * that explains it. Criteria the opportunity doesn't specify are left out of
 * the total so listings aren't penalized for brevity. The score is a guide
 * for the seeker only; it never decides eligibility.
 */
class MatchScorer
{
    public const WEIGHTS = [
        'skills' => 35,
        'education' => 20,
        'field' => 15,
        'classification' => 10,
        'location' => 10,
        'interests' => 10,
    ];

    /**
     * Minimum score for an opportunity to be shown as a recommendation.
     */
    public const RECOMMENDATION_THRESHOLD = 30;

    /**
     * Expects skills, barangay and category details to be loaded.
     */
    public function score(SeekerSnapshot $seeker, Opportunity $opportunity): MatchResult
    {
        $earned = 0.0;
        $possible = 0;
        $reasons = [];
        $gaps = [];

        foreach (array_keys(self::WEIGHTS) as $criterion) {
            $result = $this->{$criterion}($seeker, $opportunity);

            if ($result === null) {
                continue;
            }

            [$fraction, $reason, $gap] = $result + [1 => null, 2 => null];

            $possible += self::WEIGHTS[$criterion];
            $earned += self::WEIGHTS[$criterion] * $fraction;

            if ($reason !== null) {
                $reasons[] = $reason;
            }
            if ($gap !== null) {
                $gaps[] = $gap;
            }
        }

        $score = $possible === 0 ? 0 : (int) round($earned / $possible * 100);

        return new MatchResult($score, $reasons, $gaps);
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function skills(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        $listed = $opportunity->skills->pluck('name');

        if ($listed->isEmpty()) {
            return null;
        }

        $matched = $listed->filter(fn (string $name) => isset($seeker->skills[mb_strtolower($name)]))->values();
        $missing = $listed->diff($matched)->values();

        return [
            $matched->count() / $listed->count(),
            $matched->isEmpty() ? null : sprintf(
                'You have %d of %d listed skills: %s',
                $matched->count(),
                $listed->count(),
                $matched->implode(', '),
            ),
            $missing->isEmpty() ? null : 'Listed skills not on your profile: '.$missing->implode(', ').'. Add them if you have them.',
        ];
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function education(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        $required = $opportunity->min_education;

        if ($required === null) {
            return null;
        }

        if ($seeker->highestEducation === null) {
            return [0.0, null, 'Add your highest educational attainment to your profile.'];
        }

        if ($seeker->highestEducation->rank() >= $required->rank()) {
            return [1.0, "Your education ({$seeker->highestEducation->label()}) meets the {$required->label()} requirement"];
        }

        return [0.0, null, "Requires {$required->label()}; your profile shows {$seeker->highestEducation->label()}."];
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function field(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        $sources = $seeker->backgroundSources();
        $eligibleCourses = $opportunity->category === OpportunityCategory::Scholarship
            ? ($opportunity->scholarshipDetail?->eligible_courses ?? [])
            : [];

        // Scholarships limited to specific courses: compare course to course.
        if ($eligibleCourses !== []) {
            foreach ($seeker->courses as $course) {
                foreach ($eligibleCourses as $eligible) {
                    if (TextTokens::overlap(TextTokens::from($course), TextTokens::from($eligible)) !== []) {
                        return [1.0, "Your course “{$course}” matches the eligible course “{$eligible}”"];
                    }
                }
            }

            return [0.0, null, 'Eligible courses: '.Str::limit(implode(', ', $eligibleCourses), 120).'.'];
        }

        if ($sources === []) {
            return [0.0, null, 'Add your education, work experience or interests so we can match your field.'];
        }

        $opportunityTokens = TextTokens::from(
            $opportunity->title,
            $opportunity->qualifications,
            $opportunity->skills->pluck('name')->implode(' '),
        );

        foreach ($sources as $source) {
            if (TextTokens::overlap($source['tokens'], $opportunityTokens) !== []) {
                return [1.0, "Related to your {$source['label']} {$source['text']}"];
            }
        }

        return [0.0];
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function classification(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        $classification = $seeker->classification;

        if ($classification === null) {
            return null;
        }

        $label = $classification->label();

        return match ($opportunity->category) {
            OpportunityCategory::Scholarship => match ($classification) {
                Classification::Student => [1.0, 'Scholarships support students like you'],
                Classification::OutOfSchoolYouth => [1.0, 'Educational assistance can help out-of-school youth return to school'],
                Classification::FreshGraduate, Classification::CareerShifter => [0.5],
                default => [0.0],
            },
            OpportunityCategory::Livelihood => $this->livelihoodFit($classification, $opportunity),
            OpportunityCategory::Job => match (true) {
                $classification === Classification::Student => $opportunity->jobDetail?->employment_type === EmploymentType::Internship
                    ? [1.0, 'Internships are a good fit for students']
                    : [0.3],
                in_array($classification, [Classification::Unemployed, Classification::FreshGraduate, Classification::CareerShifter], true) => [1.0, "Suited to {$label} job seekers"],
                default => [0.6],
            },
        };
    }

    /**
     * @return array{0: float, 1?: ?string}
     */
    private function livelihoodFit(Classification $classification, Opportunity $opportunity): array
    {
        $targets = $opportunity->livelihoodDetail?->target_classifications ?? [];

        if ($targets !== []) {
            return in_array($classification->value, $targets, true)
                ? [1.0, "This program is meant for {$classification->label()} residents"]
                : [0.0];
        }

        return match ($classification) {
            Classification::Unemployed, Classification::SelfEmployed, Classification::OutOfSchoolYouth, Classification::Other => [1.0, "Livelihood support suits {$classification->label()} residents"],
            Classification::Employed, Classification::CareerShifter => [0.5],
            default => [0.3],
        };
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function location(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        if ($opportunity->is_remote) {
            return [1.0, 'Can be done remotely'];
        }

        if ($opportunity->barangay_id !== null) {
            return $opportunity->barangay_id === $seeker->barangayId
                ? [1.0, "In your barangay, {$opportunity->barangay?->name}"]
                : [0.8, "In Bauko (Brgy. {$opportunity->barangay?->name})"];
        }

        return filled($opportunity->location_text) ? [0.3] : null;
    }

    /**
     * @return array{0: float, 1?: ?string, 2?: ?string}|null
     */
    private function interests(SeekerSnapshot $seeker, Opportunity $opportunity): ?array
    {
        if ($seeker->interests === []) {
            return [0.0, null, 'Add your areas of interest to your profile.'];
        }

        $isTraining = $opportunity->livelihoodDetail?->assistance_type === AssistanceType::SkillsTraining
            || Str::contains(mb_strtolower($opportunity->title), 'training');

        $matching = array_filter($seeker->interests, fn (array $interest) => match ($interest['category']) {
            InterestCategory::Job => $opportunity->category === OpportunityCategory::Job,
            InterestCategory::Scholarship => $opportunity->category === OpportunityCategory::Scholarship,
            InterestCategory::Livelihood => $opportunity->category === OpportunityCategory::Livelihood,
            InterestCategory::Training => $isTraining,
        });

        if ($matching === []) {
            return [0.0];
        }

        $opportunityTokens = TextTokens::from(
            $opportunity->title,
            $opportunity->qualifications,
            $opportunity->skills->pluck('name')->implode(' '),
            implode(' ', $opportunity->scholarshipDetail?->eligible_courses ?? []),
        );

        foreach ($matching as $interest) {
            if ($interest['field'] && TextTokens::overlap(TextTokens::from($interest['field']), $opportunityTokens) !== []) {
                return [1.0, "Matches your interest in {$interest['field']} ({$interest['category']->label()})"];
            }
        }

        $first = reset($matching);

        return [0.6, "You're interested in {$first['category']->label()}"];
    }
}
