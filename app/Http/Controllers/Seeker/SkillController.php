<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\UpdateSkillsRequest;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    use ResolvesApplicantProfile;

    /**
     * Replace the seeker's skills, keeping the recorded source (manual or
     * resume) of skills they already had.
     */
    public function update(UpdateSkillsRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);

        DB::transaction(function () use ($profile, $request) {
            $sources = $profile->skills()->pluck('applicant_skills.source', 'skills.id');

            $sync = collect($request->validated('skills'))
                ->map(fn (string $name) => Skill::findOrCreateByName($name))
                ->mapWithKeys(fn (Skill $skill) => [$skill->id => ['source' => $sources[$skill->id] ?? 'manual']]);

            $profile->skills()->sync($sync->all());
        });

        return back()->with('status', 'Skills saved.');
    }
}
