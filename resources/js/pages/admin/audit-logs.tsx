import { Head, Link } from '@inertiajs/react';
import { ChevronDown, History, Lock } from 'lucide-react';
import { Fragment, useState } from 'react';

import { FilterSelect, SearchBox, visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDateTime } from '@/types/application';

type Values = Record<string, unknown> | null;

interface LogRow {
    id: number;
    at: string;
    action: string;
    action_label: string;
    category: string;
    actor: { id: number; name: string; account_type: string } | null;
    organization: { id: number; name: string } | null;
    subject: { label: string; id: number | null; url: string | null } | null;
    old_values: Values;
    new_values: Values;
    ip_address: string | null;
}

interface Filters {
    category: string | null;
    action: string | null;
    actor: string;
    organization: number | null;
    from: string | null;
    to: string | null;
}

interface AuditLogsProps {
    logs: Paginated<LogRow>;
    filters: Filters;
    options: { categories: Option[]; actions: (Option & { category: string })[]; organizations: { id: number; name: string }[] };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audit log', href: '/admin/audit-logs' }];

const show = (value: unknown) =>
    value === null || value === undefined || value === '' ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value);

export default function AuditLogs({ logs, filters, options }: AuditLogsProps) {
    const [open, setOpen] = useState<number | null>(null);
    const visit = (changes: Partial<Filters>) => visitWithFilters(route('admin.audit-logs.index'), { ...filters, ...changes });
    const actions = options.actions.filter((a) => !filters.category || a.category === filters.category);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Audit log</h1>
                    <p className="text-muted-foreground flex items-center gap-1.5">
                        <Lock className="h-3.5 w-3.5" /> A permanent record of important actions. Entries cannot be edited or deleted.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <FilterSelect
                        id="filter-category"
                        label="Area"
                        value={filters.category}
                        allLabel="All areas"
                        options={options.categories}
                        onChange={(category) => visit({ category, action: null })}
                    />
                    <FilterSelect
                        id="filter-action"
                        label="Action"
                        value={filters.action}
                        allLabel="All actions"
                        options={actions}
                        onChange={(action) => visit({ action })}
                    />
                    <FilterSelect
                        id="filter-organization"
                        label="Organization"
                        value={filters.organization}
                        allLabel="All organizations"
                        options={options.organizations.map((o) => ({ value: o.id, label: o.name }))}
                        onChange={(organization) => visit({ organization: organization ? Number(organization) : null })}
                    />
                    <SearchBox
                        id="filter-actor"
                        label="Done by"
                        placeholder="Name or email"
                        initial={filters.actor}
                        onSearch={(actor) => visit({ actor })}
                    />
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-from">From</Label>
                        <Input id="filter-from" type="date" value={filters.from ?? ''} onChange={(e) => visit({ from: e.target.value || null })} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-to">To</Label>
                        <Input id="filter-to" type="date" value={filters.to ?? ''} onChange={(e) => visit({ to: e.target.value || null })} />
                    </div>
                </div>

                <Card className="overflow-hidden py-0">
                    {logs.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <History className="h-6 w-6" />
                            No entries match these filters.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Time</th>
                                        <th className="px-4 py-3 font-medium">Done by</th>
                                        <th className="px-4 py-3 font-medium">Action</th>
                                        <th className="px-4 py-3 font-medium">Record</th>
                                        <th className="px-4 py-3 font-medium">Organization</th>
                                        <th className="px-4 py-3">
                                            <span className="sr-only">Details</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((log) => {
                                        const hasDetails = Boolean(log.old_values || log.new_values || log.ip_address);
                                        const expanded = open === log.id;

                                        return (
                                            <Fragment key={log.id}>
                                                <tr className="align-top">
                                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDateTime(log.at)}</td>
                                                    <td className="px-4 py-3">
                                                        {log.actor ? (
                                                            <Link href={route('admin.users.show', log.actor.id)} className="hover:underline">
                                                                {log.actor.name}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-muted-foreground">System or deleted account</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">{log.action_label}</td>
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {log.subject ? (
                                                            log.subject.url ? (
                                                                <Link href={log.subject.url} className="hover:underline">
                                                                    {log.subject.label} #{log.subject.id}
                                                                </Link>
                                                            ) : (
                                                                `${log.subject.label}${log.subject.id ? ` #${log.subject.id}` : ''}`
                                                            )
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">{log.organization?.name ?? '—'}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        {hasDetails && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setOpen(expanded ? null : log.id)}
                                                                aria-expanded={expanded}
                                                                className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                                            >
                                                                Details{' '}
                                                                <ChevronDown
                                                                    className={cn('h-3.5 w-3.5 transition-transform', expanded && 'rotate-180')}
                                                                />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                                {expanded && (
                                                    <tr className="bg-muted/30">
                                                        <td colSpan={6} className="px-4 py-3">
                                                            <Changes oldValues={log.old_values} newValues={log.new_values} />
                                                            {log.ip_address && (
                                                                <p className="text-muted-foreground mt-2 text-xs">IP address: {log.ip_address}</p>
                                                            )}
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={logs} />
            </div>
        </AppLayout>
    );
}

function Changes({ oldValues, newValues }: { oldValues: Values; newValues: Values }) {
    const keys = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]));

    if (keys.length === 0) {
        return <p className="text-muted-foreground text-xs">No values recorded.</p>;
    }

    return (
        <dl className="grid gap-1 text-xs sm:grid-cols-[12rem_1fr]">
            {keys.map((key) => (
                <Fragment key={key}>
                    <dt className="text-muted-foreground font-medium">{key}</dt>
                    <dd className="break-all">
                        {oldValues && key in oldValues && (
                            <>
                                <span className="text-red-700 line-through dark:text-red-300">{show(oldValues[key])}</span>
                                {' → '}
                            </>
                        )}
                        <span>{newValues && key in newValues ? show(newValues[key]) : '—'}</span>
                    </dd>
                </Fragment>
            ))}
        </dl>
    );
}
