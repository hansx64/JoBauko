import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type NotificationItem, type SharedData } from '@/types';

export function timeAgo(date: string) {
    const seconds = Math.round((Date.now() - new Date(date).getTime()) / 1000);
    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    if (seconds < 60) return format.format(-seconds, 'second');
    if (seconds < 3600) return format.format(-Math.round(seconds / 60), 'minute');
    if (seconds < 86400) return format.format(-Math.round(seconds / 3600), 'hour');
    if (seconds < 86400 * 30) return format.format(-Math.round(seconds / 86400), 'day');

    return new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Opening a notification marks it read and goes to what it is about.
 */
export function openNotification(notification: NotificationItem) {
    router.visit(notification.has_link ? route('notifications.open', notification.id) : route('notifications.index'));
}

export function NotificationBell() {
    const { notifications } = usePage<SharedData>().props;

    if (!notifications) {
        return null;
    }

    const { unread, recent } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
                >
                    <Bell className="h-5 w-5" />
                    {unread > 0 && (
                        <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifications
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-primary text-xs font-medium hover:underline"
                            onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                        >
                            Mark all as read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {recent.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-6 text-center text-sm">No notifications yet.</p>
                ) : (
                    recent.map((notification) => (
                        <DropdownMenuItem key={notification.id} onSelect={() => openNotification(notification)} className="items-start gap-2 py-2">
                            <span
                                className={cn('mt-1.5 size-2 shrink-0 rounded-full', notification.read ? 'bg-transparent' : 'bg-primary')}
                                aria-label={notification.read ? undefined : 'Unread'}
                            />
                            <span className="grid gap-0.5">
                                <span className={cn('line-clamp-3 text-sm', !notification.read && 'font-medium')}>{notification.message}</span>
                                <span className="text-muted-foreground text-xs">{timeAgo(notification.created_at)}</span>
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('notifications.index')} className="justify-center text-sm font-medium">
                        See all notifications
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
