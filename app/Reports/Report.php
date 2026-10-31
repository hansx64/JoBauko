<?php

namespace App\Reports;

use Illuminate\Support\Collection;

/**
 * A report administrators can view and export.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Which date the period filters on, shown next to the period.
     */
    abstract public function periodBasis(): string;

    abstract public function build(ReportPeriod $period): ReportResult;

    /**
     * Reports that list individual people. Viewing and exporting them is
     * written to the audit log.
     */
    public function containsPersonalData(): bool
    {
        return false;
    }

    /**
     * Rows for a monthly chart from Y-m => count, with empty months as zero.
     *
     * @param  Collection<string, int>|array<string, int>  $counts
     * @return list<array{label: string, value: int}>
     */
    protected function monthly(ReportPeriod $period, Collection|array $counts): array
    {
        return array_map(fn (string $month) => ['label' => $month, 'value' => (int) ($counts[$month] ?? 0)], $period->months());
    }

    /**
     * @param  int|float  $part
     * @param  int|float  $whole
     */
    protected function percent($part, $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100, 1).'%' : '—';
    }

    /**
     * Hours shown as days, e.g. "1.5 days", or a dash when there is no data.
     */
    protected function days(int|float|string|null $hours): string
    {
        if ($hours === null) {
            return '—';
        }

        $days = round((float) $hours / 24, 1);

        return $days.' '.($days == 1 ? 'day' : 'days');
    }

    protected static function monthExpression(string $column): string
    {
        return "date_format({$column}, '%Y-%m')";
    }
}
