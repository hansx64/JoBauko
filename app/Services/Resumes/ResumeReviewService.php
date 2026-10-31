<?php

namespace App\Services\Resumes;

use App\Enums\EducationLevel;
use App\Enums\ResumeExtractionStatus;
use App\Models\ApplicantProfile;
use App\Models\Resume;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

/**
 * Applies the resume data a seeker has reviewed and confirmed to their
 * profile. Only confirmed items are ever used for matching.
 */
class ResumeReviewService
{
    /**
     * @param  array{skills?: list<string>, education?: list<array<string, mixed>>, experience?: list<array<string, mixed>>, certifications?: list<array<string, mixed>>, trainings?: list<array<string, mixed>>}  $confirmed
     */
    public function apply(Resume $resume, ApplicantProfile $profile, array $confirmed): void
    {
        DB::transaction(function () use ($resume, $profile, $confirmed) {
            $existingSkillIds = $profile->skills()->pluck('skills.id')->all();

            foreach ($confirmed['skills'] ?? [] as $name) {
                $skill = Skill::findOrCreateByName($name);

                if (! in_array($skill->id, $existingSkillIds, true)) {
                    $profile->skills()->attach($skill->id, ['source' => 'resume']);
                    $existingSkillIds[] = $skill->id;
                }
            }

            foreach ($confirmed['education'] ?? [] as $entry) {
                $profile->education()->firstOrCreate(
                    ['level' => $entry['level'], 'school' => $entry['school'], 'course' => $entry['course'] ?? null],
                    ['year_graduated' => $entry['year_graduated'] ?? null, 'is_current' => $entry['is_current'] ?? false],
                );
            }

            foreach ($confirmed['experience'] ?? [] as $entry) {
                $profile->experience()->firstOrCreate(
                    ['position' => $entry['position'], 'company' => $entry['company'], 'start_date' => $entry['start_date']],
                    ['end_date' => $entry['end_date'] ?? null, 'description' => $entry['description'] ?? null],
                );
            }

            foreach ($confirmed['certifications'] ?? [] as $entry) {
                $profile->certifications()->firstOrCreate(
                    ['name' => $entry['name']],
                    ['issuer' => $entry['issuer'] ?? null],
                );
            }

            foreach ($confirmed['trainings'] ?? [] as $entry) {
                $profile->trainings()->firstOrCreate(
                    ['title' => $entry['title']],
                    ['provider' => $entry['provider'] ?? null, 'hours' => $entry['hours'] ?? null],
                );
            }

            $this->raiseHighestEducation($profile);

            $resume->update([
                'extraction_status' => ResumeExtractionStatus::Reviewed,
                'reviewed_at' => now(),
            ]);
        });
    }

    /**
     * Set highest attainment from the education entries when the seeker
     * hasn't chosen one, or when an entry shows a higher completed level.
     */
    private function raiseHighestEducation(ApplicantProfile $profile): void
    {
        $highest = $profile->education()->get()
            ->filter(fn ($entry) => ! $entry->is_current)
            ->map(fn ($entry) => $entry->level)
            ->sortByDesc(fn (EducationLevel $level) => $level->rank())
            ->first();

        if ($highest !== null && ($profile->highest_education === null || $highest->rank() > $profile->highest_education->rank())) {
            $profile->update(['highest_education' => $highest]);
        }
    }
}
