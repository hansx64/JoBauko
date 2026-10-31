<?php

namespace App\Http\Controllers\Seeker;

use App\Enums\ResumeExtractionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seeker\UploadResumeRequest;
use App\Models\Resume;
use App\Services\Resumes\ResumeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeController extends Controller
{
    public function store(UploadResumeRequest $request, ResumeService $resumes): RedirectResponse
    {
        $resume = $resumes->upload($request->user(), $request->file('resume'));

        if ($resume->extraction_status === ResumeExtractionStatus::Extracted) {
            return to_route('seeker.resume.review', $resume)
                ->with('success', 'Resume uploaded. Review what we found before adding it to your profile.');
        }

        return to_route('seeker.profile.edit')->with('success', 'Resume uploaded.');
    }

    /**
     * Stream the file. PDFs can be previewed inline; everything else downloads.
     */
    public function show(Request $request, Resume $resume): StreamedResponse
    {
        Gate::authorize('view', $resume);

        $inline = $request->boolean('preview') && $resume->isPdf();

        return Storage::disk(ResumeService::DISK)->response(
            $resume->disk_path,
            $resume->original_name,
            [
                'Content-Type' => $resume->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    /**
     * Read the resume again, e.g. after a failed attempt.
     */
    public function extract(Resume $resume, ResumeService $resumes): RedirectResponse
    {
        Gate::authorize('update', $resume);

        $resumes->extract($resume);

        return $resume->refresh()->extraction_status === ResumeExtractionStatus::Extracted
            ? to_route('seeker.resume.review', $resume)
            : back()->with('success', 'We still could not read this resume. You can fill in your profile by hand.');
    }

    public function destroy(Resume $resume, ResumeService $resumes): RedirectResponse
    {
        Gate::authorize('delete', $resume);

        $resumes->remove($resume);

        return to_route('seeker.profile.edit')->with('success', 'Resume removed.');
    }
}
