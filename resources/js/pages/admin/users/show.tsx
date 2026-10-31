import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, CircleAlert, RotateCcw, UserRoundX } from 'lucide-react';
import { ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { ReasonDialog } from '@/components/reason-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatDateTime } from '@/types/application';
import { formatDate } from '@/types/organization';

interface AdminUserShowProps {
    account: {
        id: number;
        name: string;
        email: string;
        email_verified: boolean;
        mobile_number: string | null;
        mobile_verified: boolean;
        account_type: string;
        account_label: string;
        barangay: string | null;
        is_active: boolean;
        deactivated_at: string | null;
        deactivation_reason: string | null;
        registered_at: string;
        last_login_at: string | null;
    };
    seeker: {
        classification: string | null;
        profile_percent: number;
        has_resume: boolean;
        applications_total: number;
        applications_by_status: { status: string; label: string; count: number }[];
        recent_applications: {
            id: number;
            opportunity: string;
            organization: string | null;
            status: string;
            status_label: string;
            submitted_at: string;
        }[];
    } | null;
    membership: { organization_id: number; organization: string | null; role: string; is_active: boolean } | null;
    consents: { id: number; type: string; version: string; granted_at: string; withdrawn_at: string | null }[];
    history: { id: number; action: string; by: string | null; reason: string | null; at: string }[];
    isSelf: boolean;
}

const actionLabels: Record<string, string> = {
    'user.deactivated': 'Deactivated',
    'user.reactivated': 'Reactivated',
};

export default function AdminUserShow({ account, seeker, membership, consents, history, isSelf }: AdminUserShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Users', href: '/admin/users' },
        { title: account.name, href: `/admin/users/${account.id}` },
    ];
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [deactivating, setDeactivating] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">{account.name}</h1>
                            <StatusBadge status={account.is_active ? 'active' : 'inactive'} label={account.is_active ? 'Active' : 'Deactivated'} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {account.account_label} · registered {formatDate(account.registered_at)}
                        </p>
                    </div>

                    {!isSelf &&
                        (account.is_active ? (
                            <Button variant="destructive" onClick={() => setDeactivating(true)}>
                                <UserRoundX className="h-4 w-4" /> Deactivate account
                            </Button>
                        ) : (
                            <Button onClick={() => router.post(route('admin.users.reactivate', account.id), {}, { preserveScroll: true })}>
                                <RotateCcw className="h-4 w-4" /> Reactivate account
                            </Button>
                        ))}
                </div>

                <InputError message={errors.account} />

                {!account.is_active && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
                        <p className="font-medium">Deactivated {account.deactivated_at && formatDateTime(account.deactivated_at)}</p>
                        {account.deactivation_reason && <p>Reason: {account.deactivation_reason}</p>}
                        <p className="mt-1 opacity-80">This person cannot sign in. Their records are kept and nothing has been deleted.</p>
                    </div>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Account</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    <Detail label="Email">
                                        <Verified ok={account.email_verified}>{account.email}</Verified>
                                    </Detail>
                                    <Detail label="Mobile number">
                                        {account.mobile_number ? <Verified ok={account.mobile_verified}>{account.mobile_number}</Verified> : '—'}
                                    </Detail>
                                    <Detail label="Barangay">{account.barangay ? `${account.barangay}, Bauko` : '—'}</Detail>
                                    <Detail label="Last sign-in">{account.last_login_at ? formatDateTime(account.last_login_at) : 'Never'}</Detail>
                                </dl>
                            </CardContent>
                        </Card>

                        {seeker && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Seeker summary</CardTitle>
                                    <CardDescription>
                                        The full profile and documents are shared only with the organizations this person applies to.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 text-sm">
                                    <dl className="grid gap-4 sm:grid-cols-3">
                                        <Detail label="Classification">{seeker.classification ?? '—'}</Detail>
                                        <Detail label="Profile completeness">{seeker.profile_percent}%</Detail>
                                        <Detail label="Resume">{seeker.has_resume ? 'Uploaded' : 'None'}</Detail>
                                    </dl>
                                    <div className="grid gap-2">
                                        <p className="font-medium">
                                            Applications{' '}
                                            <span className="text-muted-foreground font-normal tabular-nums">({seeker.applications_total})</span>
                                        </p>
                                        {seeker.applications_total === 0 ? (
                                            <p className="text-muted-foreground">No applications yet.</p>
                                        ) : (
                                            <>
                                                <div className="flex flex-wrap gap-2">
                                                    {seeker.applications_by_status.map((row) => (
                                                        <StatusBadge key={row.status} status={row.status} label={`${row.label}: ${row.count}`} />
                                                    ))}
                                                </div>
                                                <ul className="divide-y rounded-lg border">
                                                    {seeker.recent_applications.map((a) => (
                                                        <li key={a.id} className="flex items-center justify-between gap-3 px-3 py-2">
                                                            <div className="min-w-0">
                                                                <p className="truncate font-medium">{a.opportunity}</p>
                                                                <p className="text-muted-foreground truncate text-xs">
                                                                    {a.organization} · {formatDate(a.submitted_at)}
                                                                </p>
                                                            </div>
                                                            <StatusBadge status={a.status} label={a.status_label} />
                                                        </li>
                                                    ))}
                                                </ul>
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {membership && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Organization</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="grid gap-4 text-sm sm:grid-cols-3">
                                        <Detail label="Organization">
                                            <Link href={route('admin.organizations.show', membership.organization_id)} className="hover:underline">
                                                {membership.organization}
                                            </Link>
                                        </Detail>
                                        <Detail label="Role">{membership.role}</Detail>
                                        <Detail label="Membership">{membership.is_active ? 'Active' : 'Deactivated by the organization'}</Detail>
                                    </dl>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Consents</CardTitle>
                                <CardDescription>Recorded under the Data Privacy Act.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {consents.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No consents recorded.</p>
                                ) : (
                                    <ul className="grid gap-3 text-sm">
                                        {consents.map((c) => (
                                            <li key={c.id}>
                                                <p className="font-medium">
                                                    {c.type} <span className="text-muted-foreground font-normal">v{c.version}</span>
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    Given {formatDate(c.granted_at)}
                                                    {c.withdrawn_at && ` · withdrawn ${formatDate(c.withdrawn_at)}`}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Account history</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {history.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No administrator actions on this account.</p>
                                ) : (
                                    <ol className="grid gap-3 text-sm">
                                        {history.map((entry) => (
                                            <li key={entry.id}>
                                                <p className="font-medium">{actionLabels[entry.action] ?? entry.action}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {formatDateTime(entry.at)}
                                                    {entry.by && ` · ${entry.by}`}
                                                </p>
                                                {entry.reason && <p className="mt-0.5">{entry.reason}</p>}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ReasonDialog
                open={deactivating}
                onOpenChange={setDeactivating}
                action={route('admin.users.deactivate', account.id)}
                title={`Deactivate ${account.name}?`}
                description="They will be signed out everywhere and won't be able to sign in. They will receive an email with the reason. You can reactivate the account later."
                submitLabel="Deactivate"
                maxLength={255}
            />
        </AppLayout>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1">
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function Verified({ ok, children }: { ok: boolean; children: ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            {children}
            {ok ? (
                <BadgeCheck className="h-4 w-4 text-teal-600" aria-label="Verified" />
            ) : (
                <CircleAlert className="h-4 w-4 text-amber-600" aria-label="Not verified" />
            )}
        </span>
    );
}
