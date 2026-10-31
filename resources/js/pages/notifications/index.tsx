import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck, ChevronRight, Megaphone } from 'lucide-react';

import { openNotification, timeAgo } from '@/components/notification-bell';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type NotificationItem, type Paginated } from '@/types';

interface NotificationsProps {
    notifications: Paginated<NotificationItem>;
    filter: 'all' | 'unread';
    unreadCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

export default function Notifications({ notifications, filter, unreadCount }: NotificationsProps) {
    const tabs = [
        { value: 'all', label: 'All' },
        { value: 'unread', label: `Unread (${unreadCount})` },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Notifications</h1>
                        <p className="text-muted-foreground">Updates on your applications, opportunities and announcements.</p>
                    </div>
                    {unreadCount > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                        >
                            <CheckCheck className="h-4 w-4" /> Mark all as read
                        </Button>
                    )}
                </div>

                <div className="flex gap-1" role="tablist">
                    {tabs.map((tab) => (
                        <button
                            key={tab.value}
                            role="tab"
                            aria-selected={filter === tab.value}
                            onClick={() =>
                                router.get(route('notifications.index'), tab.value === 'unread' ? { filter: 'unread' } : {}, { preserveScroll: true })
                            }
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium',
                                filter === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <Card className="overflow-hidden py-0">
                    {notifications.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Bell className="h-6 w-6" />
                            {filter === 'unread' ? "You're all caught up." : 'No notifications yet.'}
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {notifications.data.map((n) =>
                                n.kind === 'AnnouncementPublished' ? (
                                    <li key={n.id} className={cn('grid gap-1 px-4 py-3 text-sm', !n.read && 'bg-primary/5')}>
                                        <p className="flex items-center gap-2 font-medium">
                                            <Megaphone className="text-primary h-4 w-4 shrink-0" /> {n.title}
                                        </p>
                                        <p className="whitespace-pre-line">{n.body}</p>
                                        <p className="text-muted-foreground flex items-center justify-between gap-2 text-xs">
                                            Announcement from the Municipality of Bauko · {timeAgo(n.created_at)}
                                            {!n.read && (
                                                <button
                                                    type="button"
                                                    onClick={() => openNotification(n)}
                                                    className="text-primary font-medium hover:underline"
                                                >
                                                    Mark as read
                                                </button>
                                            )}
                                        </p>
                                    </li>
                                ) : (
                                    <li key={n.id}>
                                        <button
                                            type="button"
                                            onClick={() => openNotification(n)}
                                            className={cn(
                                                'hover:bg-muted/40 flex w-full items-start gap-3 px-4 py-3 text-left text-sm',
                                                !n.read && 'bg-primary/5',
                                            )}
                                        >
                                            <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : 'bg-primary')} />
                                            <span className="grid flex-1 gap-0.5">
                                                <span className={cn(!n.read && 'font-medium')}>{n.message}</span>
                                                <span className="text-muted-foreground text-xs">{timeAgo(n.created_at)}</span>
                                            </span>
                                            {n.has_link && <ChevronRight className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />}
                                        </button>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </Card>

                <Pagination page={notifications} />
            </div>
        </AppLayout>
    );
}
