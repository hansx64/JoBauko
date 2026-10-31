<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\UpdateInterestsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InterestController extends Controller
{
    use ResolvesApplicantProfile;

    public function update(UpdateInterestsRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $interests = collect($request->validated('interests'))
            ->map(fn (array $i) => ['category' => $i['category'], 'field' => filled($i['field'] ?? null) ? Str::squish($i['field']) : null])
            ->unique(fn (array $i) => $i['category'].'|'.mb_strtolower((string) $i['field']));

        DB::transaction(function () use ($profile, $interests) {
            $profile->interests()->delete();
            $profile->interests()->createMany($interests->all());
        });

        return back()->with('status', 'Interests saved.');
    }
}
