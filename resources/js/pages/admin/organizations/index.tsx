import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDate } from '@/types/organization';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Organizations', href: '/admin/organizations' }];

interface OrganizationRow {
    id: number;
    name: string;
    type: string;
    verification_status: string;
    verification_label: string;
    opportunities_count: number;
    registered_at: string;
}

interface AdminOrganizationsProps {
    organizations: Paginated<OrganizationRow>;
    filters: { status: string; search: string };
    counts: Record<string, number>;
    statuses: Option[];
}

export default function AdminOrganizations({ organizations, filters, counts, statuses }: AdminOrganizationsProps) {
    const [search, setSearch] = useState(filters.search);
    const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);

    const visit = (params: { status?: string; search?: string }) =>
        router.get(
            route('admin.organizations.index'),
            { status: filters.status === 'all' ? undefined : filters.status, search: filters.search || undefined, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visit({ search: search || undefined });
    };

    const tabs = [{ value: 'all', label: 'All', count: total }, ...statuses.map((s) => ({ ...s, count: Number(counts[s.value] ?? 0) }))];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organizations" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Organizations</h1>
                    <p className="text-muted-foreground">Review registrations and manage verified organizations.</p>
                </div>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-1" role="tablist">
                        {tabs.map((tab) => (
                            <button
                                key={tab.value}
                                role="tab"
                                aria-selected={filters.status === tab.value}
                                onClick={() => visit({ status: tab.value === 'all' ? undefined : tab.value })}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium',
                                    filters.status === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {tab.label}
                                <span className="tabular-nums opacity-70">{tab.count}</span>
                            </button>
                        ))}
                    </div>
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name" aria-label="Search by name" />
                        <Button type="submit" variant="outline" size="icon" aria-label="Search">
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <Card className="overflow-hidden py-0">
                    {organizations.data.length === 0 ? (
                        <div className="text-muted-foreground p-8 text-center text-sm">No organizations found.</div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Organization</th>
                                        <th className="px-4 py-3 font-medium">Type</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium">Opportunities</th>
                                        <th className="px-4 py-3 font-medium">Registered</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {organizations.data.map((o) => (
                                        <tr key={o.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-medium">
                                                <Link href={route('admin.organizations.show', o.id)} className="hover:underline">
                                                    {o.name}
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{o.type}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={o.verification_status} label={o.verification_label} />
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">{o.opportunities_count}</td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDate(o.registered_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={organizations} />
            </div>
        </AppLayout>
    );
}
