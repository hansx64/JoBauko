<?php

namespace App\Reports\Export;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportPeriod;
use App\Reports\ReportResult;
use App\Reports\ReportTable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes a report as an .xlsx workbook: a summary sheet, then one sheet per
 * table with numbers stored as numbers.
 */
class SpreadsheetExporter
{
    public function export(Report $report, ReportPeriod $period, ReportResult $result, User $generatedBy): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('JoBauko')->setTitle($report->title());

        $summary = $book->getActiveSheet()->setTitle('Summary');
        $lines = [
            [$report->title()],
            ['Municipality of Bauko, Mountain Province — JoBauko'],
            ['Period', $period->label()],
            ['Based on', $report->periodBasis()],
            ['Generated', now()->format('M j, Y g:i A').' by '.$generatedBy->name],
            [],
            ...array_map(fn (array $item) => [$item['label'], $item['value']], $result->summary),
        ];
        $summary->fromArray($lines, null, 'A1', true);
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);

        $used = ['Summary'];
        foreach ($result->tables as $table) {
            $this->writeTable($book->createSheet(), $table, $used);
        }

        $path = tempnam(sys_get_temp_dir(), 'report');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  list<string>  $used  sheet names already taken
     */
    private function writeTable(Worksheet $sheet, ReportTable $table, array &$used): void
    {
        $sheet->setTitle($this->sheetName($table->title, $used));

        $keys = array_keys($table->columns);
        $sheet->fromArray(array_values($table->columns), null, 'A1');
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($keys)).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach ($table->rows as $r => $row) {
            foreach ($keys as $c => $key) {
                $value = $row[$key] ?? null;
                $cell = Coordinate::stringFromColumnIndex($c + 1).($r + 2);

                // Text that looks like a formula must never be evaluated.
                is_string($value)
                    ? $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING)
                    : $sheet->setCellValue($cell, $value);
            }
        }

        foreach (range(1, count($keys)) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        if ($table->note) {
            $sheet->setCellValueExplicit('A'.(count($table->rows) + 3), $table->note, DataType::TYPE_STRING);
            $sheet->getStyle('A'.(count($table->rows) + 3))->getFont()->setItalic(true);
        }
    }

    /**
     * Excel sheet names: at most 31 characters, none of []:*?/\ and unique.
     *
     * @param  list<string>  $used
     */
    private function sheetName(string $title, array &$used): string
    {
        $base = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title)), 0, 28);
        $name = $base;

        for ($i = 2; in_array($name, $used, true); $i++) {
            $name = "{$base} {$i}";
        }

        $used[] = $name;

        return $name;
    }
}
