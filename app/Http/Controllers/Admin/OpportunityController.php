<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Models\Report;
use App\Services\Opportunities\OpportunityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    private const FILTERS = ['all', 'reported', 'published', 'expired', 'draft', 'closed', 'archived', 'suspended'];

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'all';
        $category = OpportunityCategory::tryFrom((string) $request->query('category'));
        $search = trim((string) $request->query('q'));

        $opportunities = $this->filtered(Opportunity::query(), $filter)
            ->with('organization:id,name')
            ->withCount(['applications', 'reports as open_reports_count' => fn (Builder $q) => $q->open()])
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%')
                ->orWhereHas('organization', fn (Builder $o) => $o->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))))
            ->orderByDesc('open_reports_count')
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Opportunity $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'organization' => $o->organization->name,
                'organization_id' => $o->organization_id,
                'category' => $o->category->value,
                'category_label' => $o->category->label(),
                'status' => $o->displayStatus(),
                'status_label' => $o->displayStatusLabel(),
                'application_deadline' => $o->application_deadline?->toDateString(),
                'applications_count' => $o->applications_count,
                'open_reports_count' => (int) $o->getAttribute('open_reports_count'),
            ]);

        return Inertia::render('admin/opportunities/index', [
            'opportunities' => $opportunities,
            'filters' => ['status' => $filter, 'category' => $category?->value, 'q' => $search],
            'counts' => collect(self::FILTERS)->mapWithKeys(fn (string $f) => [$f => $this->filtered(Opportunity::query(), $f)->count()]),
            'categories' => OpportunityCategory::options(),
        ]);
    }

    public function show(Opportunity $opportunity): Response
    {
        $opportunity->load(['organization', 'barangay', 'creator:id,name', 'suspender:id,name']);

        $byStatus = $opportunity->applications()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/opportunities/show', [
            'opportunity' => [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'slug' => $opportunity->slug,
                'organization' => ['id' => $opportunity->organization->id, 'name' => $opportunity->organization->name, 'verification_label' => $opportunity->organization->verification_status->label()],
                'category_label' => $opportunity->category->label(),
                'status' => $opportunity->displayStatus(),
                'status_label' => $opportunity->displayStatusLabel(),
                'stored_status' => $opportunity->status->value,
                'is_public' => Opportunity::query()->visible()->whereKey($opportunity->id)->exists(),
                'location' => $opportunity->is_remote ? 'Remote' : ($opportunity->barangay ? "Brgy. {$opportunity->barangay->name}, Bauko" : $opportunity->location_text),
                'application_deadline' => $opportunity->application_deadline?->toDateString(),
                'published_at' => $opportunity->published_at?->toIso8601String(),
                'created_at' => $opportunity->created_at->toIso8601String(),
                'created_by' => $opportunity->creator?->name,
                'slots' => $opportunity->slots,
                'description' => $opportunity->description,
                'suspension' => $opportunity->status === OpportunityStatus::Suspended ? [
                    'reason' => $opportunity->suspension_reason,
                    'at' => $opportunity->suspended_at?->toIso8601String(),
                    'by' => $opportunity->suspender?->name,
                    'previous_label' => $opportunity->status_before_suspension?->label(),
                ] : null,
            ],
            'applications' => [
                'total' => (int) $byStatus->sum(),
                'byStatus' => collect(ApplicationStatus::cases())
                    ->map(fn (ApplicationStatus $s) => ['status' => $s->value, 'label' => $s->label(), 'count' => (int) ($byStatus[$s->value] ?? 0)])
                    ->filter(fn (array $row) => $row['count'] > 0)
                    ->values(),
            ],
            'reports' => $opportunity->reports()
                ->with(['reporter:id,name', 'resolver:id,name'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Report $r) => ReportController::row($r->setRelation('reportable', $opportunity))),
            'history' => AuditLog::query()
                ->where('auditable_type', $opportunity->getMorphClass())
                ->where('auditable_id', $opportunity->id)
                ->whereNot('action', 'opportunity.updated')
                ->with('user:id,name')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'by' => $log->user?->name,
                    'reason' => $log->new_values['suspension_reason'] ?? null,
                    'at' => $log->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function suspend(Request $request, Opportunity $opportunity, OpportunityService $opportunities): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        $opportunities->suspend($opportunity, $request->user(), $validated['reason']);

        return back()->with('success', "“{$opportunity->title}” has been suspended.");
    }

    public function reinstate(Opportunity $opportunity, OpportunityService $opportunities): RedirectResponse
    {
        $opportunities->reinstate($opportunity);

        return back()->with('success', "“{$opportunity->title}” has been reinstated.");
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    private function filtered(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'all' => $query,
            'reported' => $query->whereHas('reports', fn (Builder $q) => $q->open()),
            'published' => $query->where('status', OpportunityStatus::Published)->whereDate('application_deadline', '>=', today()),
            'expired' => $query->where('status', OpportunityStatus::Published)->whereDate('application_deadline', '<', today()),
            default => $query->where('status', $filter),
        };
    }
}
