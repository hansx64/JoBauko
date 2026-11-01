<?php

namespace App\Http\Controllers\Seeker;

use App\Enums\CivilStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seeker\ResumeDetailsRequest;
use App\Services\Resumes\ResumeBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResumeBuilderController extends Controller
{
    public function edit(Request $request, ResumeBuilder $builder): Response
    {
        $user = $request->user();
        $details = $builder->details($user);
        $document = $builder->document($user);
        $resume = $user->currentResume;

        return Inertia::render('seeker/resume-builder', [
            'details' => [
                'desired_position' => $details->desired_position,
                // Starts from "About me" on the profile until the seeker writes one.
                'objective' => $document['objective'],
                'languages' => $details->languages ?? [],
                'references' => $details->references ?? [],
                'civil_status' => $details->civil_status?->value,
                'nationality' => $details->nationality,
                'show_birthdate' => $details->show_birthdate,
                'show_sex' => $details->show_sex,
                'include_certification' => $details->include_certification,
            ],
            'contents' => [
                'name' => $document['name'],
                'address' => $document['address'],
                'mobile' => $document['mobile'],
                'email' => $document['email'],
                'experience' => count($document['experience']),
                'education' => count($document['education']),
                'trainings' => count($document['trainings']),
                'certifications' => count($document['certifications']),
                'skills' => count($document['skills']),
                'has_birthdate' => $user->applicantProfile?->date_of_birth !== null,
                'has_sex' => $user->applicantProfile?->gender !== null,
            ],
            'gaps' => $builder->gaps($document),
            'currentResume' => $resume ? [
                'original_name' => $resume->original_name,
                'is_generated' => $resume->is_generated,
                'saved_at' => $resume->created_at->toIso8601String(),
            ] : null,
            'options' => [
                'civilStatuses' => CivilStatus::options(),
            ],
        ]);
    }

    public function update(ResumeDetailsRequest $request): RedirectResponse
    {
        $request->user()->resumeDetail()->updateOrCreate([], $request->details());

        return back()->with('success', 'Resume details saved.');
    }

    /**
     * The resume as a PDF, to preview or download. It is not stored.
     */
    public function pdf(Request $request, ResumeBuilder $builder): HttpResponse
    {
        $user = $request->user();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($builder->pdf($user), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$builder->fileName($user).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Save the PDF as the seeker's current resume, sent with applications.
     */
    public function store(Request $request, ResumeBuilder $builder): RedirectResponse
    {
        $builder->saveAsCurrent($request->user());

        return to_route('seeker.profile.edit')
            ->with('success', 'Your new resume is saved. It will be sent with your applications.');
    }
}
