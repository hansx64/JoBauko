<?php

namespace App\Http\Controllers\Organization;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Applications\ApplicationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Status changes and internal remarks on an application.
 */
class ApplicationReviewController extends Controller
{
    public function status(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $application);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ApplicationStatus::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = ApplicationStatus::from($validated['status']);

        Gate::authorize('changeStatus', [$application, $status]);

        $workflow->transition($application, $status, $request->user(), $validated['remarks'] ?? null);

        return back()->with('success', "Status changed to {$status->label()}. The applicant has been notified.");
    }

    public function remark(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('addRemark', $application);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $workflow->addRemark($application, $request->user(), $validated['body']);

        return back()->with('success', 'Remark added. Only your organization can see it.');
    }
}
