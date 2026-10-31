import { Head, Link } from '@inertiajs/react';
import { Archive, CalendarClock, FilePen, FileX2, Megaphone, Plus, Timer } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { VerificationBanner } from '@/components/verification-banner';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatDate, type OrganizationSummary } from '@/types/organization';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/org/dashboard' }];

interface OrganizationDashboardProps {
    organization: OrganizationSummary;
    stats: { published: number; active: number; expired: number; drafts: number; closed: number; archived: number };
    upcomingDeadlines: { id: number; title: string; category: string; deadline: string }[];
}

export default function OrganizationDashboard({ organization, stats, upcomingDeadlines }: OrganizationDashboardProps) {
    const tiles = [
        { label: 'Active', value: stats.active, icon: Megaphone, href: '/org/opportunities?status=published' },
        { label: 'Drafts', value: stats.drafts, icon: FilePen, href: '/org/opportunities?status=draft' },
        { label: 'Expired', value: stats.expired, icon: Timer, href: '/org/opportunities?status=expired' },
        { label: 'Closed', value: stats.closed, icon: FileX2, href: '/org/opportunities?status=closed' },
        { label: 'Archived', value: stats.archived, icon: Archive, href: '/org/opportunities?status=archived' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        {organization.logo_url && <img src={organization.logo_url} alt="" className="size-12 rounded-lg border object-cover" />}
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">{organization.name}</h1>
                            <p className="text-muted-foreground">{stats.published} published opportunities</p>
                        </div>
                    </div>
                    {organization.verification_status !== 'suspended' && (
                        <Button asChild>
                            <Link href={route('org.opportunities.create')}>
                                <Plus className="h-4 w-4" /> New opportunity
                            </Link>
                        </Button>
                    )}
                </div>

                <VerificationBanner organization={organization} />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {tiles.map((tile) => (
                        <Link key={tile.label} href={tile.href} className="group">
                            <Card className="group-hover:border-primary/40 h-full transition-colors">
                                <CardHeader>
                                    <CardDescription className="flex items-center gap-2">
                                        <tile.icon className="h-4 w-4" /> {tile.label}
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">{tile.value}</CardTitle>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CalendarClock className="h-4 w-4" /> Upcoming deadlines
                        </CardTitle>
                        <CardDescription>Open opportunities closing soonest.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {upcomingDeadlines.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No open opportunities.</p>
                        ) : (
                            <ul className="divide-y">
                                {upcomingDeadlines.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-4 py-2.5 text-sm first:pt-0 last:pb-0">
                                        <Link href={route('org.opportunities.edit', item.id)} className="font-medium hover:underline">
                                            {item.title}
                                            <span className="text-muted-foreground font-normal"> · {item.category}</span>
                                        </Link>
                                        <span className="text-muted-foreground shrink-0">{formatDate(item.deadline)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
