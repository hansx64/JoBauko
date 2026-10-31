<?php

namespace App\Reports;

/**
 * One table in a report. Columns listed in $numeric are right-aligned and
 * written to spreadsheets as numbers.
 */
final readonly class ReportTable
{
    /**
     * @param  array<string, string>  $columns  key => heading
     * @param  list<array<string, string|int|float|null>>  $rows
     * @param  list<string>  $numeric
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public array $numeric = [],
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?int $limit = null): array
    {
        $rows = $limit === null ? $this->rows : array_slice($this->rows, 0, $limit);

        return [
            'title' => $this->title,
            'columns' => array_map(fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'numeric' => in_array($key, $this->numeric, true)], array_keys($this->columns), $this->columns),
            'rows' => $rows,
            'total_rows' => count($this->rows),
            'note' => $this->note,
        ];
    }
}
