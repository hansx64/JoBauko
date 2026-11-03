import { OpportunityCard } from '@/components/opportunities/opportunity-card';
import { CompletionMeter } from '@/components/profile/completion-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type OpportunityCardData } from '@/types/opportunity';
import { formatDate } from '@/types/organization';
import { type Completion } from '@/types/profile';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BellRing, Bookmark, CalendarClock, ClipboardList, Info, MapPin, Smartphone, Sparkles, UserRound } from 'lucide-react';
import { ReactNode, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    applications: {
        total: number;
        active: number;
        approved: number;
        recent: { id: number; title: string; organization: string; status: string; status_label: string; updated_at: string | null }[];
    };
    recommended: OpportunityCardData[];
    latest: Record<'job' | 'scholarship' | 'livelihood' | 'training', OpportunityCardData[]>;
    saved: OpportunityCardData[];
    savedCount: number;
    deadlines: { id: number; slug: string; title: string; deadline: string; days_left: number }[];
    completion: Completion;
    classification: string | null;
    barangay: string | null;
    mobile: { masked: string | null; verified: boolean };
    smsConsent: boolean;
    selectedTopics: string[];
}

const latestTabs = [
    { key: 'job', label: 'Jobs' },
    { key: 'scholarship', label: 'Scholarships' },
    { key: 'livelihood', label: 'Livelihood' },
    { key: 'training', label: 'Trainings' },
] as const;

