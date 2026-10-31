<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * How actively each organization publishes and how promptly it handles
 * applications. Response times are measured, not judged: the report does
 * not touch any organization's decisions.
 */
class OrganizationActivityReport extends Report
{
    public function key(): string
    {
        return 'organization-activity';
    }

    public function title(): string
    {
        return 'Organization activity';
    }

    public function description(): string
    {
        return 'Listings published, applications received and decided, and response times per organization.';
    }

    public function periodBasis(): string
    {
        return 'Publication date for listings; submission date for applications';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $published = DB::table('opportunities')->whereNull('deleted_at')->whereBetween('published_at', [$period->from, $period->to])
            ->selectRaw('organization_id, count(*) as total')->groupBy('organization_id')->pluck('total', 'organization_id');

        $applications = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->whereNull('applications.deleted_at')
            ->whereBetween('applications.submitted_at', [$period->from, $period->to])
            ->selectRaw('opportunities.organization_id,
                count(*) as received,
                sum(applications.first_reviewed_at is not null) as reviewed,
                sum(applications.status = ?) as approved,
                sum(applications.status = ?) as rejected,
                sum(applications.status = ? and applications.submitted_at < ?) as waiting,
                avg(case when applications.first_reviewed_at is not null then timestampdiff(hour, applications.submitted_at, applications.first_reviewed_at) end) as response_hours', [
                ApplicationStatus::Approved->value,
                ApplicationStatus::Rejected->value,
                ApplicationStatus::Submitted->value,
                now()->subDays(Application::STALLED_DAYS),
            ])
            ->groupBy('opportunities.organization_id')
            ->get()
            ->keyBy('organization_id');

        $ids = collect($published->keys())->merge($applications->keys())->unique();

        $rows = Organization::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'type', 'verification_status'])
            ->map(function (Organization $o) use ($published, $applications) {
                $a = $applications[$o->id] ?? null;

                return [
                    'organization' => $o->name,
                    'type' => $o->type->label(),
                    'status' => $o->verification_status->label(),
                    'published' => (int) ($published[$o->id] ?? 0),
                    'received' => (int) ($a->received ?? 0),
                    'reviewed' => (int) ($a->reviewed ?? 0),
                    'approved' => (int) ($a->approved ?? 0),
                    'rejected' => (int) ($a->rejected ?? 0),
                    'waiting' => (int) ($a->waiting ?? 0),
                    'response_days' => isset($a->response_hours) ? round($a->response_hours / 24, 1) : null,
                ];
            })
            ->sortByDesc('received')
            ->values();

        $responseHours = $applications->filter(fn ($a) => $a->response_hours !== null);
        $weightedHours = $responseHours->sum(fn ($a) => $a->response_hours * $a->reviewed);
        $reviewed = $responseHours->sum('reviewed');

        return new ReportResult(
            summary: [
                ['label' => 'Organizations that published', 'value' => $published->count()],
                ['label' => 'Organizations that received applications', 'value' => $applications->count()],
                ['label' => 'Average time to first review', 'value' => $this->days($reviewed > 0 ? $weightedHours / $reviewed : null)],
                ['label' => 'Applications waiting '.Application::STALLED_DAYS.'+ days for review', 'value' => (int) $rows->sum('waiting')],
            ],
            charts: [
                ['title' => 'Applications received', 'type' => 'ranked', 'rows' => $rows->take(15)->map(fn ($r) => ['label' => $r['organization'], 'value' => $r['received']])->all()],
            ],
            tables: [
                new ReportTable(
                    'By organization',
                    [
                        'organization' => 'Organization',
                        'type' => 'Type',
                        'status' => 'Verification',
                        'published' => 'Listings published',
                        'received' => 'Applications received',
                        'reviewed' => 'Reviewed',
                        'approved' => 'Approved',
                        'rejected' => 'Not approved',
                        'waiting' => 'Waiting '.Application::STALLED_DAYS.'+ days',
                        'response_days' => 'Avg. days to first review',
                    ],
                    $rows->all(),
                    ['published', 'received', 'reviewed', 'approved', 'rejected', 'waiting', 'response_days'],
                    '“Waiting” counts applications from the period still not opened for review after '.Application::STALLED_DAYS.' days.',
                ),
            ],
        );
    }
}
