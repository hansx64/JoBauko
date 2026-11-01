import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { OpportunityActions } from '@/components/opportunity-actions';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { VerificationBanner } from '@/components/verification-banner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated, type SharedData } from '@/types';
import { formatDate, type OpportunityListItem, type OrganizationSummary } from '@/types/organization';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Opportunities', href: '/org/opportunities' }];

const TABS = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Drafts' },
    { value: 'published', label: 'Active' },
    { value: 'expired', label: 'Expired' },
    { value: 'closed', label: 'Closed' },
    { value: 'archived', label: 'Archived' },
];

interface OpportunitiesIndexProps {
    organization: OrganizationSummary;
    opportunities: Paginated<OpportunityListItem>;
    filters: { status: string; search: string };
    canCreate: boolean;
}

export default function OpportunitiesIndex({ organization, opportunities, filters, canCreate }: OpportunitiesIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const { errors, auth } = usePage<SharedData>().props;
    const canViewApplications = auth.organization?.permissions.includes('view_applications') ?? false;

    const visit = (params: { status?: string; search?: string }) =>
        router.get(
            route('org.opportunities.index'),
            { status: filters.status, search: filters.search, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visit({ search });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Opportunities" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Opportunities</h1>
                        <p className="text-muted-foreground">Jobs, scholarships, livelihood programs and trainings posted by {organization.name}.</p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={route('org.opportunities.create')}>
                                <Plus className="h-4 w-4" /> New opportunity
                            </Link>
                        </Button>
                    )}
                </div>

                <VerificationBanner organization={organization} />
                <InputError message={(errors as Record<string, string>).status} />

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-1" role="tablist">
                        {TABS.map((tab) => (
                            <button
                                key={tab.value}
                                role="tab"
                                aria-selected={filters.status === tab.value}
                                onClick={() => visit({ status: tab.value })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium',
                                    filters.status === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by title"
                            aria-label="Search by title"
                        />
                        <Button type="submit" variant="outline" size="icon" aria-label="Search">
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                </div>

                <Card className="overflow-hidden py-0">
                    {opportunities.data.length === 0 ? (
                        <div className="text-muted-foreground p-8 text-center text-sm">
                            {filters.search || filters.status !== 'all' ? 'No opportunities match these filters.' : 'No opportunities yet.'}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Title</th>
                                        <th className="px-4 py-3 font-medium">Category</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Deadline</th>
                                        <th className="px-4 py-3 text-right font-medium">Applicants</th>
                                        <th className="px-4 py-3 font-medium">Last updated</th>
                                        <th className="px-4 py-3">
                                            <span className="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {opportunities.data.map((o) => (
                                        <tr key={o.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-medium">
                                                <Link href={route('org.opportunities.edit', o.id)} className="hover:underline">
                                                    {o.title}
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{o.category_label}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={o.status} label={o.status_label} />
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(o.deadline)}</td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {o.applications_count > 0 && canViewApplications ? (
                                                    <Link
                                                        href={route('org.applications.index', { opportunity: o.id })}
                                                        className="text-primary font-medium hover:underline"
                                                    >
                                                        {o.applications_count}
                                                    </Link>
                                                ) : (
                                                    o.applications_count
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDate(o.updated_at)}</td>
                                            <td className="px-2 py-3 text-right">
                                                {canCreate && (
                                                    <OpportunityActions
                                                        opportunity={{ id: o.id, title: o.title, status: o.status, deadline: o.deadline }}
                                                    />
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
