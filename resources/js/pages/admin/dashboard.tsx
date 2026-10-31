import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, Clock, UserRoundX, Users, UsersRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin dashboard',
        href: '/admin/dashboard',
    },
];

interface AdminDashboardProps {
    stats: {
        totalUsers: number;
        seekers: number;
        organizationUsers: number;
        inactiveUsers: number;
        pendingOrganizations: number;
    };
    classifications: { label: string; total: number }[];
}

export default function AdminDashboard({ stats, classifications }: AdminDashboardProps) {
    const tiles = [
        { label: 'Registered users', value: stats.totalUsers, icon: Users },
        { label: 'Opportunity seekers', value: stats.seekers, icon: UsersRound },
        { label: 'Organization personnel', value: stats.organizationUsers, icon: Building2 },
        { label: 'Deactivated accounts', value: stats.inactiveUsers, icon: UserRoundX },
    ];
    const maxClassification = Math.max(1, ...classifications.map((c) => c.total));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Platform overview</h1>
                    <p className="text-muted-foreground">Live figures from the JoBauko database.</p>
                </div>

                {stats.pendingOrganizations > 0 && (
                    <Link
                        href={route('admin.organizations.index', { status: 'pending' })}
                        className="flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <span className="flex items-center gap-2">
                            <Clock className="h-4 w-4" />
                            {stats.pendingOrganizations} {stats.pendingOrganizations === 1 ? 'organization is' : 'organizations are'} waiting for
                            verification
                        </span>
                        <span className="font-medium">Review →</span>
                    </Link>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <Card key={tile.label}>
                            <CardHeader>
                                <CardDescription className="flex items-center gap-2">
                                    <tile.icon className="h-4 w-4" /> {tile.label}
                                </CardDescription>
                                <CardTitle className="text-3xl tabular-nums">{tile.value.toLocaleString()}</CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Seekers by current status</CardTitle>
                        <CardDescription>Classification chosen by each seeker</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {classifications.map((row) => (
                            <div key={row.label} className="grid grid-cols-[9rem_1fr_3rem] items-center gap-3 text-sm sm:grid-cols-[11rem_1fr_3rem]">
                                <span className="truncate">{row.label}</span>
                                <div className="bg-muted h-2 rounded-full">
                                    <div className="bg-primary h-2 rounded-full" style={{ width: `${(row.total / maxClassification) * 100}%` }} />
                                </div>
                                <span className="text-right tabular-nums">{row.total}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
