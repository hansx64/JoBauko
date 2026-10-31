import { Head, Link } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';

import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { channelLabels } from '@/types/announcement';
import { formatDateTime } from '@/types/application';

interface AnnouncementRow {
    id: number;
    title: string;
    status: string;
    status_label: string;
    channels: string[];
    recipient_count: number;
    author: string | null;
    created_at: string;
    sent_at: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Announcements', href: '/admin/announcements' }];

export default function AdminAnnouncements({ announcements }: { announcements: Paginated<AnnouncementRow> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Announcements" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Announcements</h1>
                        <p className="text-muted-foreground">Messages from the municipality to seekers, organizations or administrators.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.announcements.create')}>
                            <Plus className="h-4 w-4" /> New announcement
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden py-0">
                    {announcements.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Megaphone className="h-6 w-6" />
                            No announcements yet.
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {announcements.data.map((a) => (
                                <li key={a.id}>
                                    <Link href={route('admin.announcements.show', a.id)} className="hover:bg-muted/30 grid gap-1 px-4 py-3 text-sm">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium">{a.title}</span>
                                            <StatusBadge status={a.status} label={a.status_label} />
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            {a.channels.map((c) => channelLabels[c] ?? c).join(', ')}
                                            {a.sent_at
                                                ? ` · sent ${formatDateTime(a.sent_at)} to ${a.recipient_count.toLocaleString()} ${a.recipient_count === 1 ? 'person' : 'people'}`
                                                : ` · created ${formatDateTime(a.created_at)}`}
                                            {a.author && ` · ${a.author}`}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Pagination page={announcements} />
            </div>
        </AppLayout>
    );
}
