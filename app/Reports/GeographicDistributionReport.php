<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Enums\ApplicationStatus;
use App\Models\Barangay;
use Illuminate\Support\Facades\DB;

/**
 * Seekers, opportunities and outcomes per Bauko barangay, so outreach can
 * target the barangays that are under-served.
 */
class GeographicDistributionReport extends Report
{
    public function key(): string
    {
        return 'geographic-distribution';
    }

    public function title(): string
    {
        return 'Geographic distribution';
    }

    public function description(): string
    {
        return 'Seekers, opportunities, applications and approvals in each of the 22 barangays of Bauko.';
    }

    public function periodBasis(): string
    {
        return 'Account, publication, submission and approval dates';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $seekers = DB::table('users')->whereNull('deleted_at')->where('account_type', AccountType::Seeker->value);
        $seekersTotal = (clone $seekers)->where('created_at', '<=', $period->to)->selectRaw('barangay_id, count(*) as total')->groupBy('barangay_id')->pluck('total', 'barangay_id');
        $seekersNew = (clone $seekers)->whereBetween('created_at', [$period->from, $period->to])->selectRaw('barangay_id, count(*) as total')->groupBy('barangay_id')->pluck('total', 'barangay_id');
        $opportunities = DB::table('opportunities')->whereNull('deleted_at')->whereBetween('published_at', [$period->from, $period->to])
            ->selectRaw("case when is_remote then 'remote' else coalesce(barangay_id, 'elsewhere') end as place, count(*) as total")
            ->groupBy('place')->pluck('total', 'place');
        $residents = fn (string $dateColumn, ?ApplicationStatus $status = null) => DB::table('applications')
            ->join('users', 'users.id', '=', 'applications.user_id')
            ->whereNull('applications.deleted_at')
            ->when($status, fn ($q) => $q->where('applications.status', $status->value))
            ->whereBetween("applications.{$dateColumn}", [$period->from, $period->to])
            ->selectRaw('users.barangay_id, count(*) as total')
            ->groupBy('users.barangay_id')
            ->pluck('total', 'barangay_id');
        $applications = $residents('submitted_at');
        $approved = $residents('decided_at', ApplicationStatus::Approved);

        $rows = Barangay::orderBy('name')->get(['id', 'name'])->map(fn (Barangay $b) => [
            'barangay' => $b->name,
            'seekers' => (int) ($seekersTotal[$b->id] ?? 0),
            'new_seekers' => (int) ($seekersNew[$b->id] ?? 0),
            'opportunities' => (int) ($opportunities[$b->id] ?? 0),
            'applications' => (int) ($applications[$b->id] ?? 0),
            'approved' => (int) ($approved[$b->id] ?? 0),
        ])->all();

        $unstated = [
            'barangay' => 'Not stated',
            'seekers' => (int) ($seekersTotal[''] ?? 0),
            'new_seekers' => (int) ($seekersNew[''] ?? 0),
            'opportunities' => 0,
            'applications' => (int) ($applications[''] ?? 0),
            'approved' => (int) ($approved[''] ?? 0),
        ];

        if (array_sum(array_slice($unstated, 1)) > 0) {
            $rows[] = $unstated;
        }

        $underserved = collect($rows)->where('barangay', '!=', 'Not stated')->where('opportunities', 0)->count();

        return new ReportResult(
            summary: [
                ['label' => 'Registered seekers (end of period)', 'value' => (int) $seekersTotal->sum()],
                ['label' => 'Opportunities located in Bauko barangays', 'value' => (int) $opportunities->except(['remote', 'elsewhere'])->sum()],
                ['label' => 'Remote or outside Bauko', 'value' => (int) ($opportunities['remote'] ?? 0) + (int) ($opportunities['elsewhere'] ?? 0)],
                ['label' => 'Barangays with no local opportunity', 'value' => $underserved],
            ],
            charts: [
                ['title' => 'Registered seekers by barangay', 'type' => 'ranked', 'rows' => collect($rows)->map(fn ($r) => ['label' => $r['barangay'], 'value' => $r['seekers']])->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable(
                    'By barangay',
                    [
                        'barangay' => 'Barangay',
                        'seekers' => 'Seekers (total)',
                        'new_seekers' => 'New seekers',
                        'opportunities' => 'Opportunities located here',
                        'applications' => 'Applications by residents',
                        'approved' => 'Residents approved',
                    ],
                    $rows,
                    ['seekers', 'new_seekers', 'opportunities', 'applications', 'approved'],
                    'Residence is the barangay in the seeker’s account. Seekers (total) counts everyone registered by the end of the period.',
                ),
            ],
        );
    }
}
