import { Head, Link } from '@inertiajs/react';
import { Flag } from 'lucide-react';

import { visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDateTime } from '@/types/application';
import { outcomeLabels, type ReportRow } from '@/types/report';

interface AdminReportsProps {
    reports: Paginated<ReportRow>;
    filters: { status: string };
    counts: Record<string, number>;
    statuses: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Flagged listings', href: '/admin/reports' }];

export default function AdminReports({ reports, filters, counts, statuses }: AdminReportsProps) {
    const isOpen = filters.status === 'open';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Flagged listings" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Flagged listings</h1>
                    <p className="text-muted-foreground">
                        Listings users flagged as fraudulent, misleading or otherwise against the rules. Oldest first.
                    </p>
                </div>

                <div className="flex flex-wrap gap-1" role="tablist">
                    {statuses.map((tab) => (
                        <button
                            key={tab.value}
                            role="tab"
                            aria-selected={filters.status === tab.value}
                            onClick={() => visitWithFilters(route('admin.reports.index'), { status: tab.value === 'open' ? null : tab.value })}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium',
                                filters.status === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {tab.label}
                            <span className="tabular-nums opacity-70">{counts[tab.value] ?? 0}</span>
                        </button>
                    ))}
                </div>

                <Card className="overflow-hidden py-0">
                    {reports.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Flag className="h-6 w-6" />
                            {isOpen ? 'No open reports. Nothing needs review.' : 'No reports here yet.'}
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {reports.data.map((r) => (
                                <li key={r.id}>
                                    <Link href={route('admin.reports.show', r.id)} className="hover:bg-muted/30 grid gap-1 px-4 py-3 text-sm">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium">{r.target ? r.target.title : 'Deleted opportunity'}</span>
                                            <StatusBadge status={r.status} label={r.outcome ? outcomeLabels[r.outcome] : r.status_label} />
                                        </div>
                                        <p>
                                            <span className="font-medium">{r.reason_label}</span>
                                            {r.details && <span className="text-muted-foreground"> — {r.details}</span>}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {r.target?.organization && `${r.target.organization} · `}
                                            Reported by {r.reporter ?? 'a deleted account'} · {formatDateTime(r.created_at)}
                                            {r.resolved_at && ` · reviewed ${formatDateTime(r.resolved_at)}${r.resolver ? ` by ${r.resolver}` : ''}`}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Pagination page={reports} />
            </div>
        </AppLayout>
    );
}
