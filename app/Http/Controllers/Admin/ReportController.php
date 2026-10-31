<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OpportunityStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Report;
use App\Services\Moderation\ReportService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = ReportStatus::tryFrom((string) $request->query('status')) ?? ReportStatus::Open;

        $reports = Report::query()
            ->where('status', $status)
            ->with(['reporter:id,name', 'resolver:id,name', 'reportable' => fn (MorphTo $morph) => $morph->morphWith([Opportunity::class => ['organization:id,name']])])
            // The queue is worked oldest first; history reads newest first.
            ->orderBy('created_at', $status === ReportStatus::Open ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Report $r) => self::row($r));

        return Inertia::render('admin/reports/index', [
            'reports' => $reports,
            'filters' => ['status' => $status->value],
            'counts' => Report::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => ReportStatus::options(),
        ]);
    }

    public function show(Report $report): Response
    {
        $report->load(['reporter:id,name,email', 'resolver:id,name']);
        $opportunity = $report->reportable;

        if ($opportunity instanceof Opportunity) {
            $opportunity->load('organization:id,name');
        }

        return Inertia::render('admin/reports/show', [
            'report' => [
                ...self::row($report),
                'reporter_email' => $report->reporter?->email,
                'resolution_notes' => $report->resolution_notes,
            ],
            'opportunity' => $opportunity instanceof Opportunity ? [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'slug' => $opportunity->slug,
                'organization' => $opportunity->organization->name,
                'organization_id' => $opportunity->organization_id,
                'status' => $opportunity->displayStatus(),
                'status_label' => $opportunity->displayStatusLabel(),
                'is_public' => Opportunity::query()->visible()->whereKey($opportunity->id)->exists(),
                'can_suspend' => $opportunity->status !== OpportunityStatus::Suspended && $opportunity->status !== OpportunityStatus::Archived,
            ] : null,
            'related' => Report::query()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->whereKeyNot($report->id)
                ->with(['reporter:id,name', 'resolver:id,name'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Report $r) => self::row($r->setRelation('reportable', $opportunity))),
        ]);
    }

    public function decide(Request $request, Report $report, ReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(ReportService::DECISIONS)],
            'notes' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'notes.required' => 'Explain the decision. When suspending, this is the reason sent to the organization.',
        ]);

        $reports->decide($report, $request->user(), $validated['decision'], $validated['notes']);

        return to_route('admin.reports.index')->with('success', match ($validated['decision']) {
            'suspend' => 'The opportunity was suspended and its open reports were closed.',
            'resolve' => 'The open reports on this opportunity were marked as resolved.',
            'dismiss' => 'The open reports on this opportunity were dismissed.',
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Report $report): array
    {
        $target = $report->reportable;

        return [
            'id' => $report->id,
            'reason' => $report->reason->value,
            'reason_label' => $report->reason->label(),
            'details' => $report->details,
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'outcome' => $report->outcome,
            'reporter' => $report->reporter?->name,
            'resolver' => $report->resolver?->name,
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'created_at' => $report->created_at->toIso8601String(),
            'target' => $target instanceof Opportunity
                ? ['id' => $target->id, 'title' => $target->title, 'organization' => $target->organization?->name]
                : null,
        ];
    }
}
