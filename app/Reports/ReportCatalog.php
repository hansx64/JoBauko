<?php

namespace App\Reports;

/**
 * The reports administrators can generate, in menu order.
 */
class ReportCatalog
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        RegisteredUsersReport::class,
        OrganizationsReport::class,
        PublishedOpportunitiesReport::class,
        ApplicationsReport::class,
        ApprovedApplicantsReport::class,
        OpportunityCategoriesReport::class,
        GeographicDistributionReport::class,
        OrganizationActivityReport::class,
    ];

    /**
     * @return list<Report>
     */
    public static function all(): array
    {
        return array_map(fn (string $class) => app($class), self::REPORTS);
    }

    public static function find(string $key): ?Report
    {
        foreach (self::all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }
}
