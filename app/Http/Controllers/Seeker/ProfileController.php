<?php

namespace App\Http\Controllers\Seeker;

use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EducationStatus;
use App\Enums\Gender;
use App\Enums\InterestCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\UpdatePersonalInfoRequest;
use App\Models\Skill;
use App\Services\Profiles\ProfileCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use ResolvesApplicantProfile;

    public function edit(Request $request, ProfileCompletion $completion): Response
    {
        $profile = $this->profile($request)->load(['education', 'experience', 'certifications', 'trainings', 'skills', 'interests']);
        $resume = $request->user()->currentResume;

        return Inertia::render('seeker/profile', [
            'personal' => [
                'classification' => $profile->classification->value,
                'date_of_birth' => $profile->date_of_birth?->toDateString(),
                'gender' => $profile->gender?->value,
                'address_line' => $profile->address_line,
                'highest_education' => $profile->highest_education?->value,
                'current_education_status' => $profile->current_education_status?->value,
                'summary' => $profile->summary,
            ],
            'education' => $profile->education->map(fn ($e) => [
                'id' => $e->id,
                'level' => $e->level->value,
                'level_label' => $e->level->label(),
                'school' => $e->school,
                'course' => $e->course,
                'year_graduated' => $e->year_graduated,
                'is_current' => $e->is_current,
            ]),
            'experience' => $profile->experience->map->only(['id', 'position', 'company', 'industry', 'start_date', 'end_date', 'description']),
            'certifications' => $profile->certifications->map->only(['id', 'name', 'issuer', 'issued_at', 'expires_at']),
            'trainings' => $profile->trainings->map->only(['id', 'title', 'provider', 'completed_at', 'hours']),
            'skills' => $profile->skills->pluck('name'),
            'interests' => $profile->interests->map(fn ($i) => ['category' => $i->category->value, 'field' => $i->field]),
            'resume' => $resume ? [
                'id' => $resume->id,
                'original_name' => $resume->original_name,
                'size_bytes' => $resume->size_bytes,
                'is_pdf' => $resume->isPdf(),
                'uploaded_at' => $resume->created_at->toIso8601String(),
                'extraction_status' => $resume->extraction_status->value,
                'extraction_label' => $resume->extraction_status->label(),
                'extraction_error' => $resume->extracted_data['error'] ?? null,
            ] : null,
            'completion' => $completion->for($request->user()),
            'skillSuggestions' => Skill::orderBy('name')->pluck('name'),
            'options' => [
                'classifications' => Classification::options(),
                'genders' => Gender::options(),
                'educationLevels' => EducationLevel::options(),
                'educationStatuses' => EducationStatus::options(),
                'interestCategories' => InterestCategory::options(),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(UpdatePersonalInfoRequest $request): RedirectResponse
    {
        $this->profile($request)->update($request->validated());

        return back()->with('status', 'Personal information saved.');
    }
}
