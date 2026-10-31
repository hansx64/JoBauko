import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';

import { FilterSelect, visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDateTime } from '@/types/application';

interface LogRow {
    id: number;
    recipient: string | null;
    user_id: number | null;
    address: string;
    content: string;
    topic: string;
    status: string;
    error: string | null;
    created_at: string;
}

interface Filters {
    channel: 'sms' | 'email';
    status: string | null;
    topic: string | null;
}

interface DeliveryLogsProps {
    logs: Paginated<LogRow>;
    filters: Filters;
    counts: Record<string, number>;
    topics: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Delivery logs', href: '/admin/delivery-logs' }];
const statusLabels: Record<string, string> = { sent: 'Sent', failed: 'Failed', queued: 'Queued' };

export default function DeliveryLogs({ logs, filters, counts, topics }: DeliveryLogsProps) {
    const visit = (changes: Partial<Filters>) => visitWithFilters(route('admin.delivery-logs.index'), { ...filters, ...changes });
    const topicLabels = Object.fromEntries(topics.map((t) => [t.value, t.label]));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Delivery logs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Delivery logs</h1>
                    <p className="text-muted-foreground">
                        Every SMS and notification email JoBauko sent. “Sent” means the provider accepted it, not that it reached the phone or inbox.
                    </p>
                </div>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex gap-1" role="tablist">
                        {(['sms', 'email'] as const).map((channel) => (
                            <button
                                key={channel}
                                role="tab"
                                aria-selected={filters.channel === channel}
                                onClick={() => visit({ channel, status: null, topic: null })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium',
                                    filters.channel === channel ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {channel === 'sms' ? 'SMS' : 'Email'}
                            </button>
                        ))}
                    </div>
                    <div className="grid w-full gap-3 sm:w-auto sm:grid-cols-2">
                        <FilterSelect
                            id="filter-status"
                            label="Status"
                            value={filters.status}
                            allLabel="All statuses"
                            options={Object.entries(statusLabels).map(([value, label]) => ({ value, label: `${label} (${counts[value] ?? 0})` }))}
                            onChange={(status) => visit({ status })}
                        />
                        <FilterSelect
                            id="filter-topic"
                            label="Topic"
                            value={filters.topic}
                            allLabel="All topics"
                            options={topics}
                            onChange={(topic) => visit({ topic })}
                        />
                    </div>
                </div>

                <Card className="overflow-hidden py-0">
                    {logs.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Inbox className="h-6 w-6" />
                            Nothing sent yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Recipient</th>
                                        <th className="px-4 py-3 font-medium">{filters.channel === 'sms' ? 'Message' : 'Subject'}</th>
                                        <th className="px-4 py-3 font-medium">Topic</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Time</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="align-top">
                                            <td className="px-4 py-3">
                                                {log.user_id ? (
                                                    <Link href={route('admin.users.show', log.user_id)} className="font-medium hover:underline">
                                                        {log.recipient}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">Deleted account</span>
                                                )}
                                                <p className="text-muted-foreground text-xs">{log.address}</p>
                                            </td>
                                            <td className="max-w-md px-4 py-3">
                                                {log.content}
                                                {log.error && <p className="mt-1 text-xs text-red-700 dark:text-red-300">{log.error}</p>}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{topicLabels[log.topic] ?? log.topic}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={log.status} label={statusLabels[log.status] ?? log.status} />
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={logs} />
            </div>
        </AppLayout>
    );
}
