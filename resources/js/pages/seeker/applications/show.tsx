import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, LoaderCircle, Upload } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

import { DocumentRow } from '@/components/applications/document-row';
import { StatusTimeline } from '@/components/applications/status-timeline';
import InputError from '@/components/input-error';
import { categoryIcons, categoryTones } from '@/components/opportunities/opportunity-card';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type ApplicationDetails, formatFileSize } from '@/types/application';
import { type OpportunityCardData } from '@/types/opportunity';
import { formatDate } from '@/types/organization';

const statusTone: Record<string, string> = {
    approved: 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40',
    rejected: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
    for_further_requirements: 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40',
};

export default function ShowApplication({
    application,
    opportunity,
    opportunityAvailable,
}: {
    application: ApplicationDetails;
    opportunity: OpportunityCardData;
    opportunityAvailable: boolean;
}) {
    const Icon = categoryIcons[opportunity.category];
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My applications', href: route('seeker.applications.index') },
        { title: opportunity.title, href: route('seeker.applications.show', application.id) },
    ];
    const latestRemarks = [...application.history].reverse().find((entry) => entry.status === application.status && entry.remarks)?.remarks;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Application: ${opportunity.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        {opportunity.organization.logo_url ? (
                            <img src={opportunity.organization.logo_url} alt="" className="size-12 rounded-xl border object-cover" />
                        ) : (
                            <span className={cn('flex size-12 items-center justify-center rounded-xl', categoryTones[opportunity.category])}>
                                <Icon className="h-6 w-6" />
                            </span>
                        )}
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">{opportunity.title}</h1>
                            <p className="text-muted-foreground text-sm">
                                {opportunity.organization.name} · {opportunity.category_label} · Applied {formatDate(application.submitted_at)}
                            </p>
                        </div>
                    </div>
                    {opportunityAvailable && (
                        <Button asChild variant="outline">
                            <Link href={route('opportunities.show', opportunity.slug)}>View opportunity</Link>
                        </Button>
                    )}
                </header>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="grid min-w-0 gap-6">
                        <Card className={statusTone[application.status]}>
                            <CardHeader>
                                <CardDescription>Current status</CardDescription>
                                <CardTitle className="flex flex-wrap items-center gap-3 text-xl">
                                    {application.status_label}
                                    <StatusBadge status={application.status} label={application.status_label} />
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <p>{application.status_description}</p>
                                {latestRemarks && (
                                    <div className="bg-background/70 rounded-lg border p-3">
                                        <p className="text-muted-foreground mb-1 text-xs font-medium">Message from {opportunity.organization.name}</p>
                                        <p className="whitespace-pre-line">{latestRemarks}</p>
                                    </div>
                                )}
                                {application.status === 'withdrawn' && application.withdrawal_reason && (
                                    <p className="text-muted-foreground">Your reason: {application.withdrawal_reason}</p>
                                )}
                            </CardContent>
                        </Card>

                        {application.accepts_documents && <AddDocumentCard applicationId={application.id} />}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Submitted documents</CardTitle>
                                <CardDescription>Only you and {opportunity.organization.name} can open these files.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!application.resume && application.documents.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No documents were submitted with this application.</p>
                                ) : (
                                    <ul className="divide-y rounded-lg border">
                                        {application.resume && (
                                            <DocumentRow
                                                label="Resume"
                                                name={application.resume.original_name}
                                                url={application.resume.url}
                                                previewUrl={application.resume.preview_url}
                                            />
                                        )}
                                        {application.documents.map((document) => (
                                            <DocumentRow
                                                key={document.id}
                                                label={document.label}
                                                name={document.original_name}
                                                meta={`${formatFileSize(document.size_bytes)}${document.added_later && document.uploaded_at ? ` · added ${formatDate(document.uploaded_at)}` : ''}`}
                                                url={document.url}
                                                previewUrl={document.preview_url}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {application.cover_letter && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Cover letter</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-line">{application.cover_letter}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <aside className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Status history</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <StatusTimeline
                                    entries={application.history.map((entry) => ({
                                        ...entry,
                                        by: entry.by_applicant ? 'by you' : `by ${opportunity.organization.name}`,
                                    }))}
                                />
                            </CardContent>
                        </Card>

                        {application.can_withdraw && <WithdrawCard applicationId={application.id} title={opportunity.title} />}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function AddDocumentCard({ applicationId }: { applicationId: number }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{ label: string; file: File | null }>({ label: '', file: null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('seeker.applications.documents.store', applicationId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Upload className="h-4 w-4" /> Send the requested documents
                </CardTitle>
                <CardDescription>Upload each document the organization asked for. PDF, DOCX, JPG or PNG, up to 5 MB.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                    <div className="grid gap-2">
                        <Label htmlFor="document-label">Document name</Label>
                        <Input
                            id="document-label"
                            required
                            maxLength={150}
                            value={data.label}
                            onChange={(e) => setData('label', e.target.value)}
                            placeholder="e.g. Certificate of Grades"
                        />
                        <InputError message={errors.label} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="document-file">File</Label>
                        <Input
                            id="document-file"
                            ref={fileInput}
                            type="file"
                            required
                            accept=".pdf,.docx,.jpg,.jpeg,.png"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.file} />
                    </div>
                    <Button type="submit" disabled={processing} className="sm:mt-[1.375rem]">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Upload
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function WithdrawCard({ applicationId, title }: { applicationId: number; title: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('seeker.applications.withdraw', applicationId), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">No longer interested?</CardTitle>
                <CardDescription>Withdrawing lets the organization know you are no longer pursuing this opportunity.</CardDescription>
            </CardHeader>
            <CardContent>
                <Button variant="outline" className="text-destructive w-full" onClick={() => setOpen(true)}>
                    Withdraw application
                </Button>
            </CardContent>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>Withdraw your application?</DialogTitle>
                            <DialogDescription>{title}</DialogDescription>
                        </DialogHeader>
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>This cannot be undone</AlertTitle>
                            <AlertDescription>After withdrawing, you cannot apply to this opportunity again.</AlertDescription>
                        </Alert>
                        <div className="grid gap-2">
                            <Label htmlFor="withdraw-reason">Reason (optional)</Label>
                            <Textarea
                                id="withdraw-reason"
                                rows={3}
                                maxLength={500}
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                placeholder="e.g. I accepted another offer."
                            />
                            <p className="text-muted-foreground text-xs">Shared with the organization.</p>
                            <InputError message={errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
                                Keep application
                            </Button>
                            <Button type="submit" variant="destructive" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Withdraw
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
