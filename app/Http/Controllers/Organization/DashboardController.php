<?php

namespace App\Http\Controllers\Organization;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Support\DailyCounts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesOrganization;

    /**
     * Organization overview: opportunity counts, application figures and
     * charts, and what needs attention.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('organization/dashboard', [
            ...$this->opportunityOverview($request),
            'applications' => $request->user()->organizationMembership->hasPermission(OrganizationPermission::ViewApplications)
                ? $this->applicationOverview($this->organization($request))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationOverview(Organization $organization): array
    {
        $applications = Application::query()
            ->whereIn('opportunity_id', $organization->opportunities()->withTrashed()->select('id'));

        $byStatus = (clone $applications)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $perDay = DailyCounts::lastDays((clone $applications)->toBase(), 'submitted_at');

        return [
            'total' => (int) $byStatus->sum(),
            'awaitingReview' => (int) ($byStatus[ApplicationStatus::Submitted->value] ?? 0),
            'inProgress' => (int) collect(ApplicationStatus::active())->sum(fn (ApplicationStatus $s) => $byStatus[$s->value] ?? 0),
            'approved' => (int) ($byStatus[ApplicationStatus::Approved->value] ?? 0),
            'last30Days' => array_sum(array_column($perDay, 'count')),
            'perDay' => $perDay,
            'byStatus' => collect(ApplicationStatus::cases())
                ->map(fn (ApplicationStatus $s) => ['status' => $s->value, 'label' => $s->label(), 'count' => (int) ($byStatus[$s->value] ?? 0)])
                ->filter(fn (array $row) => $row['count'] > 0)
                ->sortByDesc('count')
                ->values(),
            'topOpportunities' => $organization->opportunities()
                ->withCount([
                    'applications',
                    'applications as new_applications_count' => fn ($q) => $q->where('status', ApplicationStatus::Submitted),
                ])
                ->having('applications_count', '>', 0)
                ->orderByDesc('applications_count')
                ->limit(5)
                ->get(['id', 'title'])
                ->map(fn (Opportunity $o) => [
                    'id' => $o->id,
                    'title' => $o->title,
                    'total' => (int) $o->getAttribute('applications_count'),
                    'new' => (int) $o->getAttribute('new_applications_count'),
                ]),
            'recent' => (clone $applications)
                ->with(['user' => fn ($q) => $q->withTrashed(), 'opportunity' => fn ($q) => $q->withTrashed()])
                ->latest('submitted_at')
                ->limit(5)
                ->get()
                ->map(fn (Application $a) => [
                    'id' => $a->id,
                    'name' => $a->user->name,
                    'opportunity' => $a->opportunity->title,
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                    'submitted_at' => $a->submitted_at->toIso8601String(),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opportunityOverview(Request $request): array
    {
        $organization = $this->organization($request);
        $opportunities = $organization->opportunities();

        $byStatus = (clone $opportunities)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $expired = (clone $opportunities)
            ->where('status', OpportunityStatus::Published)
            ->whereDate('application_deadline', '<', today())
            ->count();

        $published = (int) ($byStatus[OpportunityStatus::Published->value] ?? 0);

        return [
            'organization' => $this->organizationSummary($organization),
            'stats' => [
                'published' => $published,
                'active' => $published - $expired,
                'expired' => $expired,
                'drafts' => (int) ($byStatus[OpportunityStatus::Draft->value] ?? 0),
                'closed' => (int) ($byStatus[OpportunityStatus::Closed->value] ?? 0),
                'archived' => (int) ($byStatus[OpportunityStatus::Archived->value] ?? 0),
            ],
            'upcomingDeadlines' => (clone $opportunities)
                ->open()
                ->orderBy('application_deadline')
                ->limit(5)
                ->get(['id', 'title', 'category', 'application_deadline'])
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'title' => $o->title,
                    'category' => $o->category->label(),
                    'deadline' => $o->application_deadline->toDateString(),
                ]),
        ];
    }
}
