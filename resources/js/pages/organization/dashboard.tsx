import { Head, Link } from '@inertiajs/react';
import { Archive, CalendarClock, CircleCheck, ClipboardList, FilePen, FileX2, Inbox, Megaphone, Plus, Timer, Workflow } from 'lucide-react';

import { ChartCard } from '@/components/charts/chart-card';
import { DailyColumnChart, DailyCountTable, type DailyCount } from '@/components/charts/daily-column-chart';
import { RankedBars } from '@/components/charts/ranked-bars';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { VerificationBanner } from '@/components/verification-banner';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatDate, type OrganizationSummary } from '@/types/organization';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/org/dashboard' }];

interface ApplicationOverview {
    total: number;
    awaitingReview: number;
    inProgress: number;
    approved: number;
    last30Days: number;
    perDay: DailyCount[];
    byStatus: { status: string; label: string; count: number }[];
    topOpportunities: { id: number; title: string; total: number; new: number }[];
    recent: { id: number; name: string; opportunity: string; status: string; status_label: string; submitted_at: string }[];
}

interface OrganizationDashboardProps {
    organization: OrganizationSummary;
    stats: { published: number; active: number; expired: number; drafts: number; closed: number; archived: number };
    upcomingDeadlines: { id: number; title: string; category: string; deadline: string }[];
    applications: ApplicationOverview | null;
}

export default function OrganizationDashboard({ organization, stats, upcomingDeadlines, applications }: OrganizationDashboardProps) {
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

                {applications && <ApplicationsSection data={applications} />}

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Opportunities</h2>
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
                </section>

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

function ApplicationsSection({ data }: { data: ApplicationOverview }) {
    const kpis = [
        { label: 'Applications received', value: data.total, icon: ClipboardList, href: route('org.applications.index') },
        { label: 'Awaiting review', value: data.awaitingReview, icon: Inbox, href: route('org.applications.index', { status: 'submitted' }) },
        { label: 'In progress', value: data.inProgress, icon: Workflow, href: route('org.applications.index', { status: 'active' }) },
        { label: 'Approved', value: data.approved, icon: CircleCheck, href: route('org.applications.index', { status: 'approved' }) },
    ];

    return (
        <section className="grid gap-4">
            <h2 className="text-lg font-semibold">Applications</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {kpis.map((kpi) => (
                    <Link key={kpi.label} href={kpi.href} className="group">
                        <Card className="group-hover:border-primary/40 h-full transition-colors">
                            <CardHeader>
                                <CardDescription className="flex items-center gap-2">
                                    <kpi.icon className="h-4 w-4" /> {kpi.label}
                                </CardDescription>
                                <CardTitle className="text-3xl tabular-nums">{kpi.value.toLocaleString()}</CardTitle>
                            </CardHeader>
                        </Card>
                    </Link>
                ))}
            </div>

            {data.total === 0 ? (
                <Card>
                    <CardContent className="text-muted-foreground flex flex-col items-center gap-2 py-6 text-center text-sm">
                        <ClipboardList className="h-6 w-6" />
                        No applications yet. They will appear here as soon as applicants apply to your published opportunities.
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                        <ChartCard
                            title="Applications received"
                            description={`${data.last30Days.toLocaleString()} in the last 30 days`}
                            chart={<DailyColumnChart data={data.perDay} unit={['application', 'applications']} />}
                            table={<DailyCountTable data={data.perDay} label="Applications" />}
                        />
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">By status</CardTitle>
                                <CardDescription>All applications, largest group first.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <RankedBars
                                    valueLabel="Applications by status"
                                    rows={data.byStatus.map((row) => ({
                                        key: row.status,
                                        label: row.label,
                                        value: row.count,
                                        href: route('org.applications.index', { status: row.status }),
                                    }))}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Latest applications</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="divide-y">
                                    {data.recent.map((item) => (
                                        <li key={item.id} className="flex items-center justify-between gap-3 py-2.5 text-sm first:pt-0 last:pb-0">
                                            <div className="min-w-0">
                                                <Link href={route('org.applications.show', item.id)} className="font-medium hover:underline">
                                                    {item.name}
                                                </Link>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {item.opportunity} · {formatDate(item.submitted_at)}
                                                </p>
                                            </div>
                                            <StatusBadge status={item.status} label={item.status_label} />
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Most applied-to opportunities</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <table className="w-full text-sm">
                                    <thead className="text-muted-foreground text-left text-xs">
                                        <tr>
                                            <th className="pb-2 font-medium">Opportunity</th>
                                            <th className="pb-2 text-right font-medium">Awaiting review</th>
                                            <th className="pb-2 text-right font-medium">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {data.topOpportunities.map((item) => (
                                            <tr key={item.id}>
                                                <td className="py-2 pr-2">
                                                    <Link
                                                        href={route('org.applications.index', { opportunity: item.id })}
                                                        className="line-clamp-1 font-medium hover:underline"
                                                    >
                                                        {item.title}
                                                    </Link>
                                                </td>
                                                <td className="py-2 text-right tabular-nums">{item.new}</td>
                                                <td className="py-2 text-right tabular-nums">{item.total}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </section>
    );
}
