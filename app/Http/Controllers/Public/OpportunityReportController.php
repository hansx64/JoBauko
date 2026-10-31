<?php

namespace App\Http\Controllers\Public;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Services\Moderation\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpportunityReportController extends Controller
{
    public function store(Request $request, Opportunity $opportunity, ReportService $reports): RedirectResponse
    {
        abort_unless(Opportunity::query()->visible()->whereKey($opportunity->id)->exists(), 404);
        // Administrators act on listings directly instead of reporting them.
        abort_if($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => [
                Rule::requiredIf(fn () => ReportReason::tryFrom((string) $request->input('reason'))?->requiresDetails() ?? false),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'details.required' => 'Please describe the problem so an administrator can check it.',
        ]);

        $reports->submit($request->user(), $opportunity, ReportReason::from($validated['reason']), $validated['details'] ?? null);

        return back()->with('success', 'Thank you. An administrator will review your report.');
    }
}
