import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { channelLabels } from '@/types/announcement';
import { formatDateTime } from '@/types/application';

interface AnnouncementShowProps {
    announcement: {
        id: number;
        title: string;
        body: string;
        channels: string[];
        status: 'draft' | 'sending' | 'sent';
        status_label: string;
        author: string | null;
        recipient_count: number;
        created_at: string;
        sent_at: string | null;
    };
    audienceSummary: string[];
    reach: { total: number; email: number; sms: number } | null;
    delivery: { sms: Record<string, number>; email: Record<string, number> } | null;
}

export default function AnnouncementShow({ announcement, audienceSummary, reach, delivery }: AnnouncementShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Announcements', href: '/admin/announcements' },
        { title: announcement.title, href: `/admin/announcements/${announcement.id}` },
    ];
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [confirming, setConfirming] = useState(false);
    const [sending, setSending] = useState(false);
    const isDraft = announcement.status === 'draft';
    const channels = ['in_app', ...announcement.channels];

    const send = () =>
        router.post(
            route('admin.announcements.send', announcement.id),
            {},
            {
                onStart: () => setSending(true),
                onFinish: () => {
                    setSending(false);
                    setConfirming(false);
                },
            },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={announcement.title} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">{announcement.title}</h1>
                            <StatusBadge status={announcement.status} label={announcement.status_label} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {announcement.sent_at
                                ? `Sent ${formatDateTime(announcement.sent_at)}`
                                : `Created ${formatDateTime(announcement.created_at)}`}
                            {announcement.author && ` by ${announcement.author}`}
                        </p>
                    </div>
                    {isDraft && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route('admin.announcements.edit', announcement.id)}>
                                    <Pencil className="h-4 w-4" /> Edit
                                </Link>
                            </Button>
                            <Button variant="outline" onClick={() => router.delete(route('admin.announcements.destroy', announcement.id))}>
                                <Trash2 className="h-4 w-4" /> Delete draft
                            </Button>
                            <Button onClick={() => setConfirming(true)} disabled={reach?.total === 0}>
                                <Send className="h-4 w-4" /> Send
                            </Button>
                        </div>
                    )}
                </div>

                <InputError message={errors.announcement} />

                {announcement.status === 'sending' && (
                    <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        Sending in the background. Reload this page to see progress. If it stays here, check that the queue worker is running.
                    </p>
                )}

                <div className="grid items-start gap-6 md:grid-cols-[1fr_18rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Message</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-line">{announcement.body}</p>
                        </CardContent>
                    </Card>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Recipients</CardTitle>
                                <CardDescription>{channels.map((c) => channelLabels[c] ?? c).join(', ')}</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <ul className="grid gap-1">
                                    {audienceSummary.map((line) => (
                                        <li key={line}>{line}</li>
                                    ))}
                                </ul>
                                {reach && (
                                    <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 border-t pt-3">
                                        <dt className="text-muted-foreground">In-app</dt>
                                        <dd className="text-right tabular-nums">{reach.total.toLocaleString()}</dd>
                                        {announcement.channels.includes('email') && (
                                            <>
                                                <dt className="text-muted-foreground">Email</dt>
                                                <dd className="text-right tabular-nums">{reach.email.toLocaleString()}</dd>
                                            </>
                                        )}
                                        {announcement.channels.includes('sms') && (
                                            <>
                                                <dt className="text-muted-foreground">SMS</dt>
                                                <dd className="text-right tabular-nums">{reach.sms.toLocaleString()}</dd>
                                            </>
                                        )}
                                    </dl>
                                )}
                                {reach?.total === 0 && <p className="text-muted-foreground">No one matches this audience yet.</p>}
                            </CardContent>
                        </Card>

                        {delivery && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Delivery</CardTitle>
                                    <CardDescription>{announcement.recipient_count.toLocaleString()} recipients</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2 text-sm">
                                    <DeliveryRow label="Email" counts={delivery.email} />
                                    <DeliveryRow label="SMS" counts={delivery.sms} />
                                    <Link href={route('admin.delivery-logs.index')} className="text-primary text-xs hover:underline">
                                        Open delivery logs →
                                    </Link>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Send this announcement?</DialogTitle>
                        <DialogDescription>
                            It goes to {reach?.total.toLocaleString()} {reach?.total === 1 ? 'person' : 'people'} and cannot be recalled or edited
                            afterwards.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirming(false)}>
                            Cancel
                        </Button>
                        <Button onClick={send} disabled={sending}>
                            <Send className="h-4 w-4" /> Send now
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function DeliveryRow({ label, counts }: { label: string; counts: Record<string, number> }) {
    const sent = Number(counts.sent ?? 0);
    const failed = Number(counts.failed ?? 0);

    return (
        <div className="flex items-center justify-between gap-2">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">
                {sent.toLocaleString()} sent
                {failed > 0 && <span className="text-red-700 dark:text-red-300"> · {failed.toLocaleString()} failed</span>}
            </span>
        </div>
    );
}
