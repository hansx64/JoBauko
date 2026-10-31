import { Head, router, usePage } from '@inertiajs/react';
import { FileDown, FileSpreadsheet, ShieldAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { ChartCard } from '@/components/charts/chart-card';
import { DailyColumnChart, DailyCountTable } from '@/components/charts/daily-column-chart';
import { RankedBars } from '@/components/charts/ranked-bars';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

interface ChartData {
    title: string;
    type: 'ranked' | 'monthly';
    unit?: [string, string];
    rows: { label: string; value: number }[];
}

interface TableData {
    title: string;
    columns: { key: string; label: string; numeric: boolean }[];
    rows: Record<string, string | number | null>[];
    total_rows: number;
    note: string | null;
}

interface AnalyticsShowProps {
    report: { key: string; title: string; description: string; basis: string; personal_data: boolean };
    period: { from: string; to: string; label: string };
    summary: { label: string; value: string | number }[];
    charts: ChartData[];
    tables: TableData[];
}

const iso = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

function presets() {
    const today = new Date();
    const year = today.getFullYear();
    const quarterStart = new Date(year, Math.floor(today.getMonth() / 3) * 3, 1);
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 29);

    return [
        { label: 'Last 30 days', from: iso(thirtyDaysAgo), to: iso(today) },
        { label: 'This quarter', from: iso(quarterStart), to: iso(today) },
        { label: 'This year', from: `${year}-01-01`, to: iso(today) },
        { label: 'Last year', from: `${year - 1}-01-01`, to: `${year - 1}-12-31` },
    ];
}

export default function AnalyticsShow({ report, period, summary, charts, tables }: AnalyticsShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports & analytics', href: '/admin/analytics' },
        { title: report.title, href: `/admin/analytics/${report.key}` },
    ];
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [range, setRange] = useState({ from: period.from, to: period.to });

    const apply = (next: { from: string; to: string }) => {
        setRange(next);
        router.get(route('admin.analytics.show', report.key), next, { preserveScroll: true, replace: true });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        apply(range);
    };

    const exportUrl = (format: 'pdf' | 'xlsx') => route('admin.analytics.export', { key: report.key, format, from: period.from, to: period.to });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={report.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{report.title}</h1>
                        <p className="text-muted-foreground">{report.description}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={exportUrl('pdf')}>
                                <FileDown className="h-4 w-4" /> PDF
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={exportUrl('xlsx')}>
                                <FileSpreadsheet className="h-4 w-4" /> Excel
                            </a>
                        </Button>
                    </div>
                </div>

                {report.personal_data && (
                    <p className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
                        This report lists individuals. Viewing and exporting it is recorded in the audit log. Share it only with people who need it
                        for program monitoring, as required by the Data Privacy Act.
                    </p>
                )}

                <Card>
                    <CardContent className="grid gap-3">
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={range.from}
                                    max={range.to}
                                    onChange={(e) => setRange({ ...range, from: e.target.value })}
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={range.to}
                                    min={range.from}
                                    onChange={(e) => setRange({ ...range, to: e.target.value })}
                                    required
                                />
                            </div>
                            <Button type="submit">Update</Button>
                            <div className="flex flex-wrap gap-1">
                                {presets().map((preset) => (
                                    <button
                                        key={preset.label}
                                        type="button"
                                        onClick={() => apply({ from: preset.from, to: preset.to })}
                                        className={cn(
                                            'rounded-md px-2.5 py-1.5 text-xs font-medium',
                                            preset.from === period.from && preset.to === period.to
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-muted',
                                        )}
                                    >
                                        {preset.label}
                                    </button>
                                ))}
                            </div>
                        </form>
                        <InputError message={errors.from || errors.to} />
                        <p className="text-muted-foreground text-xs">
                            {period.label} · {report.basis}
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {summary.map((item) => (
                        <Card key={item.label}>
                            <CardHeader>
                                <CardDescription>{item.label}</CardDescription>
                                <CardTitle className="text-3xl tabular-nums">
                                    {typeof item.value === 'number' ? item.value.toLocaleString() : item.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                {charts.length > 0 && (
                    <div className="grid items-start gap-4 lg:grid-cols-2">
                        {charts.map((chart) => (
                            <ReportChart key={chart.title} chart={chart} />
                        ))}
                    </div>
                )}

                {tables.map((table) => (
                    <ReportTableCard key={table.title} table={table} />
                ))}
            </div>
        </AppLayout>
    );
}

function ReportChart({ chart }: { chart: ChartData }) {
    const hasData = chart.rows.some((row) => row.value > 0);

    if (chart.type === 'monthly') {
        const data = chart.rows.map((row) => ({ date: `${row.label}-01`, count: row.value }));

        return (
            <ChartCard
                title={chart.title}
                chart={<DailyColumnChart data={data} unit={chart.unit ?? ['record', 'records']} granularity="month" />}
                table={<DailyCountTable data={data} label="Count" granularity="month" />}
            />
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{chart.title}</CardTitle>
            </CardHeader>
            <CardContent>
                {hasData ? (
                    <RankedBars rows={chart.rows.map((row) => ({ key: row.label, label: row.label, value: row.value }))} valueLabel={chart.title} />
                ) : (
                    <p className="text-muted-foreground text-sm">No records in this period.</p>
                )}
            </CardContent>
        </Card>
    );
}

function ReportTableCard({ table }: { table: TableData }) {
    return (
        <Card className="overflow-hidden">
            <CardHeader>
                <CardTitle className="text-base">{table.title}</CardTitle>
                {table.total_rows > table.rows.length && (
                    <CardDescription>
                        Showing the first {table.rows.length.toLocaleString()} of {table.total_rows.toLocaleString()} rows. Downloads include every
                        row.
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="grid gap-2 px-0">
                {table.rows.length === 0 ? (
                    <p className="text-muted-foreground px-6 text-sm">No records in this period.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    {table.columns.map((column) => (
                                        <th
                                            key={column.key}
                                            className={cn('px-4 py-2.5 font-medium whitespace-nowrap first:pl-6', column.numeric && 'text-right')}
                                        >
                                            {column.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {table.rows.map((row, index) => (
                                    <tr key={index}>
                                        {table.columns.map((column) => (
                                            <td key={column.key} className={cn('px-4 py-2 first:pl-6', column.numeric && 'text-right tabular-nums')}>
                                                {row[column.key] === null || row[column.key] === undefined
                                                    ? '—'
                                                    : typeof row[column.key] === 'number'
                                                      ? (row[column.key] as number).toLocaleString()
                                                      : row[column.key]}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {table.note && <p className="text-muted-foreground px-6 text-xs">{table.note}</p>}
            </CardContent>
        </Card>
    );
}
