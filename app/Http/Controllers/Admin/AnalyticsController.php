<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\Export\SpreadsheetExporter;
use App\Reports\Report;
use App\Reports\ReportCatalog;
use App\Reports\ReportPeriod;
use App\Reports\ReportTable;
use App\Services\Audit\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Administrator reports, viewable on screen and exportable as PDF or Excel.
 */
class AnalyticsController extends Controller
{
    /** Rows shown per table on screen; exports always contain every row. */
    private const SCREEN_ROWS = 200;

    public function index(): Response
    {
        return Inertia::render('admin/analytics/index', [
            'reports' => array_map(fn (Report $r) => [
                'key' => $r->key(),
                'title' => $r->title(),
                'description' => $r->description(),
                'personal_data' => $r->containsPersonalData(),
            ], ReportCatalog::all()),
        ]);
    }

    public function show(Request $request, string $key, AuditLogger $audit): Response
    {
        $report = ReportCatalog::find($key) ?? abort(404);
        $period = ReportPeriod::fromRequest($request);
        $result = $report->build($period);

        if ($report->containsPersonalData()) {
            $audit->log('report.viewed', null, new: ['report' => $key, ...$period->toArray()]);
        }

        return Inertia::render('admin/analytics/show', [
            'report' => [
                'key' => $report->key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'basis' => $report->periodBasis(),
                'personal_data' => $report->containsPersonalData(),
            ],
            'period' => $period->toArray(),
            'summary' => $result->summary,
            'charts' => $result->charts,
            'tables' => array_map(fn (ReportTable $t) => $t->toArray(self::SCREEN_ROWS), $result->tables),
        ]);
    }

    public function export(Request $request, string $key, string $format, AuditLogger $audit, SpreadsheetExporter $spreadsheets): HttpResponse
    {
        $report = ReportCatalog::find($key) ?? abort(404);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $period = ReportPeriod::fromRequest($request);
        $result = $report->build($period);
        $filename = sprintf('jobauko-%s-%s-to-%s.%s', $key, $period->from->toDateString(), $period->to->toDateString(), $format);

        $audit->log('report.exported', null, new: ['report' => $key, 'format' => $format, ...$period->toArray()]);

        if ($format === 'xlsx') {
            return response()->download($spreadsheets->export($report, $period, $result, $request->user()), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        $wide = collect($result->tables)->contains(fn (ReportTable $t) => count($t->columns) > 6);

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'period' => $period,
            'result' => $result,
            'generatedAt' => now()->format('M j, Y g:i A'),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4', $wide ? 'landscape' : 'portrait')->download($filename);
    }
}
