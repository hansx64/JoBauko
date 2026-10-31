import { CompletionMeter } from '@/components/profile/completion-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Completion } from '@/types/profile';
import { Head, Link, usePage } from '@inertiajs/react';
import { BellRing, MapPin, Smartphone, UserRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    completion: Completion;
    classification: string | null;
    barangay: string | null;
    mobile: { masked: string | null; verified: boolean };
    smsConsent: boolean;
    selectedTopics: string[];
}

export default function Dashboard({ completion, classification, barangay, mobile, smsConsent, selectedTopics }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user.name.split(' ')[0];
    const nextStep = completion.sections.find((s) => !s.complete);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Welcome, {firstName}</h1>
                    <p className="text-muted-foreground">Your JoBauko account at a glance.</p>
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
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <CompletionMeter percent={completion.percent} />
                        <Button asChild size="sm" variant={nextStep ? 'default' : 'outline'} className="shrink-0">
                            <Link href={nextStep ? `/profile#${nextStep.key}` : '/profile'}>{nextStep ? 'Complete profile' : 'View profile'}</Link>
                        </Button>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription className="flex items-center gap-2">
                                <UserRound className="h-4 w-4" /> Current status
                            </CardDescription>
                            <CardTitle className="text-xl">{classification ?? 'Not set'}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription className="flex items-center gap-2">
                                <MapPin className="h-4 w-4" /> Barangay
                            </CardDescription>
                            <CardTitle className="text-xl">{barangay ?? 'Not set'}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription className="flex items-center gap-2">
                                <Smartphone className="h-4 w-4" /> Mobile number
                            </CardDescription>
                            <CardTitle className="flex items-center gap-2 text-xl">
                                {mobile.masked ?? 'Not set'}
                                {mobile.masked && (
                                    <Badge variant={mobile.verified ? 'default' : 'outline'}>{mobile.verified ? 'Verified' : 'Unverified'}</Badge>
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BellRing className="h-4 w-4" /> Notification topics
                        </CardTitle>
                        <CardDescription>
                            {smsConsent
                                ? 'SMS notifications are on for the topics below.'
                                : "SMS notifications are off. You haven't given SMS consent."}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {selectedTopics.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {selectedTopics.map((topic) => (
                                    <Badge key={topic} variant="secondary">
                                        {topic}
                                    </Badge>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No topics selected.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
