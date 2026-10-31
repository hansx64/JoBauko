<?php

namespace App\Reports;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\Organization;

/**
 * Organizations that registered in the period and where their verification
 * stands now.
 */
class OrganizationsReport extends Report
{
    public function key(): string
    {
        return 'organizations';
    }

    public function title(): string
    {
        return 'Organizations';
    }

    public function description(): string
    {
        return 'Registered organizations by type and verification status, with their personnel and listings.';
    }

    public function periodBasis(): string
    {
        return 'Date the organization registered';
    }

    public function build(ReportPeriod $period): ReportResult
    {
        $organizations = Organization::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->with('barangay:id,name')
            ->withCount([
                'members as active_members_count' => fn ($q) => $q->where('is_active', true),
                'opportunities as published_count' => fn ($q) => $q->whereNotNull('published_at'),
            ])
            ->orderBy('name')
            ->get();

        $byStatus = $organizations->countBy(fn (Organization $o) => $o->verification_status->value);
        $byType = $organizations->countBy(fn (Organization $o) => $o->type->value);

        return new ReportResult(
            summary: [
                ['label' => 'Registered', 'value' => $organizations->count()],
                ['label' => 'Verified', 'value' => (int) ($byStatus[VerificationStatus::Verified->value] ?? 0)],
                ['label' => 'Awaiting verification', 'value' => (int) ($byStatus[VerificationStatus::Pending->value] ?? 0)],
                ['label' => 'Rejected or suspended', 'value' => (int) ($byStatus[VerificationStatus::Rejected->value] ?? 0) + (int) ($byStatus[VerificationStatus::Suspended->value] ?? 0)],
            ],
            charts: [
                ['title' => 'By type', 'type' => 'ranked', 'rows' => collect(OrganizationType::cases())
                    ->map(fn (OrganizationType $t) => ['label' => $t->label(), 'value' => (int) ($byType[$t->value] ?? 0)])
                    ->sortByDesc('value')->values()->all()],
            ],
            tables: [
                new ReportTable('By verification status', ['status' => 'Status', 'total' => 'Organizations'], array_map(fn (VerificationStatus $s) => [
                    'status' => $s->label(),
                    'total' => (int) ($byStatus[$s->value] ?? 0),
                ], VerificationStatus::cases()), ['total']),
                new ReportTable(
                    'Organizations',
                    [
                        'name' => 'Organization',
                        'type' => 'Type',
                        'barangay' => 'Barangay',
                        'status' => 'Status',
                        'registered' => 'Registered',
                        'verified' => 'Verified',
                        'personnel' => 'Active personnel',
                        'published' => 'Opportunities published',
                    ],
                    $organizations->map(fn (Organization $o) => [
                        'name' => $o->name,
                        'type' => $o->type->label(),
                        'barangay' => $o->barangay?->name,
                        'status' => $o->verification_status->label(),
                        'registered' => $o->created_at->toDateString(),
                        'verified' => $o->verified_at?->toDateString(),
                        'personnel' => (int) $o->getAttribute('active_members_count'),
                        'published' => (int) $o->getAttribute('published_count'),
                    ])->all(),
                    ['personnel', 'published'],
                    'Opportunities published counts every listing the organization has ever published.',
                ),
            ],
        );
    }
}
