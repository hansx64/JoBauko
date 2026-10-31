<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Support\OpportunityCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedOpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $saved = $request->user()->savedOpportunities()
            ->visible()
            ->with(['organization', 'barangay', 'jobDetail'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return Inertia::render('seeker/saved', [
            'opportunities' => $saved->map(fn (Opportunity $o) => OpportunityCard::make($o, saved: true)),
            // Saved listings that were withdrawn since (archived, suspended, ...).
            'unavailableCount' => $request->user()->savedOpportunities()->count() - $saved->count(),
        ]);
    }

    public function store(Request $request, Opportunity $opportunity): RedirectResponse
    {
        abort_unless(Opportunity::query()->visible()->whereKey($opportunity->id)->exists(), 404);

        $request->user()->savedOpportunities()->syncWithoutDetaching([$opportunity->id]);

        return back()->with('success', 'Saved. Find it under Saved opportunities.');
    }

    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $request->user()->savedOpportunities()->detach($opportunity->id);

        return back()->with('success', 'Removed from your saved opportunities.');
    }
}
