<?php

namespace App\Http\Controllers\Organization;

use App\Enums\OpportunityStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organization\Concerns\ResolvesOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesOrganization;

    /**
     * Organization overview. Application figures and charts are added with
     * applicant management.
     */
    public function __invoke(Request $request): Response
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

        return Inertia::render('organization/dashboard', [
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
        ]);
    }
}
