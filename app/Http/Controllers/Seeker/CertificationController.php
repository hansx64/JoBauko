<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\CertificationRequest;
use App\Models\ApplicantCertification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    use ResolvesApplicantProfile;

    public function store(CertificationRequest $request): RedirectResponse
    {
        $this->profile($request)->certifications()->create($request->validated());

        return back()->with('success', 'Certification added.');
    }

    public function update(CertificationRequest $request, ApplicantCertification $certification): RedirectResponse
    {
        $this->ensureOwned($request, $certification);

        $certification->update($request->validated());

        return back()->with('success', 'Certification updated.');
    }

    public function destroy(Request $request, ApplicantCertification $certification): RedirectResponse
    {
        $this->ensureOwned($request, $certification);

        $certification->delete();

        return back()->with('success', 'Certification removed.');
    }
}
