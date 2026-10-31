<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\ExperienceRequest;
use App\Models\ApplicantExperience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    use ResolvesApplicantProfile;

    public function store(ExperienceRequest $request): RedirectResponse
    {
        $this->profile($request)->experience()->create($request->validated());

        return back()->with('status', 'Work experience added.');
    }

    public function update(ExperienceRequest $request, ApplicantExperience $experience): RedirectResponse
    {
        $this->ensureOwned($request, $experience);

        $experience->update($request->validated());

        return back()->with('status', 'Work experience updated.');
    }

    public function destroy(Request $request, ApplicantExperience $experience): RedirectResponse
    {
        $this->ensureOwned($request, $experience);

        $experience->delete();

        return back()->with('status', 'Work experience removed.');
    }
}
