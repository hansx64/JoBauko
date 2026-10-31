<?php

namespace App\Reports;

/**
 * What a report produces, independent of how it is shown: the web page,
 * the PDF and the spreadsheet all render this.
 */
final readonly class ReportResult
{
    /**
     * @param  list<array{label: string, value: string|int}>  $summary
     * @param  list<array{title: string, type: 'ranked'|'monthly', unit?: array{0: string, 1: string}, rows: list<array{label: string, value: int}>}>  $charts
     * @param  list<ReportTable>  $tables
     */
    public function __construct(
        public array $summary,
        public array $charts,
        public array $tables,
    ) {}
}
