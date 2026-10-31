import { Head, Link } from '@inertiajs/react';
import { Building2, CircleCheck, ClipboardList, Clock, Flag, Hourglass, Megaphone, UserRoundX, Users, UsersRound } from 'lucide-react';
import { type ReactNode } from 'react';

import { ChartCard } from '@/components/charts/chart-card';
import { DailyColumnChart, DailyCountTable, type DailyCount } from '@/components/charts/daily-column-chart';
import { RankedBars, type RankedRow } from '@/components/charts/ranked-bars';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin dashboard', href: '/admin/dashboard' }];

type Row = Omit<RankedRow, 'href'>;

interface AdminDashboardProps {
    stats: {
        totalUsers: number;
        seekers: number;
        organizationUsers: number;
        inactiveUsers: number;
        verifiedOrganizations: number;
        openOpportunities: number;
        applications: number;
    };
    attention: { pendingOrganizations: number; openReports: number; stalledApplications: number; stalledDays: number };
    registrationsPerDay: DailyCount[];
    applicationsPerDay: DailyCount[];
    classifications: Row[];
    seekersByBarangay: Row[];
    openByCategory: Row[];
    applicationsByStatus: Row[];
}

const sum = (data: DailyCount[]) => data.reduce((total, day) => total + day.count, 0);

export default function AdminDashboard(props: AdminDashboardProps) {
    const { stats, attention } = props;

    const tiles = [
        { label: 'Registered users', value: stats.totalUsers, icon: Users, href: route('admin.users.index') },
        { label: 'Opportunity seekers', value: stats.seekers, icon: UsersRound, href: route('admin.users.index', { type: 'seeker' }) },
        {
            label: 'Organization personnel',
            value: stats.organizationUsers,
            icon: Building2,
            href: route('admin.users.index', { type: 'organization' }),
        },
        { label: 'Deactivated accounts', value: stats.inactiveUsers, icon: UserRoundX, href: route('admin.users.index', { status: 'inactive' }) },
        {
            label: 'Verified organizations',
            value: stats.verifiedOrganizations,
            icon: CircleCheck,
            href: route('admin.organizations.index', { status: 'verified' }),
        },
        {
            label: 'Open opportunities',
            value: stats.openOpportunities,
            icon: Megaphone,
            href: route('admin.opportunities.index', { status: 'published' }),
        },
        { label: 'Applications', value: stats.applications, icon: ClipboardList, href: route('admin.applications.index') },
    ];

    const alerts = [
        attention.pendingOrganizations > 0 && {
            icon: Clock,
            text: `${attention.pendingOrganizations} ${attention.pendingOrganizations === 1 ? 'organization is' : 'organizations are'} waiting for verification`,
            href: route('admin.organizations.index', { status: 'pending' }),
        },
        attention.openReports > 0 && {
            icon: Flag,
            text: `${attention.openReports} open ${attention.openReports === 1 ? 'report' : 'reports'} on opportunities`,
            href: route('admin.reports.index'),
        },
        attention.stalledApplications > 0 && {
            icon: Hourglass,
            text: `${attention.stalledApplications} ${attention.stalledApplications === 1 ? 'application has' : 'applications have'} had no update for ${attention.stalledDays} days or more`,
            href: route('admin.applications.index', { status: 'stalled' }),
        },
    ].filter((alert) => alert !== false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Platform overview</h1>
                    <p className="text-muted-foreground">Live figures from the JoBauko database.</p>
                </div>

                {alerts.length > 0 && (
                    <div className="grid gap-2">
                        {alerts.map((alert) => (
                            <Link
                                key={alert.href}
                                href={alert.href}
                                className="flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                            >
                                <span className="flex items-center gap-2">
                                    <alert.icon className="h-4 w-4 shrink-0" />
                                    {alert.text}
                                </span>
                                <span className="shrink-0 font-medium">Review →</span>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <Link key={tile.label} href={tile.href} className="group">
                            <Card className="group-hover:border-primary/40 h-full transition-colors">
                                <CardHeader>
                                    <CardDescription className="flex items-center gap-2">
                                        <tile.icon className="h-4 w-4" /> {tile.label}
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">{tile.value.toLocaleString()}</CardTitle>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCard
                        title="New accounts"
                        description={`${sum(props.registrationsPerDay).toLocaleString()} in the last 30 days`}
                        chart={<DailyColumnChart data={props.registrationsPerDay} unit={['account', 'accounts']} />}
                        table={<DailyCountTable data={props.registrationsPerDay} label="Accounts" />}
                    />
                    <ChartCard
                        title="Applications submitted"
                        description={`${sum(props.applicationsPerDay).toLocaleString()} in the last 30 days`}
                        chart={<DailyColumnChart data={props.applicationsPerDay} unit={['application', 'applications']} />}
                        table={<DailyCountTable data={props.applicationsPerDay} label="Applications" />}
                    />
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-3">
                    <div className="grid gap-4 lg:col-span-2 lg:grid-cols-2">
                        <RankedCard
                            title="Seekers by current status"
                            description="Classification chosen by each seeker."
                            rows={props.classifications.map((row) => ({
                                ...row,
                                href: route('admin.users.index', { type: 'seeker', classification: row.key }),
                            }))}
                            empty="No seekers have registered yet."
                        />
                        <RankedCard
                            title="Open opportunities"
                            description="Listings accepting applications today."
                            rows={props.openByCategory.map((row) => ({
                                ...row,
                                href: route('admin.opportunities.index', { status: 'published', category: row.key }),
                            }))}
                            empty="No opportunities are open right now."
                        />
                        <RankedCard
                            title="Applications by status"
                            description="All applications, largest group first."
                            rows={props.applicationsByStatus.map((row) => ({ ...row, href: route('admin.applications.index', { status: row.key }) }))}
                            empty="No applications yet."
                            className="lg:col-span-2"
                        />
                    </div>
                    <RankedCard
                        title="Seekers by barangay"
                        description="Every Bauko barangay, so areas with few registrations stand out."
                        rows={props.seekersByBarangay.map((row) => ({
                            ...row,
                            href: row.key === 'none' ? undefined : route('admin.users.index', { type: 'seeker', barangay: row.key }),
                        }))}
                        empty="No seekers have registered yet."
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function RankedCard({
    title,
    description,
    rows,
    empty,
    className,
}: {
    title: string;
    description: ReactNode;
    rows: RankedRow[];
    empty: string;
    className?: string;
}) {
    const hasData = rows.some((row) => row.value > 0);

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {hasData ? <RankedBars rows={rows} valueLabel={title} /> : <p className="text-muted-foreground text-sm">{empty}</p>}
            </CardContent>
        </Card>
    );
}
