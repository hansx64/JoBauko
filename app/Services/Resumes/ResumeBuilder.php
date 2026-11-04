<?php

namespace App\Services\Resumes;

use App\Enums\Gender;
use App\Models\ApplicantEducation;
use App\Models\ApplicantExperience;
use App\Models\ApplicantProfile;
use App\Models\Resume;
use App\Models\ResumeDetail;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Builds a resume in the standard chronological format employers expect,
 * from the seeker's profile plus the resume-only details they add in the
 * resume builder.
 */
class ResumeBuilder
{
    /** Short bond paper, the size most local employers print on. */
    public const PAPER = 'letter';

    public function __construct(private readonly ResumeService $resumes) {}

    public function details(User $user): ResumeDetail
    {
        return $user->resumeDetail ?? new ResumeDetail;
    }

    /**
     * Everything printed on the resume, in display-ready form.
     *
     * @return array<string, mixed>
     */
    public function document(User $user): array
    {
        $user->loadMissing(['barangay.municipality.province', 'resumeDetail']);
        $profile = $this->profile($user);
        $details = $this->details($user);

        $experience = $profile->experience->map(fn (ApplicantExperience $e) => [
            'position' => $e->position,
            'company' => $e->company,
            'period' => $this->month($e->start_date).' – '.($this->month($e->end_date) ?? 'Present'),
            'duties' => $this->lines($e->description),
        ])->all();

        $references = collect($details->references ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->values()->all();

        return [
            'name' => $user->name,
            'address' => $this->address($user, $profile),
            // 0917 123 4567 reads more easily than 09171234567.
            'mobile' => $user->mobile_number ? preg_replace('/^(\d{4})(\d{3})(\d{4})$/', '$1 $2 $3', $user->mobile_number) : null,
            'email' => $user->email,
            'desired_position' => $details->desired_position,
            'objective' => $details->objective ?? $profile->summary,
            'experience' => $experience,
            // Fresh graduates and students lead with their schooling.
            'experience_first' => $experience !== [],
            'education' => $profile->education->map(fn (ApplicantEducation $e) => [
                'school' => $e->school,
                'course' => $e->course ?: $e->level->label(),
                'year' => $e->is_current
                    ? ($e->year_graduated ? "Expected {$e->year_graduated}" : 'Currently enrolled')
                    : ($e->year_graduated ? (string) $e->year_graduated : null),
            ])->all(),
            'trainings' => $profile->trainings->map(fn ($t) => [
                'title' => $t->title,
                'meta' => collect([
                    $t->provider,
                    $this->month($t->completed_at),
                    $t->hours ? "{$t->hours} ".Str::plural('hour', $t->hours) : null,
                ])->filter()->implode(' · '),
            ])->all(),
            'certifications' => $profile->certifications->map(fn ($c) => [
                'name' => $c->name,
                'meta' => collect([
                    $c->issuer,
                    $this->prefixed('Issued', $this->month($c->issued_at)),
                    $this->prefixed('Valid until', $this->month($c->expires_at)),
                ])->filter()->implode(' · '),
            ])->all(),
            'skills' => $profile->skills->pluck('name')->all(),
            'languages' => array_values(array_filter($details->languages ?? [])),
            'personal' => $this->personal($profile, $details),
            'references' => $references,
            'certify' => $details->include_certification,
        ];
    }

    /**
     * What the resume is still missing, so the seeker knows before sending it.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public function gaps(array $document): array
    {
        return array_values(array_filter([
            $document['mobile'] ? null : 'Add a mobile number in account settings so employers can call you.',
            $document['address'] ? null : 'Set your barangay in account settings.',
            filled($document['objective']) ? null : 'Write a short objective: the work you are looking for and what you offer.',
            $document['education'] ? null : 'Add your educational background on your profile.',
            $document['experience'] || $document['trainings'] ? null : 'Add work experience, or trainings and seminars you attended, on your profile.',
            $document['skills'] ? null : 'Add your skills on your profile.',
        ]));
    }

    public function pdf(User $user): string
    {
        return Pdf::loadView('resumes.standard', ['resume' => $this->document($user)])
            ->setPaper(self::PAPER)
            // Embed only the characters used, keeping the file small enough to email.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function fileName(User $user): string
    {
        return (Str::slug($user->name) ?: 'my').'-resume.pdf';
    }

    /**
     * Make the PDF the user's current resume, sent with their applications.
     */
    public function saveAsCurrent(User $user): Resume
    {
        return $this->resumes->storeGenerated($user, $this->pdf($user), $this->fileName($user));
    }

    private function profile(User $user): ApplicantProfile
    {
        $profile = $user->applicantProfile ?? $user->applicantProfile()->make();

        return $profile->loadMissing(['education', 'experience', 'trainings', 'certifications', 'skills']);
    }

    private function address(User $user, ApplicantProfile $profile): ?string
    {
        if ($user->barangay === null) {
            return null;
        }

        $municipality = $user->barangay->municipality;

        return collect([
            $profile->address_line,
            $user->barangay->name,
            $municipality?->name,
            $municipality?->province?->name,
        ])->filter()->implode(', ');
    }

    /**
     * Personal details are printed only when the seeker chooses to include
     * them; employers may not require age or birth date (RA 10911).
     *
     * @return array<string, string>
     */
    private function personal(ApplicantProfile $profile, ResumeDetail $details): array
    {
        $birthdate = $profile->date_of_birth;
        $gender = $profile->gender;

        return array_filter([
            'Date of birth' => $details->show_birthdate && $birthdate instanceof CarbonInterface
                ? $birthdate->format('F j, Y')." ({$birthdate->age} years old)"
                : null,
            'Sex' => $details->show_sex && $gender && $gender !== Gender::PreferNotToSay ? $gender->label() : null,
            'Civil status' => $details->civil_status?->label(),
            'Nationality' => $details->nationality,
        ]);
    }

    private function month(mixed $date): ?string
    {
        return $date instanceof CarbonInterface ? $date->format('M Y') : null;
    }

    private function prefixed(string $label, ?string $value): ?string
    {
        return $value === null ? null : "{$label} {$value}";
    }

    /**
     * One duty per line; bullets typed by the seeker are removed.
     *
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        return collect(preg_split('/\R/', (string) $text))
            ->map(fn ($line) => trim(preg_replace('/^\s*[-•*·]+\s*/u', '', $line)))
            ->filter()
            ->values()
            ->all();
    }
}
