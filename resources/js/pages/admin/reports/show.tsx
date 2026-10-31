import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { formatDateTime } from '@/types/application';
import { outcomeLabels, type ReportRow } from '@/types/report';

interface AdminReportShowProps {
    report: ReportRow & { reporter_email: string | null; resolution_notes: string | null };
    opportunity: {
        id: number;
        title: string;
        slug: string;
        organization: string;
        organization_id: number;
        status: string;
        status_label: string;
        is_public: boolean;
        can_suspend: boolean;
    } | null;
    related: ReportRow[];
}

type Decision = 'suspend' | 'resolve' | 'dismiss';

const decisions: { value: Decision; label: string; description: string }[] = [
    {
        value: 'suspend',
        label: 'Suspend the opportunity',
        description: 'Hide and lock the listing. Your notes are sent to the organization as the reason.',
    },
    {
        value: 'resolve',
        label: 'Resolved another way',
        description: 'For example, the organization corrected the listing after you contacted them.',
    },
    {
        value: 'dismiss',
        label: 'Dismiss',
        description: 'The listing does not break the rules.',
    },
];

export default function AdminReportShow({ report, opportunity, related }: AdminReportShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports', href: '/admin/reports' },
        { title: `Report #${report.id}`, href: `/admin/reports/${report.id}` },
    ];
    const available = decisions.filter((d) => d.value !== 'suspend' || opportunity?.can_suspend);
    const form = useForm<{ decision: Decision; notes: string }>({ decision: available[0].value, notes: '' });
    const openRelated = related.filter((r) => r.status === 'open').length;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.reports.decide', report.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Report #${report.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">{report.reason_label}</h1>
                        <StatusBadge status={report.status} label={report.outcome ? outcomeLabels[report.outcome] : report.status_label} />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Reported by {report.reporter ?? 'a deleted account'}
                        {report.reporter_email && ` (${report.reporter_email})`} · {formatDateTime(report.created_at)}
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_24rem]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">What was reported</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <p className="whitespace-pre-line">{report.details || 'The reporter did not add details.'}</p>
                                {opportunity ? (
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2.5">
                                        <div className="min-w-0">
                                            <Link href={route('admin.opportunities.show', opportunity.id)} className="font-medium hover:underline">
                                                {opportunity.title}
                                            </Link>
                                            <p className="text-muted-foreground text-xs">
                                                <Link
                                                    href={route('admin.organizations.show', opportunity.organization_id)}
                                                    className="hover:underline"
                                                >
                                                    {opportunity.organization}
                                                </Link>
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={opportunity.status} label={opportunity.status_label} />
                                            {opportunity.is_public && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <a
                                                        href={route('opportunities.show', opportunity.slug)}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        aria-label="Open the public page"
                                                    >
                                                        <ExternalLink className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground">The reported opportunity has been deleted.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Other reports on this opportunity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {related.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No other reports.</p>
                                ) : (
                                    <ul className="divide-y rounded-lg border text-sm">
                                        {related.map((r) => (
                                            <li key={r.id} className="grid gap-1 px-3 py-2.5">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <Link href={route('admin.reports.show', r.id)} className="font-medium hover:underline">
                                                        {r.reason_label}
                                                    </Link>
                                                    <StatusBadge status={r.status} label={r.outcome ? outcomeLabels[r.outcome] : r.status_label} />
                                                </div>
                                                {r.details && <p className="text-muted-foreground">{r.details}</p>}
                                                <p className="text-muted-foreground text-xs">
                                                    {r.reporter ?? 'Deleted account'} · {formatDateTime(r.created_at)}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {report.status === 'open' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Decision</CardTitle>
                                <CardDescription>
                                    Applies to this report
                                    {openRelated > 0 && ` and the ${openRelated} other open ${openRelated === 1 ? 'report' : 'reports'}`} on the same
                                    opportunity. Reporters are told the outcome, not your notes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="grid gap-4">
                                    <fieldset className="grid gap-2">
                                        <legend className="sr-only">Decision</legend>
                                        {available.map((d) => (
                                            <label
                                                key={d.value}
                                                className={cn(
                                                    'flex cursor-pointer gap-3 rounded-lg border p-3 text-sm',
                                                    form.data.decision === d.value && 'border-primary bg-primary/5',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="decision"
                                                    value={d.value}
                                                    checked={form.data.decision === d.value}
                                                    onChange={() => form.setData('decision', d.value)}
                                                    className="mt-0.5"
                                                />
                                                <span>
                                                    <span className="font-medium">{d.label}</span>
                                                    <span className="text-muted-foreground block text-xs">{d.description}</span>
                                                </span>
                                            </label>
                                        ))}
                                    </fieldset>
                                    <InputError message={form.errors.decision} />
                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            {form.data.decision === 'suspend' ? 'Reason (sent to the organization)' : 'Notes'}
                                        </Label>
                                        <Textarea
                                            id="notes"
                                            rows={4}
                                            maxLength={1000}
                                            minLength={10}
                                            required
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                        />
                                        <InputError message={form.errors.notes} />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant={form.data.decision === 'suspend' ? 'destructive' : 'default'}
                                        disabled={form.processing}
                                    >
                                        Record decision
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Decision</CardTitle>
                                <CardDescription>
                                    {report.resolved_at && formatDateTime(report.resolved_at)}
                                    {report.resolver && ` by ${report.resolver}`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p className="font-medium">{report.outcome ? outcomeLabels[report.outcome] : report.status_label}</p>
                                {report.resolution_notes && <p className="whitespace-pre-line">{report.resolution_notes}</p>}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
