<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\Classification;
use App\Enums\OpportunityCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applications submitted in the period and what has happened to them.
 */
class ApplicationsReport extends Report
{
    public function key(): string
    {
        return 'applications';
    }

    public function title(): string
    {
        return 'Applications';
    }

    public function description(): string
    {
        return 'Applications by status, category and applicant classification, with approval rates and response times.';
    }

    public function periodBasis(): string
    {
        return 'Date the application was submitted';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $applications = fn (): Builder => DB::table('applications')
            ->whereNull('applications.deleted_at')
            ->whereBetween('applications.submitted_at', [$period->from, $period->to]);

        $byStatus = $applications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $byStatus->sum();
        $approved = (int) ($byStatus[ApplicationStatus::Approved->value] ?? 0);
        $rejected = (int) ($byStatus[ApplicationStatus::Rejected->value] ?? 0);
        $responseHours = $applications()->whereNotNull('first_reviewed_at')->selectRaw('avg(timestampdiff(hour, submitted_at, first_reviewed_at)) as hours')->value('hours');
        $monthly = $applications()->selectRaw(self::monthExpression('submitted_at').' as month, count(*) as total')->groupBy('month')->pluck('total', 'month');

        $byCategory = $applications()
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->selectRaw('opportunities.category as grp, applications.status, count(*) as total')
            ->groupBy('grp', 'applications.status')
            ->get();
        $byClassification = $applications()
            ->join('applicant_profiles', 'applicant_profiles.user_id', '=', 'applications.user_id')
            ->selectRaw('applicant_profiles.classification as grp, applications.status, count(*) as total')
            ->groupBy('grp', 'applications.status')
            ->get();

        return new ReportResult(
            summary: [
                ['label' => 'Applications submitted', 'value' => $total],
                ['label' => 'Approved', 'value' => $approved],
                ['label' => 'Approval rate', 'value' => $this->percent($approved, $approved + $rejected)],
                ['label' => 'Average time to first review', 'value' => $this->days($responseHours)],
            ],
            charts: [
                ['title' => 'Submitted per month', 'type' => 'monthly', 'unit' => ['application', 'applications'], 'rows' => $this->monthly($period, $monthly)],
                ['title' => 'By status', 'type' => 'ranked', 'rows' => collect(ApplicationStatus::cases())
                    ->map(fn (ApplicationStatus $s) => ['label' => $s->label(), 'value' => (int) ($byStatus[$s->value] ?? 0)])
                    ->filter(fn (array $r) => $r['value'] > 0)->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable('By status', ['status' => 'Status', 'total' => 'Applications', 'share' => 'Share'], array_map(fn (ApplicationStatus $s) => [
                    'status' => $s->label(),
                    'total' => (int) ($byStatus[$s->value] ?? 0),
                    'share' => $this->percent((int) ($byStatus[$s->value] ?? 0), $total),
                ], ApplicationStatus::cases()), ['total']),
                $this->outcomeTable('By opportunity category', 'Category', $byCategory, array_map(fn (OpportunityCategory $c) => [$c->value, $c->label()], OpportunityCategory::cases())),
                $this->outcomeTable('By applicant classification', 'Classification', $byClassification, array_map(fn (Classification $c) => [$c->value, $c->label()], Classification::cases())),
            ],
        );
    }

    /**
     * @param  Collection<int, object>  $rows  grp, status, total
     * @param  list<array{0: string, 1: string}>  $groups  value, label
     */
    private function outcomeTable(string $title, string $heading, Collection $rows, array $groups): ReportTable
    {
        return new ReportTable(
            $title,
            ['group' => $heading, 'total' => 'Applications', 'in_progress' => 'In progress', 'approved' => 'Approved', 'rejected' => 'Not approved', 'withdrawn' => 'Withdrawn', 'rate' => 'Approval rate'],
            array_map(function (array $group) use ($rows) {
                $mine = $rows->where('grp', $group[0]);
                $count = fn (ApplicationStatus $s) => (int) $mine->where('status', $s->value)->sum('total');
                $approved = $count(ApplicationStatus::Approved);
                $rejected = $count(ApplicationStatus::Rejected);

                return [
                    'group' => $group[1],
                    'total' => (int) $mine->sum('total'),
                    'in_progress' => (int) collect(ApplicationStatus::active())->sum($count),
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'withdrawn' => $count(ApplicationStatus::Withdrawn),
                    'rate' => $this->percent($approved, $approved + $rejected),
                ];
            }, $groups),
            ['total', 'in_progress', 'approved', 'rejected', 'withdrawn'],
            'Approval rate is approved out of decided (approved plus not approved) applications.',
        );
    }
}
