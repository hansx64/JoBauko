import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';

import { FilterSelect, SearchBox, visitWithFilters } from '@/components/admin/filters';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type Barangay, type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDate } from '@/types/organization';

interface UserRow {
    id: number;
    name: string;
    email: string;
    account_type: string;
    account_label: string;
    detail: string | null;
    barangay: string | null;
    is_active: boolean;
    registered_at: string;
    last_login_at: string | null;
}

interface Filters {
    type: string;
    classification: string | null;
    status: string | null;
    barangay: number | null;
    q: string;
}

interface AdminUsersProps {
    users: Paginated<UserRow>;
    filters: Filters;
    counts: Record<string, number>;
    options: { types: Option[]; classifications: Option[]; barangays: Barangay[] };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/admin/users' }];

export default function AdminUsers({ users, filters, counts, options }: AdminUsersProps) {
    const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);
    const visit = (changes: Partial<Filters>) => visitWithFilters(route('admin.users.index'), { ...filters, ...changes });
    const tabs = [{ value: 'all', label: 'All', count: total }, ...options.types.map((t) => ({ ...t, count: Number(counts[t.value] ?? 0) }))];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Users</h1>
                    <p className="text-muted-foreground">Every account on JoBauko. Deactivate accounts that misuse the portal.</p>
                </div>

                <div className="flex flex-wrap gap-1" role="tablist">
                    {tabs.map((tab) => (
                        <button
                            key={tab.value}
                            role="tab"
                            aria-selected={filters.type === tab.value}
                            onClick={() => visit({ type: tab.value, classification: tab.value === 'seeker' ? filters.classification : null })}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium',
                                filters.type === tab.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {tab.label}
                            <span className="tabular-nums opacity-70">{tab.count}</span>
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <FilterSelect
                        id="filter-status"
                        label="Account status"
                        value={filters.status}
                        allLabel="Active and deactivated"
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Deactivated' },
                        ]}
                        onChange={(status) => visit({ status })}
                    />
                    <FilterSelect
                        id="filter-classification"
                        label="Seeker classification"
                        value={filters.classification}
                        allLabel="All classifications"
                        options={options.classifications}
                        onChange={(classification) => visit({ classification, type: classification ? 'seeker' : filters.type })}
                    />
                    <FilterSelect
                        id="filter-barangay"
                        label="Barangay"
                        value={filters.barangay}
                        allLabel="All barangays"
                        options={options.barangays.map((b) => ({ value: b.id, label: b.name }))}
                        onChange={(barangay) => visit({ barangay: barangay ? Number(barangay) : null })}
                    />
                    <SearchBox id="filter-q" label="Search" placeholder="Name, email or mobile" initial={filters.q} onSearch={(q) => visit({ q })} />
                </div>

                <Card className="overflow-hidden py-0">
                    {users.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <Users className="h-6 w-6" />
                            No accounts match these filters.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Name</th>
                                        <th className="px-4 py-3 font-medium">Account</th>
                                        <th className="px-4 py-3 font-medium">Barangay</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Registered</th>
                                        <th className="px-4 py-3 font-medium">Last sign-in</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Link href={route('admin.users.show', user.id)} className="font-medium hover:underline">
                                                    {user.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">{user.email}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.account_label}
                                                {user.detail && <p className="text-muted-foreground text-xs">{user.detail}</p>}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{user.barangay ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={user.is_active ? 'active' : 'inactive'}
                                                    label={user.is_active ? 'Active' : 'Deactivated'}
                                                />
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDate(user.registered_at)}</td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                                {user.last_login_at ? formatDate(user.last_login_at) : 'Never'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Pagination page={users} />
            </div>
        </AppLayout>
    );
}
