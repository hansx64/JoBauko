<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Lifecycle actions. The rules for each transition live in
 * OpportunityService; this controller only authorizes and responds.
 */
class OpportunityStatusController extends Controller
{
    public function __construct(private readonly OpportunityService $opportunities) {}

    public function publish(Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $this->opportunities->publish($opportunity);

        return back()->with('success', "“{$opportunity->title}” is now published.");
    }

    public function unpublish(Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $this->opportunities->unpublish($opportunity);

        return back()->with('success', "“{$opportunity->title}” was moved back to drafts.");
    }

    public function close(Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $this->opportunities->close($opportunity);

        return back()->with('success', "Applications for “{$opportunity->title}” are closed.");
    }

    public function reopen(Request $request, Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $request->validate(['application_deadline' => ['required', 'date']]);

        $this->opportunities->reopen($opportunity, $request->input('application_deadline'));

        return back()->with('success', "“{$opportunity->title}” is accepting applications again.");
    }

    public function extend(Request $request, Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $request->validate(['application_deadline' => ['required', 'date']]);

        $this->opportunities->extendDeadline($opportunity, $request->input('application_deadline'));

        return back()->with('success', "The deadline for “{$opportunity->title}” was extended.");
    }

    public function archive(Opportunity $opportunity): RedirectResponse
    {
        Gate::authorize('manage', $opportunity);
        $this->opportunities->archive($opportunity);

        return back()->with('success', "“{$opportunity->title}” was archived.");
    }
}
