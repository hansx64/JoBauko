<?php

namespace App\Services\Profiles;

use App\Models\User;

/**
 * Computes how complete a seeker's profile is. Derived on demand rather than
 * stored, so it can never drift from the underlying data.
 */
class ProfileCompletion
{
    /**
     * @return array{percent: int, sections: list<array{key: string, label: string, weight: int, complete: bool, hint: string}>}
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

        $percent = array_sum(array_map(fn (array $s) => $s['complete'] ? $s['weight'] : 0, $sections));

        return ['percent' => $percent, 'sections' => $sections];
    }
}
