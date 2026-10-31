<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 28px 32px 40px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #111827; }
        header { border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px; }
        .eyebrow { color: #1e3a8a; font-size: 9px; letter-spacing: 0.04em; text-transform: uppercase; }
        h1 { font-size: 18px; margin: 2px 0 4px; }
        .meta { color: #4b5563; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
        .summary td { border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 8px; width: 25%; vertical-align: top; }
        .summary .value { font-size: 15px; font-weight: bold; }
        .summary .label { color: #4b5563; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f3f4f6; text-align: left; font-weight: bold; padding: 4px 5px; border-bottom: 1px solid #d1d5db; }
        table.data td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .note { color: #6b7280; font-style: italic; margin-top: 4px; }
        .notice { border: 1px solid #f59e0b; background: #fffbeb; padding: 6px 8px; margin-bottom: 12px; }
        footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <footer>JoBauko — {{ $report->title() }} — {{ $period->label() }} — generated {{ $generatedAt }} by {{ $generatedBy }}</footer>

    <header>
        <div class="eyebrow">Municipality of Bauko, Mountain Province · JoBauko</div>
        <h1>{{ $report->title() }}</h1>
        <div class="meta">{{ $period->label() }} · {{ $report->periodBasis() }}</div>
    </header>

    @if ($report->containsPersonalData())
        <div class="notice">This report contains personal data protected by the Data Privacy Act of 2012 (RA 10173). Do not share it beyond the people who need it for program monitoring.</div>
    @endif

    <table class="summary">
        @foreach (array_chunk($result->summary, 4) as $chunk)
            <tr>
                @foreach ($chunk as $item)
                    <td><div class="value">{{ is_int($item['value']) ? number_format($item['value']) : $item['value'] }}</div><div class="label">{{ $item['label'] }}</div></td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @foreach ($result->tables as $table)
        <h2>{{ $table->title }}</h2>
        @if (count($table->rows) === 0)
            <p class="meta">No records in this period.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($table->columns as $key => $label)
                            <th @class(['num' => in_array($key, $table->numeric, true)])>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table->rows as $row)
                        <tr>
                            @foreach ($table->columns as $key => $label)
                                <td @class(['num' => in_array($key, $table->numeric, true)])>{{ $row[$key] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if ($table->note)
            <p class="note">{{ $table->note }}</p>
        @endif
    @endforeach
</body>
</html>
