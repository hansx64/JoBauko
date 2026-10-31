<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only monitoring of applications across organizations. Administrators
 * see where each application stands, not its contents: documents, cover
 * letters and remarks stay with the organization the seeker applied to.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = match (true) {
            in_array($request->query('status'), ['active', 'stalled'], true) => $request->query('status'),
            default => ApplicationStatus::tryFrom((string) $request->query('status'))?->value,
        };
        $category = OpportunityCategory::tryFrom((string) $request->query('category'));
        $organization = $request->integer('organization') ?: null;
        $search = trim((string) $request->query('q'));

        $applications = $this->filtered(Application::query(), $status)
            ->with([
                'user' => fn ($q) => $q->withTrashed()->select('id', 'name'),
                'opportunity' => fn ($q) => $q->withTrashed()->select('id', 'organization_id', 'title', 'category')->with('organization:id,name'),
            ])
            ->withMax('statusHistory as last_activity_at', 'created_at')
            ->when($category, fn (Builder $q) => $q->whereHas('opportunity', fn (Builder $o) => $o->withTrashed()->where('category', $category)))
            ->when($organization, fn (Builder $q) => $q->whereHas('opportunity', fn (Builder $o) => $o->withTrashed()->where('organization_id', $organization)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereHas('user', fn (Builder $u) => $u->where('name', 'like', $like))
                    ->orWhereHas('opportunity', fn (Builder $o) => $o->withTrashed()->where('title', 'like', $like)));
            })
            ->latest('submitted_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Application $a) {
                $lastActivity = Carbon::parse($a->getAttribute('last_activity_at') ?? $a->submitted_at);

                return [
                    'id' => $a->id,
                    'applicant' => $a->user->name,
                    'user_id' => $a->user_id,
                    'opportunity' => $a->opportunity->title,
                    'opportunity_id' => $a->opportunity_id,
                    'organization' => $a->opportunity->organization?->name,
                    'category_label' => $a->opportunity->category->label(),
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                    'submitted_at' => $a->submitted_at->toIso8601String(),
                    'last_activity_at' => $lastActivity->toIso8601String(),
                    'stalled' => ! $a->status->isFinal() && $lastActivity->lt(now()->subDays(Application::STALLED_DAYS)),
                ];
            });

        $byStatus = Application::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'filters' => ['status' => $status, 'category' => $category?->value, 'organization' => $organization, 'q' => $search],
            'counts' => [
                'all' => (int) $byStatus->sum(),
                'active' => (int) collect(ApplicationStatus::active())->sum(fn (ApplicationStatus $s) => $byStatus[$s->value] ?? 0),
                'stalled' => Application::query()->stalled()->count(),
                ...collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]),
            ],
            'stalledDays' => Application::STALLED_DAYS,
            'options' => [
                'statuses' => ApplicationStatus::options(),
                'categories' => OpportunityCategory::options(),
                'organizations' => Organization::query()->whereHas('opportunities.applications')->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    private function filtered(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            null => $query,
            'active' => $query->whereIn('status', ApplicationStatus::active()),
            'stalled' => $query->stalled(),
            default => $query->where('status', $status),
        };
    }
}
