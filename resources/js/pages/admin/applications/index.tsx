import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Hourglass } from 'lucide-react';

import { FilterSelect, SearchBox, visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDate } from '@/types/organization';

interface ApplicationRow {
    id: number;
    applicant: string;
    user_id: number;
    opportunity: string;
    opportunity_id: number;
    organization: string | null;
    category_label: string;
    status: string;
    status_label: string;
    submitted_at: string;
    last_activity_at: string;
    stalled: boolean;
}

interface Filters {
    status: string | null;
    category: string | null;
    organization: number | null;
    q: string;
}

interface AdminApplicationsProps {
    applications: Paginated<ApplicationRow>;
    filters: Filters;
    counts: Record<string, number>;
    stalledDays: number;
    options: { statuses: Option[]; categories: Option[]; organizations: { id: number; name: string }[] };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Applications', href: '/admin/applications' }];

export default function AdminApplications({ applications, filters, counts, stalledDays, options }: AdminApplicationsProps) {
    const visit = (changes: Partial<Filters>) => visitWithFilters(route('admin.applications.index'), { ...filters, ...changes });

    const statusOptions = [
        { value: 'active', label: `In progress (${counts.active})` },
        { value: 'stalled', label: `No update for ${stalledDays}+ days (${counts.stalled})` },
        ...options.statuses.map((s) => ({ value: s.value, label: `${s.label} (${counts[s.value] ?? 0})` })),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Applications" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Applications</h1>
                    <p className="text-muted-foreground">
                        Where every application stands. Documents, cover letters and review notes stay with the organization the person applied to.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FilterSelect
                        id="filter-status"
                        label="Status"
                        value={filters.status}
                        allLabel={`All statuses (${counts.all})`}
                        options={statusOptions}
                        onChange={(status) => visit({ status })}
                    />
                    <FilterSelect
                        id="filter-category"
                        label="Category"
                        value={filters.category}
                        allLabel="All categories"
                        options={options.categories}
                        onChange={(category) => visit({ category })}
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
                        id="filter-q"
                        label="Search"
                        placeholder="Applicant or opportunity"
                        initial={filters.q}
                        onSearch={(q) => visit({ q })}
                    />
                </div>

                <Card className="overflow-hidden py-0">
                    {applications.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <ClipboardList className="h-6 w-6" />
                            No applications match these filters.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Applicant</th>
                                        <th className="px-4 py-3 font-medium">Opportunity</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Submitted</th>
                                        <th className="px-4 py-3 font-medium">Last update</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {applications.data.map((a) => (
                                        <tr key={a.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Link href={route('admin.users.show', a.user_id)} className="font-medium hover:underline">
                                                    {a.applicant}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Link href={route('admin.opportunities.show', a.opportunity_id)} className="hover:underline">
                                                    {a.opportunity}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {a.category_label} · {a.organization}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={a.status} label={a.status_label} />
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDate(a.submitted_at)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {a.stalled ? (
                                                    <span
                                                        className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300"
                                                        title={`No update for ${stalledDays} days or more`}
                                                    >
                                                        <Hourglass className="h-3.5 w-3.5" /> {formatDate(a.last_activity_at)}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">{formatDate(a.last_activity_at)}</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={applications} />
            </div>
        </AppLayout>
    );
}
