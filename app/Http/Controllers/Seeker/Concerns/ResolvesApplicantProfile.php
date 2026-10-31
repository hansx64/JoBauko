<?php

namespace App\Http\Controllers\Seeker\Concerns;

use App\Enums\Classification;
use App\Models\ApplicantProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesApplicantProfile
{
    protected function profile(Request $request): ApplicantProfile
    {
        return $request->user()->applicantProfile()->firstOrCreate([], [
            'classification' => Classification::Other,
        ]);
    }

    /**
     * Respond 404 (not 403) for entries of another seeker, so their
     * existence isn't revealed.
     */
    protected function ensureOwned(Request $request, Model $entry): void
    {
        abort_unless((int) $entry->getAttribute('applicant_profile_id') === (int) $this->profile($request)->id, 404);
    }
}
