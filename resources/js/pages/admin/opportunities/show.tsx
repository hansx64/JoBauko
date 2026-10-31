import { Head, Link, router, usePage } from '@inertiajs/react';
import { Ban, ExternalLink, Flag, RotateCcw } from 'lucide-react';
import { ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { ReasonDialog } from '@/components/reason-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatDateTime } from '@/types/application';
import { formatDate } from '@/types/organization';
import { outcomeLabels, type ReportRow } from '@/types/report';

interface AdminOpportunityShowProps {
    opportunity: {
        id: number;
        title: string;
        slug: string;
        organization: { id: number; name: string; verification_label: string };
        category_label: string;
        status: string;
        status_label: string;
        stored_status: string;
        is_public: boolean;
        location: string | null;
        application_deadline: string | null;
        published_at: string | null;
        created_at: string;
        created_by: string | null;
        slots: number | null;
        description: string | null;
        suspension: { reason: string | null; at: string | null; by: string | null; previous_label: string | null } | null;
    };
    applications: { total: number; byStatus: { status: string; label: string; count: number }[] };
    reports: ReportRow[];
    history: { id: number; action: string; by: string | null; reason: string | null; at: string }[];
}

const actionLabels: Record<string, string> = {
    'opportunity.created': 'Created',
    'opportunity.published': 'Published',
    'opportunity.unpublished': 'Moved back to draft',
    'opportunity.closed': 'Closed',
    'opportunity.reopened': 'Reopened',
    'opportunity.deadline_extended': 'Deadline extended',
    'opportunity.archived': 'Archived',
    'opportunity.suspended': 'Suspended by an administrator',
    'opportunity.reinstated': 'Reinstated by an administrator',
};

export default function AdminOpportunityShow({ opportunity, applications, reports, history }: AdminOpportunityShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Opportunities', href: '/admin/opportunities' },
        { title: opportunity.title, href: `/admin/opportunities/${opportunity.id}` },
    ];
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [suspending, setSuspending] = useState(false);
    const canSuspend = ['draft', 'published', 'closed'].includes(opportunity.stored_status);
    const openReports = reports.filter((r) => r.status === 'open');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={opportunity.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">{opportunity.title}</h1>
                            <StatusBadge status={opportunity.status} label={opportunity.status_label} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {opportunity.category_label} ·{' '}
                            <Link href={route('admin.organizations.show', opportunity.organization.id)} className="hover:underline">
                                {opportunity.organization.name}
                            </Link>{' '}
                            ({opportunity.organization.verification_label})
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {opportunity.is_public && (
                            <Button variant="outline" asChild>
                                <a href={route('opportunities.show', opportunity.slug)} target="_blank" rel="noreferrer">
                                    <ExternalLink className="h-4 w-4" /> Public page
                                </a>
                            </Button>
                        )}
                        {canSuspend && (
                            <Button variant="destructive" onClick={() => setSuspending(true)}>
                                <Ban className="h-4 w-4" /> Suspend
                            </Button>
                        )}
                        {opportunity.stored_status === 'suspended' && (
                            <Button onClick={() => router.post(route('admin.opportunities.reinstate', opportunity.id), {}, { preserveScroll: true })}>
                                <RotateCcw className="h-4 w-4" /> Reinstate
                            </Button>
                        )}
                    </div>
                </div>

                <InputError message={errors.status} />

                {opportunity.suspension && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
                        <p className="font-medium">
                            Suspended {opportunity.suspension.at && formatDateTime(opportunity.suspension.at)}
                            {opportunity.suspension.by && ` by ${opportunity.suspension.by}`}
                        </p>
                        <p>Reason sent to the organization: {opportunity.suspension.reason}</p>
                        {opportunity.suspension.previous_label && (
                            <p className="mt-1 opacity-80">Reinstating returns it to {opportunity.suspension.previous_label.toLowerCase()}.</p>
                        )}
                    </div>
                )}

                {openReports.length > 0 && (
                    <Link
                        href={route('admin.reports.show', openReports[0].id)}
                        className="flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <span className="flex items-center gap-2">
                            <Flag className="h-4 w-4" />
                            {openReports.length} open {openReports.length === 1 ? 'report' : 'reports'} on this opportunity
                        </span>
                        <span className="font-medium">Review →</span>
                    </Link>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Details</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <dl className="grid gap-4 sm:grid-cols-3">
                                    <Detail label="Location">{opportunity.location ?? '—'}</Detail>
                                    <Detail label="Deadline">
                                        {opportunity.application_deadline ? formatDate(opportunity.application_deadline) : '—'}
                                    </Detail>
                                    <Detail label="Slots">{opportunity.slots ?? '—'}</Detail>
                                    <Detail label="Created">
                                        {formatDate(opportunity.created_at)}
                                        {opportunity.created_by && ` by ${opportunity.created_by}`}
                                    </Detail>
                                    <Detail label="First published">
                                        {opportunity.published_at ? formatDate(opportunity.published_at) : 'Never'}
                                    </Detail>
                                    <Detail label="Publicly visible">{opportunity.is_public ? 'Yes' : 'No'}</Detail>
                                </dl>
                                <div className="grid gap-1">
                                    <p className="text-muted-foreground text-xs">Description</p>
                                    <p className="whitespace-pre-line">{opportunity.description || '—'}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Reports</CardTitle>
                                <CardDescription>What users reported about this listing.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {reports.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No reports.</p>
                                ) : (
                                    <ul className="divide-y rounded-lg border text-sm">
                                        {reports.map((r) => (
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

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Applications</CardTitle>
                                <CardDescription>{applications.total.toLocaleString()} received</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {applications.total === 0 ? (
                                    <p className="text-muted-foreground text-sm">No applications yet.</p>
                                ) : (
                                    <ul className="grid gap-2 text-sm">
                                        {applications.byStatus.map((row) => (
                                            <li key={row.status} className="flex items-center justify-between gap-2">
                                                <StatusBadge status={row.status} label={row.label} />
                                                <span className="tabular-nums">{row.count}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {history.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No recorded changes.</p>
                                ) : (
                                    <ol className="grid gap-3 text-sm">
                                        {history.map((entry) => (
                                            <li key={entry.id}>
                                                <p className="font-medium">{actionLabels[entry.action] ?? entry.action}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {formatDateTime(entry.at)}
                                                    {entry.by && ` · ${entry.by}`}
                                                </p>
                                                {entry.reason && <p className="mt-0.5">{entry.reason}</p>}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ReasonDialog
                open={suspending}
                onOpenChange={setSuspending}
                action={route('admin.opportunities.suspend', opportunity.id)}
                title="Suspend this opportunity?"
                description="It will be hidden from the public and locked for editing. The organization's personnel are notified with the reason you give. Applications already received are kept."
                label="Reason (sent to the organization)"
                submitLabel="Suspend"
            />
        </AppLayout>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1">
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}
