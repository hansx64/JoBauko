<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\OpportunityCategory;
use App\Models\Opportunity;

/**
 * Opportunities first published in the period, with where they stand now.
 */
class PublishedOpportunitiesReport extends Report
{
    public function key(): string
    {
        return 'published-opportunities';
    }

    public function title(): string
    {
        return 'Published opportunities';
    }

    public function description(): string
    {
        return 'Jobs, scholarships and livelihood programs published, with slots offered and applications received.';
    }

    public function periodBasis(): string
    {
        return 'Date the opportunity was first published';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $opportunities = Opportunity::query()
            ->whereBetween('published_at', [$period->from, $period->to])
            ->with(['organization:id,name', 'barangay:id,name'])
            ->withCount([
                'applications',
                'applications as approved_count' => fn ($q) => $q->where('status', ApplicationStatus::Approved),
            ])
            ->orderBy('published_at')
            ->get();

        $byCategory = $opportunities->countBy(fn (Opportunity $o) => $o->category->value);
        $monthly = $opportunities->countBy(fn (Opportunity $o) => $o->published_at->format('Y-m'));
        $open = $opportunities->filter(fn (Opportunity $o) => $o->isAcceptingApplications())->count();

        return new ReportResult(
            summary: [
                ['label' => 'Published', 'value' => $opportunities->count()],
                ['label' => 'Accepting applications today', 'value' => $open],
                ['label' => 'Slots offered', 'value' => (int) $opportunities->sum('slots')],
                ['label' => 'Applications received', 'value' => (int) $opportunities->sum('applications_count')],
            ],
            charts: [
                ['title' => 'Published per month', 'type' => 'monthly', 'unit' => ['opportunity', 'opportunities'], 'rows' => $this->monthly($period, $monthly)],
                ['title' => 'By category', 'type' => 'ranked', 'rows' => collect(OpportunityCategory::cases())
                    ->map(fn (OpportunityCategory $c) => ['label' => $c->label(), 'value' => (int) ($byCategory[$c->value] ?? 0)])
                    ->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable(
                    'Opportunities',
                    [
                        'title' => 'Opportunity',
                        'organization' => 'Organization',
                        'category' => 'Category',
                        'location' => 'Location',
                        'published' => 'Published',
                        'deadline' => 'Deadline',
                        'status' => 'Status today',
                        'slots' => 'Slots',
                        'applications' => 'Applications',
                        'approved' => 'Approved',
                    ],
                    $opportunities->map(fn (Opportunity $o) => [
                        'title' => $o->title,
                        'organization' => $o->organization?->name,
                        'category' => $o->category->label(),
                        'location' => $o->is_remote ? 'Remote' : ($o->barangay?->name ?? $o->location_text),
                        'published' => $o->published_at->toDateString(),
                        'deadline' => $o->application_deadline?->toDateString(),
                        'status' => $o->displayStatusLabel(),
                        'slots' => $o->slots,
                        'applications' => (int) $o->getAttribute('applications_count'),
                        'approved' => (int) $o->getAttribute('approved_count'),
                    ])->all(),
                    ['slots', 'applications', 'approved'],
                    'Slots are blank where the organization did not state a number.',
                ),
            ],
        );
    }
}
