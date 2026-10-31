<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Models\Application;

/**
 * People whose applications were approved in the period: the municipality's
 * record of who was hired, granted a scholarship or enrolled in a program.
 * Lists individuals, so viewing and exporting it is audited.
 */
class ApprovedApplicantsReport extends Report
{
    public function key(): string
    {
        return 'approved-applicants';
    }

    public function title(): string
    {
        return 'Approved applicants';
    }

    public function description(): string
    {
        return 'Each person approved for a job, scholarship or livelihood program, with their barangay and classification.';
    }

    public function periodBasis(): string
    {
        return 'Date the application was approved';
    }

    public function containsPersonalData(): bool
    {
        return true;
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $applications = Application::query()
            ->where('status', ApplicationStatus::Approved)
            ->whereBetween('decided_at', [$period->from, $period->to])
            ->with([
                'user' => fn ($q) => $q->withTrashed()->with(['barangay:id,name', 'applicantProfile']),
                'opportunity' => fn ($q) => $q->withTrashed()->with('organization:id,name'),
            ])
            ->orderBy('decided_at')
            ->get();

        $byCategory = $applications->countBy(fn (Application $a) => $a->opportunity->category->value);
        $byBarangay = $applications->countBy(fn (Application $a) => $a->user->barangay?->name ?? 'Not stated');

        return new ReportResult(
            summary: [
                ['label' => 'Approved applicants', 'value' => $applications->count()],
                ...array_map(fn (OpportunityCategory $c) => ['label' => $c->label(), 'value' => (int) ($byCategory[$c->value] ?? 0)], OpportunityCategory::cases()),
            ],
            charts: [
                ['title' => 'By barangay of residence', 'type' => 'ranked', 'rows' => $byBarangay->sortDesc()->map(fn (int $n, string $b) => ['label' => $b, 'value' => $n])->values()->all()],
            ],
            tables: [
                new ReportTable(
                    'Approved applicants',
                    [
                        'name' => 'Name',
                        'sex' => 'Sex',
                        'barangay' => 'Barangay',
                        'classification' => 'Classification',
                        'opportunity' => 'Opportunity',
                        'category' => 'Category',
                        'organization' => 'Organization',
                        'approved' => 'Approved on',
                    ],
                    $applications->map(fn (Application $a) => [
                        'name' => $a->user->name,
                        'sex' => $a->user->applicantProfile?->gender?->label(),
                        'barangay' => $a->user->barangay?->name,
                        'classification' => $a->user->applicantProfile?->classification?->label(),
                        'opportunity' => $a->opportunity->title,
                        'category' => $a->opportunity->category->label(),
                        'organization' => $a->opportunity->organization?->name,
                        'approved' => $a->decided_at?->toDateString(),
                    ])->all(),
                    note: 'Contains personal data. Use only for the municipality’s program monitoring under the Data Privacy Act.',
                ),
            ],
        );
    }
}
