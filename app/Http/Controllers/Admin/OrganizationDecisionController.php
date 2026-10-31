<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organizations\OrganizationVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationDecisionController extends Controller
{
    public function __construct(private readonly OrganizationVerificationService $verification) {}

    public function approve(Request $request, Organization $organization): RedirectResponse
    {
        $this->verification->approve($organization, $request->user());

        return back()->with('success', "{$organization->name} is now verified.");
    }

    public function reject(Request $request, Organization $organization): RedirectResponse
    {
        $this->verification->reject($organization, $request->user(), $this->reason($request));

        return back()->with('success', "The verification request of {$organization->name} was rejected.");
    }

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $this->verification->suspend($organization, $request->user(), $this->reason($request));

        return back()->with('success', "{$organization->name} has been suspended.");
    }

    public function reinstate(Request $request, Organization $organization): RedirectResponse
    {
        $this->verification->reinstate($organization, $request->user());

        return back()->with('success', "{$organization->name} has been reinstated.");
    }

    /**
     * Rejections and suspensions must explain themselves to the organization.
     */
    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
    }
}
