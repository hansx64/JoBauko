<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Seeker\Concerns\ResolvesApplicantProfile;
use App\Http\Requests\Seeker\TrainingRequest;
use App\Models\ApplicantTraining;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    use ResolvesApplicantProfile;

    public function store(TrainingRequest $request): RedirectResponse
    {
        $this->profile($request)->trainings()->create($request->validated());

        return back()->with('success', 'Training added.');
    }

    public function update(TrainingRequest $request, ApplicantTraining $training): RedirectResponse
    {
        $this->ensureOwned($request, $training);

        $training->update($request->validated());

        return back()->with('success', 'Training updated.');
    }

    public function destroy(Request $request, ApplicantTraining $training): RedirectResponse
    {
        $this->ensureOwned($request, $training);

        $training->delete();

        return back()->with('success', 'Training removed.');
    }
}
