import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CircleCheck, Download, Eye, FileText, RotateCcw, XCircle } from 'lucide-react';
import { FormEventHandler, ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatDate } from '@/types/organization';

interface AdminOrganizationShowProps {
    organization: {
        id: number;
        name: string;
        type: string;
        official_email: string;
        contact_number: string;
        office_address: string;
        barangay: string | null;
        description: string | null;
        logo_url: string | null;
        representative_name: string;
        representative_position: string | null;
        verification_status: string;
        verification_label: string;
        status_reason: string | null;
        verified_at: string | null;
        verified_by: string | null;
        registered_at: string;
    };
    documents: { id: number; original_name: string; mime_type: string; size_bytes: number; uploaded_at: string }[];
    members: { id: number; name: string | null; email: string | null; role: string; is_active: boolean }[];
    opportunityCounts: Record<string, number>;
    history: { id: number; action: string; by: string | null; reason: string | null; at: string }[];
}

const actionLabels: Record<string, string> = {
    'organization.registered': 'Registered',
    'organization.updated': 'Details updated',
    'organization.resubmitted': 'Resubmitted for verification',
    'organization.verified': 'Verified',
    'organization.rejected': 'Rejected',
    'organization.suspended': 'Suspended',
    'organization.reinstated': 'Reinstated',
};

type ReasonAction = 'reject' | 'suspend';

export default function AdminOrganizationShow({ organization, documents, members, opportunityCounts, history }: AdminOrganizationShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Organizations', href: '/admin/organizations' },
        { title: organization.name, href: `/admin/organizations/${organization.id}` },
    ];
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [reasonAction, setReasonAction] = useState<ReasonAction | null>(null);
    const reasonForm = useForm({ reason: '' });
    const status = organization.verification_status;

    const decide = (action: 'approve' | 'reinstate') =>
        router.post(route(`admin.organizations.${action}`, organization.id), {}, { preserveScroll: true });

    const submitReason: FormEventHandler = (e) => {
        e.preventDefault();
        if (!reasonAction) return;
        reasonForm.post(route(`admin.organizations.${reasonAction}`, organization.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReasonAction(null);
                reasonForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        {organization.logo_url && <img src={organization.logo_url} alt="" className="size-14 rounded-lg border object-cover" />}
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight">{organization.name}</h1>
                                <StatusBadge status={status} label={organization.verification_label} />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {organization.type} · registered {formatDate(organization.registered_at)}
                                {organization.verified_at &&
                                    ` · verified ${formatDate(organization.verified_at)} by ${organization.verified_by ?? 'an administrator'}`}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {(status === 'pending' || status === 'rejected') && (
                            <Button onClick={() => decide('approve')}>
                                <CircleCheck className="h-4 w-4" /> Approve
                            </Button>
                        )}
                        {status === 'pending' && (
                            <Button variant="outline" onClick={() => setReasonAction('reject')}>
                                <XCircle className="h-4 w-4" /> Reject
                            </Button>
                        )}
                        {(status === 'verified' || status === 'pending') && (
                            <Button variant="destructive" onClick={() => setReasonAction('suspend')}>
                                <Ban className="h-4 w-4" /> Suspend
                            </Button>
                        )}
                        {status === 'suspended' && (
                            <Button onClick={() => decide('reinstate')}>
                                <RotateCcw className="h-4 w-4" /> Reinstate
                            </Button>
                        )}
                    </div>
                </div>

                <InputError message={errors.status} />

                {organization.status_reason && (
                    <div className="rounded-lg border px-4 py-3 text-sm">
                        <span className="font-medium">Reason given: </span>
                        {organization.status_reason}
                    </div>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Organization details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    <Detail label="Official email">{organization.official_email}</Detail>
                                    <Detail label="Contact number">{organization.contact_number}</Detail>
                                    <Detail label="Office address">
                                        {organization.office_address}
                                        {organization.barangay && ` (Brgy. ${organization.barangay}, Bauko)`}
                                    </Detail>
                                    <Detail label="Authorized representative">
                                        {organization.representative_name}
                                        {organization.representative_position && `, ${organization.representative_position}`}
                                    </Detail>
                                    <Detail label="About" className="sm:col-span-2">
                                        <span className="whitespace-pre-line">{organization.description || '—'}</span>
                                    </Detail>
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Verification documents</CardTitle>
                                <CardDescription>Open each document and confirm it matches the organization before approving.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {documents.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No documents uploaded.</p>
                                ) : (
                                    <ul className="divide-y rounded-lg border">
                                        {documents.map((d) => (
                                            <li key={d.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <FileText className="text-muted-foreground h-4 w-4 shrink-0" />
                                                    <span className="truncate">{d.original_name}</span>
                                                    <span className="text-muted-foreground shrink-0">· {formatDate(d.uploaded_at)}</span>
                                                </span>
                                                <span className="flex shrink-0 gap-1">
                                                    <Button variant="ghost" size="icon" asChild>
                                                        <a
                                                            href={route('organization-documents.show', { document: d.id, preview: 1 })}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={`Open ${d.original_name}`}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                    <Button variant="ghost" size="icon" asChild>
                                                        <a
                                                            href={route('organization-documents.show', d.id)}
                                                            aria-label={`Download ${d.original_name}`}
                                                        >
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Personnel</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="divide-y text-sm">
                                    {members.map((m) => (
                                        <li key={m.id} className="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                            <span>
                                                <span className="font-medium">{m.name}</span>
                                                <span className="text-muted-foreground"> · {m.email}</span>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <Badge variant="outline">{m.role}</Badge>
                                                {!m.is_active && <Badge variant="secondary">Inactive</Badge>}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Opportunities</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {Object.keys(opportunityCounts).length === 0 ? (
                                    <p className="text-muted-foreground text-sm">None posted yet.</p>
                                ) : (
                                    <dl className="grid grid-cols-2 gap-2 text-sm">
                                        {Object.entries(opportunityCounts).map(([key, count]) => (
                                            <div key={key} className="flex justify-between gap-2 rounded-md border px-3 py-2">
                                                <dt className="text-muted-foreground capitalize">{key}</dt>
                                                <dd className="font-medium tabular-nums">{count}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="grid gap-3 text-sm">
                                    {history.map((h) => (
                                        <li key={h.id} className="border-l-2 pl-3">
                                            <p className="font-medium">{actionLabels[h.action] ?? h.action}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {formatDate(h.at, { dateStyle: 'medium', timeStyle: 'short' })}
                                                {h.by && ` · ${h.by}`}
                                            </p>
                                            {h.reason && <p className="text-muted-foreground mt-1">{h.reason}</p>}
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Dialog open={reasonAction !== null} onOpenChange={(open) => !open && setReasonAction(null)}>
                <DialogContent>
                    <form onSubmit={submitReason} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>{reasonAction === 'reject' ? 'Reject verification' : 'Suspend organization'}</DialogTitle>
                            <DialogDescription>
                                {reasonAction === 'reject'
                                    ? 'The organization will see this reason and can correct its details and resubmit.'
                                    : 'Its opportunities will be hidden from the public. The organization will see this reason.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Reason</Label>
                            <Textarea
                                id="reason"
                                rows={4}
                                value={reasonForm.data.reason}
                                onChange={(e) => reasonForm.setData('reason', e.target.value)}
                                required
                            />
                            <InputError message={reasonForm.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="secondary" onClick={() => setReasonAction(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive" disabled={reasonForm.processing}>
                                {reasonAction === 'reject' ? 'Reject' : 'Suspend'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function Detail({ label, className, children }: { label: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}
