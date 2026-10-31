import { Head, Link } from '@inertiajs/react';
import { ChevronRight, ClipboardList } from 'lucide-react';

import { categoryIcons, categoryTones } from '@/components/opportunities/opportunity-card';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { type ApplicationListItem } from '@/types/application';
import { formatDate } from '@/types/organization';

type Tab = 'all' | 'active' | 'approved' | 'rejected' | 'withdrawn';

const tabs: { key: Tab; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'active', label: 'In progress' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'withdrawn', label: 'Withdrawn' },
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My applications', href: '/applications' }];

export default function Applications({
    applications,
    tab,
    counts,
}: {
    applications: Paginated<ApplicationListItem>;
    tab: Tab;
    counts: Record<Tab, number>;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My applications" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">My applications</h1>
                    <p className="text-muted-foreground">Track every application you submitted and see what happens next.</p>
                </div>

                <nav className="flex flex-wrap gap-2" aria-label="Filter applications">
                    {tabs.map((item) => (
                        <Link
                            key={item.key}
                            href={route('seeker.applications.index', item.key === 'all' ? {} : { tab: item.key })}
                            preserveScroll
                            className={cn(
                                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                tab === item.key ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                        >
                            {item.label}
                            <span className={cn('tabular-nums', tab === item.key ? 'opacity-80' : 'text-muted-foreground')}>{counts[item.key]}</span>
                        </Link>
                    ))}
                </nav>

                {applications.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border p-10 text-center">
                        <ClipboardList className="text-muted-foreground h-8 w-8" />
                        <p className="font-medium">{counts.all === 0 ? 'You have not applied to anything yet' : 'No applications in this list'}</p>
                        <p className="text-muted-foreground text-sm">
                            {counts.all === 0
                                ? 'Find a job, scholarship or livelihood program and apply from its details page.'
                                : 'Choose another filter to see your other applications.'}
                        </p>
                        {counts.all === 0 && (
                            <Button asChild>
                                <Link href={route('opportunities.index')}>Browse opportunities</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {applications.data.map((application) => {
                            const Icon = categoryIcons[application.opportunity.category];

                            return (
                                <li key={application.id}>
                                    <Link
                                        href={route('seeker.applications.show', application.id)}
                                        className="hover:bg-muted/50 flex items-center gap-4 p-4 transition-colors"
                                    >
                                        {application.opportunity.organization.logo_url ? (
                                            <img
                                                src={application.opportunity.organization.logo_url}
                                                alt=""
                                                className="size-11 shrink-0 rounded-lg border object-cover"
                                            />
                                        ) : (
                                            <span
                                                className={cn(
                                                    'flex size-11 shrink-0 items-center justify-center rounded-lg',
                                                    categoryTones[application.opportunity.category],
                                                )}
                                            >
                                                <Icon className="h-5 w-5" />
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">{application.opportunity.title}</p>
                                            <p className="text-muted-foreground truncate text-sm">
                                                {application.opportunity.organization.name} · {application.opportunity.category_label}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Applied {formatDate(application.submitted_at)}
                                                {application.updated_at && ` · Updated ${formatDate(application.updated_at)}`}
                                            </p>
                                        </div>
                                        <StatusBadge status={application.status} label={application.status_label} />
                                        <ChevronRight className="text-muted-foreground hidden h-4 w-4 shrink-0 sm:block" />
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}

                <Pagination page={applications} />
            </div>
        </AppLayout>
    );
}
