<?php

namespace App\Services\Profiles;

use App\Models\User;

/**
 * Computes how complete a seeker's profile is. Derived on demand rather than
 * stored, so it can never drift from the underlying data.
 *
 * Sections marked required must be complete before the seeker can apply:
 * organizations need to see who they are, their education, their skills and
 * a resume. The others improve recommendations but many residents (students,
 * first-time job seekers) have nothing to put there yet.
 */
class ProfileCompletion
{
    public const REQUIRED_TO_APPLY = ['personal', 'education', 'skills', 'resume'];

    /**
     * The required sections the seeker still has to complete before applying.
     *
     * @return list<array{key: string, label: string, weight: int, complete: bool, required: bool, hint: string}>
     */
    public function missingToApply(User $user): array
    {
        $sections = $this->for($user)['sections'];

        if ($sections === []) {
            // No applicant profile at all: everything required is missing.
            return array_map(fn (string $key) => ['key' => $key, 'label' => ucfirst($key), 'weight' => 0, 'complete' => false, 'required' => true, 'hint' => ''], self::REQUIRED_TO_APPLY);
        }

        return array_values(array_filter($sections, fn (array $s) => $s['required'] && ! $s['complete']));
    }

    /**
     * @return array{percent: int, sections: list<array{key: string, label: string, weight: int, complete: bool, required: bool, hint: string}>}
     */
    public function for(User $user): array
    {
        $user->loadMissing('currentResume');
        $profile = $user->applicantProfile;

        if ($profile === null) {
            return ['percent' => 0, 'sections' => []];
        }

        $profile->loadCount(['education', 'experience', 'certifications', 'trainings', 'skills', 'interests']);

        $sections = [
            [
                'key' => 'personal',
                'label' => 'Personal information',
                'weight' => 20,
                'complete' => $profile->date_of_birth !== null && $profile->gender !== null && filled($profile->address_line),
                'hint' => 'Add your date of birth, gender and address.',
            ],
            [
                'key' => 'education',
                'label' => 'Educational background',
                'weight' => 20,
                'complete' => $profile->highest_education !== null && $profile->education_count > 0,
                'hint' => 'Set your highest attainment and add at least one school.',
            ],
            [
                'key' => 'skills',
                'label' => 'Skills',
                'weight' => 20,
                'complete' => $profile->skills_count > 0,
                'hint' => 'Add the skills you have. They are the strongest signal for recommendations.',
            ],
            [
                'key' => 'experience',
                'label' => 'Work experience',
                'weight' => 10,
                'complete' => $profile->experience_count > 0,
                'hint' => 'Add jobs, part-time work, farm work or volunteer work.',
            ],
            [
                'key' => 'credentials',
                'label' => 'Certifications and trainings',
                'weight' => 10,
                'complete' => $profile->certifications_count > 0 || $profile->trainings_count > 0,
                'hint' => 'Add certificates (e.g. TESDA NC II) or trainings you attended.',
            ],
            [
                'key' => 'interests',
                'label' => 'Areas of interest',
                'weight' => 10,
                'complete' => $profile->interests_count > 0,
                'hint' => 'Tell us what kinds of opportunities you are looking for.',
            ],
            [
                'key' => 'resume',
                'label' => 'Resume',
                'weight' => 10,
                'complete' => $user->currentResume !== null,
                'hint' => 'Upload your resume as a PDF or Word (DOCX) file, or create one with the resume builder.',
            ],
        ];

        $sections = array_map(fn (array $s) => [...$s, 'required' => in_array($s['key'], self::REQUIRED_TO_APPLY, true)], $sections);

        $percent = array_sum(array_map(fn (array $s) => $s['complete'] ? $s['weight'] : 0, $sections));

        return ['percent' => $percent, 'sections' => $sections];
    }
}
