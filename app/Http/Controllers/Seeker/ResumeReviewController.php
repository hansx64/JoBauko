<?php

namespace App\Http\Controllers\Seeker;

use App\Enums\EducationLevel;
use App\Enums\ResumeExtractionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\ApplyResumeReviewRequest;
use App\Models\Resume;
use App\Services\Resumes\ResumeReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResumeReviewController extends Controller
{
    use ResolvesApplicantProfile;

    public function show(Request $request, Resume $resume): Response|RedirectResponse
    {
        Gate::authorize('update', $resume);

        if (! in_array($resume->extraction_status, [ResumeExtractionStatus::Extracted, ResumeExtractionStatus::Reviewed], true)) {
            return to_route('seeker.profile.edit');
        }

        $existingSkills = $this->profile($request)->skills()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $data = $resume->extracted_data;

        return Inertia::render('seeker/resume-review', [
            'resume' => [
                'id' => $resume->id,
                'original_name' => $resume->original_name,
                'reviewed' => $resume->extraction_status === ResumeExtractionStatus::Reviewed,
            ],
            'extracted' => [
                // Skills already on the profile are not suggested again.
                'skills' => array_values(array_filter(
                    $data['skills'] ?? [],
                    fn (string $s) => ! in_array(mb_strtolower($s), $existingSkills, true),
                )),
                'education' => $data['education'] ?? [],
                'experience' => $data['experience'] ?? [],
                'certifications' => $data['certifications'] ?? [],
                'trainings' => $data['trainings'] ?? [],
                'keywords' => $data['keywords'] ?? [],
            ],
            'educationLevels' => EducationLevel::options(),
        ]);
    }

    public function store(ApplyResumeReviewRequest $request, Resume $resume, ResumeReviewService $review): RedirectResponse
    {
        Gate::authorize('update', $resume);

        $review->apply($resume, $this->profile($request), $request->validated());

        return to_route('seeker.profile.edit')->with('success', 'Your profile has been updated from your resume.');
    }
}
