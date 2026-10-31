import { Head, Link } from '@inertiajs/react';
import { Flag, Megaphone } from 'lucide-react';

import { FilterSelect, SearchBox, visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDate } from '@/types/organization';

interface OpportunityRow {
    id: number;
    title: string;
    organization: string;
    organization_id: number;
    category: string;
    category_label: string;
    status: string;
    status_label: string;
    application_deadline: string | null;
    applications_count: number;
    open_reports_count: number;
}

interface Filters {
    status: string;
    category: string | null;
    q: string;
}

interface AdminOpportunitiesProps {
    opportunities: Paginated<OpportunityRow>;
    filters: Filters;
    counts: Record<string, number>;
    categories: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Opportunities', href: '/admin/opportunities' }];

const tabs = [
    { value: 'all', label: 'All' },
    { value: 'reported', label: 'Reported' },
    { value: 'published', label: 'Open' },
    { value: 'expired', label: 'Expired' },
    { value: 'draft', label: 'Draft' },
    { value: 'closed', label: 'Closed' },
    { value: 'archived', label: 'Archived' },
    { value: 'suspended', label: 'Suspended' },
];

export default function AdminOpportunities({ opportunities, filters, counts, categories }: AdminOpportunitiesProps) {
    const visit = (changes: Partial<Filters>) => visitWithFilters(route('admin.opportunities.index'), { ...filters, ...changes });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Opportunities" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Opportunities</h1>
                    <p className="text-muted-foreground">
                        All listings from every organization, including drafts. Suspend listings that break the rules.
                    </p>
                </div>

                <div className="flex flex-wrap gap-1" role="tablist">
                    {tabs.map((tab) => (
                        <button
                            key={tab.value}
                            role="tab"
                            aria-selected={filters.status === tab.value}
                            onClick={() => visit({ status: tab.value })}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium',
                                filters.status === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {tab.value === 'reported' && <Flag className="h-3.5 w-3.5" />}
                            {tab.label}
                            <span className="tabular-nums opacity-70">{counts[tab.value] ?? 0}</span>
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
                    <FilterSelect
                        id="filter-category"
                        label="Category"
                        value={filters.category}
                        allLabel="All categories"
                        options={categories}
                        onChange={(category) => visit({ category })}
                    />
                    <SearchBox id="filter-q" label="Search" placeholder="Title or organization" initial={filters.q} onSearch={(q) => visit({ q })} />
                </div>

                <Card className="overflow-hidden py-0">
                    {opportunities.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Megaphone className="h-6 w-6" />
                            No opportunities match these filters.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Opportunity</th>
                                        <th className="px-4 py-3 font-medium">Category</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Deadline</th>
                                        <th className="px-4 py-3 text-right font-medium">Applications</th>
                                        <th className="px-4 py-3 text-right font-medium">Open reports</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {opportunities.data.map((o) => (
                                        <tr key={o.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Link href={route('admin.opportunities.show', o.id)} className="font-medium hover:underline">
                                                    {o.title}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    <Link href={route('admin.organizations.show', o.organization_id)} className="hover:underline">
                                                        {o.organization}
                                                    </Link>
                                                </p>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{o.category_label}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={o.status} label={o.status_label} />
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                                {o.application_deadline ? formatDate(o.application_deadline) : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">{o.applications_count}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {o.open_reports_count > 0 ? (
                                                    <span className="inline-flex items-center gap-1 font-medium text-red-700 dark:text-red-300">
                                                        <Flag className="h-3.5 w-3.5" /> {o.open_reports_count}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={opportunities} />
            </div>
        </AppLayout>
    );
}