export default function Dashboard({
    applications,
    recommended,
    latest,
    saved,
    savedCount,
    deadlines,
    completion,
    classification,
    barangay,
    mobile,
    smsConsent,
    selectedTopics,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user.name.split(' ')[0];
    const nextStep = completion.sections.find((s) => !s.complete);
    const [latestTab, setLatestTab] = useState<(typeof latestTabs)[number]['key']>('job');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Welcome, {firstName}</h1>
                        <p className="text-muted-foreground">Here are opportunities picked for you, and where you left off.</p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={route('opportunities.index')}>
                            Browse all opportunities <ArrowRight className="h-4 w-4" />
                        </Link>
                    </Button>
                </div>

                {!mobile.verified && mobile.masked && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40">
                        <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <Smartphone className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                                <div>
                                    <p className="font-medium">Verify your mobile number</p>
                                    <p className="text-muted-foreground text-sm">
                                        Confirm {mobile.masked} so we can send SMS notifications you opt into.
                                    </p>
                                </div>
                            </div>
                            <Button asChild size="sm">
                                <Link href={route('mobile.verification.notice')}>Verify now</Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_20rem]">
                    <div className="grid min-w-0 gap-6">
                        <DashboardSection
                            icon={<Sparkles className="h-4 w-4 text-emerald-600" />}
                            title="Recommended for you"
                            description="Based on your skills, education, experience, location and interests."
                            action={
                                <Link
                                    href={route('opportunities.index', { sort: 'match' })}
                                    className="text-primary text-sm font-medium hover:underline"
                                >
                                    See all matches
                                </Link>
                            }
                        >
                            {recommended.length === 0 ? (
                                <EmptyState>
                                    No strong matches right now.{' '}
                                    {nextStep ? (
                                        <>
                                            Completing your profile helps:{' '}
                                            <Link href={`/profile#${nextStep.key}`} className="text-primary font-medium hover:underline">
                                                {nextStep.hint}
                                            </Link>
                                        </>
                                    ) : (
                                        'New opportunities are matched to you as soon as they are published.'
                                    )}
                                </EmptyState>
                            ) : (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {recommended.map((opportunity) => (
                                            <OpportunityCard key={opportunity.id} opportunity={opportunity} showSave showReasons />
                                        ))}
                                    </div>
                                    <p className="text-muted-foreground mt-3 flex items-start gap-2 text-xs">
                                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                        Matches are a guide based on your profile. They don't decide eligibility, and you can apply to any
                                        opportunity.
                                    </p>
                                </>
                            )}
                        </DashboardSection>

                        <DashboardSection title="Latest opportunities" description="Newly published and open for applications.">
                            <div className="mb-4 flex gap-1" role="tablist">
                                {latestTabs.map((tab) => (
                                    <button
                                        key={tab.key}
                                        role="tab"
                                        aria-selected={latestTab === tab.key}
                                        onClick={() => setLatestTab(tab.key)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-sm font-medium',
                                            latestTab === tab.key ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                            {latest[latestTab].length === 0 ? (
                                <EmptyState>No open {latestTabs.find((t) => t.key === latestTab)?.label.toLowerCase()} right now.</EmptyState>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                                    {latest[latestTab].map((opportunity) => (
                                        <OpportunityCard key={opportunity.id} opportunity={opportunity} showSave />
                                    ))}
                                </div>
                            )}
                            <Link
                                href={route('opportunities.index', { category: latestTab })}
                                className="text-primary mt-3 inline-block text-sm font-medium hover:underline"
                            >
                                View all {latestTabs.find((t) => t.key === latestTab)?.label.toLowerCase()} →
                            </Link>
                        </DashboardSection>

                        <DashboardSection
                            icon={<Bookmark className="h-4 w-4" />}
                            title="Saved opportunities"
                            description={savedCount > 0 ? `${savedCount} saved` : 'Bookmark opportunities to review them later.'}
                            action={
                                savedCount > 0 && (
                                    <Link href={route('seeker.saved.index')} className="text-primary text-sm font-medium hover:underline">
                                        View all
                                    </Link>
                                )
                            }
                        >
                            {saved.length === 0 ? (
                                <EmptyState>You haven't saved any opportunities yet.</EmptyState>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {saved.map((opportunity) => (
                                        <OpportunityCard key={opportunity.id} opportunity={opportunity} showSave />
                                    ))}
                                </div>
                            )}
                        </DashboardSection>
                    </div>

                    <aside className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ClipboardList className="h-4 w-4" /> My applications
                                </CardTitle>
                                <CardDescription>
                                    {applications.total === 0
                                        ? 'You have not applied to anything yet.'
                                        : `${applications.active} in progress · ${applications.approved} approved · ${applications.total} total`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {applications.recent.length > 0 && (
                                    <ul className="grid gap-3 text-sm">
                                        {applications.recent.map((item) => (
                                            <li key={item.id} className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <Link
                                                        href={route('seeker.applications.show', item.id)}
                                                        className="line-clamp-1 font-medium hover:underline"
                                                    >
                                                        {item.title}
                                                    </Link>
                                                    <p className="text-muted-foreground truncate text-xs">{item.organization}</p>
                                                </div>
                                                <StatusBadge status={item.status} label={item.status_label} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Button asChild size="sm" variant="outline">
                                    <Link href={applications.total === 0 ? route('opportunities.index') : route('seeker.applications.index')}>
                                        {applications.total === 0 ? 'Find something to apply to' : 'View all applications'}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-baseline justify-between text-base">
                                    Profile completion
                                    <span className="text-2xl font-semibold tabular-nums">{completion.percent}%</span>
                                </CardTitle>
                                <CardDescription>
                                    {nextStep ? `Next: ${nextStep.hint}` : 'Your profile is complete. Keep it up to date as things change.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <CompletionMeter percent={completion.percent} />
                                <Button asChild size="sm" variant={nextStep ? 'default' : 'outline'}>
                                    <Link href={nextStep ? `/profile#${nextStep.key}` : '/profile'}>
                                        {nextStep ? 'Complete profile' : 'View profile'}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CalendarClock className="h-4 w-4" /> Upcoming deadlines
                                </CardTitle>
                                <CardDescription>Saved opportunities closing within 14 days.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {deadlines.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Nothing closing soon.</p>
                                ) : (
                                    <ul className="grid gap-3 text-sm">
                                        {deadlines.map((item) => (
                                            <li key={item.id}>
                                                <Link href={route('opportunities.show', item.slug)} className="font-medium hover:underline">
                                                    {item.title}
                                                </Link>
                                                <p
                                                    className={cn(
                                                        'text-xs',
                                                        item.days_left <= 3 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {item.days_left === 0 ? 'Closes today' : `${item.days_left} days left`} ·{' '}
                                                    {formatDate(item.deadline)}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">My account</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <AccountRow icon={UserRound} label="Current status" value={classification ?? 'Not set'} />
                                <AccountRow icon={MapPin} label="Barangay" value={barangay ?? 'Not set'} />
                                <AccountRow
                                    icon={Smartphone}
                                    label="Mobile number"
                                    value={
                                        <span className="flex items-center gap-2">
                                            {mobile.masked ?? 'Not set'}
                                            {mobile.masked && (
                                                <Badge variant={mobile.verified ? 'default' : 'outline'}>
                                                    {mobile.verified ? 'Verified' : 'Unverified'}
                                                </Badge>
                                            )}
                                        </span>
                                    }
                                />
                                <AccountRow
                                    icon={BellRing}
                                    label={smsConsent ? 'SMS notifications on for' : 'SMS notifications off'}
                                    value={
                                        smsConsent && selectedTopics.length > 0 ? (
                                            <span className="flex flex-wrap gap-1">
                                                {selectedTopics.map((topic) => (
                                                    <Badge key={topic} variant="secondary" className="font-normal">
                                                        {topic}
                                                    </Badge>
                                                ))}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {smsConsent ? 'No topics selected' : "You haven't given SMS consent"}
                                            </span>
                                        )
                                    }
                                />
                                <Link href={route('notification-preferences.edit')} className="text-primary text-sm font-medium hover:underline">
                                    Notification settings →
                                </Link>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function DashboardSection({
    icon,
    title,
    description,
    action,
    children,
}: {
    icon?: ReactNode;
    title: string;
    description: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section>
            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        {icon}
                        {title}
                    </h2>
                    <p className="text-muted-foreground text-sm">{description}</p>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return <div className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">{children}</div>;
}

function AccountRow({ icon: Icon, label, value }: { icon: typeof UserRound; label: string; value: ReactNode }) {
    return (
        <div className="flex items-start gap-3">
            <Icon className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
            <div className="min-w-0">
                <p className="text-muted-foreground text-xs">{label}</p>
                <div className="font-medium">{value}</div>
            </div>
        </div>
    );
}
