<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\EducationRequest;
use App\Models\ApplicantEducation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EducationController extends Controller
{
    use ResolvesApplicantProfile;

    public function store(EducationRequest $request): RedirectResponse
    {
        $this->profile($request)->education()->create($request->validated());

        return back()->with('success', 'Education added.');
    }

    public function update(EducationRequest $request, ApplicantEducation $education): RedirectResponse
    {
        $this->ensureOwned($request, $education);

        $education->update($request->validated());

        return back()->with('success', 'Education updated.');
    }

    public function destroy(Request $request, ApplicantEducation $education): RedirectResponse
    {
        $this->ensureOwned($request, $education);

        $education->delete();

        return back()->with('success', 'Education removed.');
    }
}
