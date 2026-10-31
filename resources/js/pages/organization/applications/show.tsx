import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, LoaderCircle, Lock, Mail, MapPin, Phone, Sparkles } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

import { DocumentRow } from '@/components/applications/document-row';
import { StatusTimeline, type TimelineEntry } from '@/components/applications/status-timeline';
import InputError from '@/components/input-error';
import { matchLabel } from '@/components/opportunities/match-badge';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { type ApplicationDocumentItem, formatDateTime, formatFileSize } from '@/types/application';
import { type OpportunityMatch } from '@/types/opportunity';
import { formatDate } from '@/types/organization';

interface ReviewApplication {
    id: number;
    status: string;
    status_label: string;
    is_final: boolean;
    submitted_at: string;
    cover_letter: string | null;
    withdrawal_reason: string | null;
    match_score_at_submission: number | null;
    resume: { original_name: string; url: string; preview_url: string | null } | null;
    documents: ApplicationDocumentItem[];
    missing_documents: string[];
    history: TimelineEntry[];
    remarks: { id: number; body: string; author: string; at: string | null }[];
}

interface Applicant {
    name: string;
    email: string;
    mobile_number: string | null;
    barangay: string | null;
    classification: string | null;
    gender: string | null;
    age: number | null;
    address: string | null;
    highest_education: string | null;
    summary: string | null;
    education: { id: number; level: string | null; school: string; course: string | null; year_graduated: number | null; is_current: boolean }[];
    experience: {
        id: number;
        position: string;
        company: string | null;
        start_date: string | null;
        end_date: string | null;
        description: string | null;
    }[];
    certifications: { id: number; name: string; issuer: string | null; issued_at: string | null }[];
    trainings: { id: number; title: string; provider: string | null; completed_at: string | null; hours: number | null }[];
    skills: string[];
}

interface ReviewProps {
    application: ReviewApplication;
    opportunity: { id: number; slug: string; title: string; category_label: string; deleted: boolean };
    applicant: Applicant;
    match: OpportunityMatch;
    nextStatuses: Option[];
    canRemark: boolean;
}

const monthYear = (date: string | null) => (date ? formatDate(date, { month: 'short', year: 'numeric' }) : null);

export default function ReviewApplication({ application, opportunity, applicant, match, nextStatuses, canRemark }: ReviewProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Applicants', href: route('org.applications.index') },
        { title: applicant.name, href: route('org.applications.show', application.id) },
    ];
    const facts = [
        applicant.classification,
        applicant.age !== null ? `${applicant.age} years old` : null,
        applicant.gender,
        applicant.highest_education,
    ].filter(Boolean);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${applicant.name} · ${opportunity.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight">
                            {applicant.name}
                            <StatusBadge status={application.status} label={application.status_label} />
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Applied {formatDateTime(application.submitted_at)} for{' '}
                            {opportunity.deleted ? (
                                opportunity.title
                            ) : (
                                <Link href={route('org.opportunities.edit', opportunity.id)} className="text-foreground font-medium hover:underline">
                                    {opportunity.title}
                                </Link>
                            )}{' '}
                            · {opportunity.category_label}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={route('org.applications.index', { opportunity: opportunity.id })}>All applicants for this opportunity</Link>
                    </Button>
                </header>

                <div className="grid items-start gap-6 xl:grid-cols-[1fr_24rem]">
                    <div className="grid min-w-0 gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Applicant</CardTitle>
                                {facts.length > 0 && <CardDescription>{facts.join(' · ')}</CardDescription>}
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <a href={`mailto:${applicant.email}`} className="flex items-center gap-2 hover:underline">
                                        <Mail className="text-muted-foreground h-4 w-4" /> {applicant.email}
                                    </a>
                                    {applicant.mobile_number && (
                                        <span className="flex items-center gap-2">
                                            <Phone className="text-muted-foreground h-4 w-4" /> {applicant.mobile_number}
                                        </span>
                                    )}
                                    {(applicant.address || applicant.barangay) && (
                                        <span className="flex items-start gap-2 sm:col-span-2">
                                            <MapPin className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                            {[applicant.address, applicant.barangay && `Brgy. ${applicant.barangay}, Bauko`]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </span>
                                    )}
                                </div>
                                {applicant.summary && <p className="leading-relaxed whitespace-pre-line">{applicant.summary}</p>}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Submitted documents</CardTitle>
                                <CardDescription>
                                    Shared by the applicant for evaluating this application only. Keep downloaded copies secure.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {application.missing_documents.length > 0 && (
                                    <Alert>
                                        <CircleAlert className="h-4 w-4" />
                                        <AlertTitle>Required documents not on file</AlertTitle>
                                        <AlertDescription>
                                            {application.missing_documents.join(', ')}. The requirement may have been added after this person applied.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {!application.resume && application.documents.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No documents were submitted.</p>
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

                        <ProfileSection title="Education" empty={applicant.education.length === 0}>
                            {applicant.education.map((item) => (
                                <Entry
                                    key={item.id}
                                    title={item.course ?? item.level ?? item.school}
                                    subtitle={[item.school, item.course ? item.level : null].filter(Boolean).join(' · ')}
                                    aside={item.is_current ? 'Currently enrolled' : item.year_graduated ? `Graduated ${item.year_graduated}` : null}
                                />
                            ))}
                        </ProfileSection>

                        <ProfileSection title="Work experience" empty={applicant.experience.length === 0}>
                            {applicant.experience.map((item) => (
                                <Entry
                                    key={item.id}
                                    title={item.position}
                                    subtitle={item.company}
                                    aside={[monthYear(item.start_date), item.end_date ? monthYear(item.end_date) : 'Present']
                                        .filter(Boolean)
                                        .join(' – ')}
                                    body={item.description}
                                />
                            ))}
                        </ProfileSection>

                        <ProfileSection
                            title="Certifications and trainings"
                            empty={applicant.certifications.length === 0 && applicant.trainings.length === 0}
                        >
                            {applicant.certifications.map((item) => (
                                <Entry key={`c${item.id}`} title={item.name} subtitle={item.issuer} aside={monthYear(item.issued_at)} />
                            ))}
                            {applicant.trainings.map((item) => (
                                <Entry
                                    key={`t${item.id}`}
                                    title={item.title}
                                    subtitle={[item.provider, item.hours ? `${item.hours} hours` : null].filter(Boolean).join(' · ')}
                                    aside={monthYear(item.completed_at)}
                                />
                            ))}
                        </ProfileSection>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Skills</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {applicant.skills.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No skills listed.</p>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {applicant.skills.map((skill) => (
                                            <Badge key={skill} variant="secondary" className="font-normal">
                                                {skill}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="grid gap-6">
                        <StatusCard
                            applicationId={application.id}
                            statusLabel={application.status_label}
                            isFinal={application.is_final}
                            nextStatuses={nextStatuses}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between gap-2 text-base">
                                    <span className="flex items-center gap-2">
                                        <Sparkles className="h-4 w-4 text-teal-600" /> Match indicator
                                    </span>
                                    <span className="text-2xl font-semibold tabular-nums">{match.score}%</span>
                                </CardTitle>
                                <CardDescription>
                                    {matchLabel(match.score)}, based on the current profile
                                    {application.match_score_at_submission !== null &&
                                        ` (${application.match_score_at_submission}% when they applied)`}
                                    . A guide only, not an eligibility decision.
                                </CardDescription>
                            </CardHeader>
                            {match.reasons.length > 0 && (
                                <CardContent>
                                    <p className="text-muted-foreground mb-2 text-xs">As explained to the applicant:</p>
                                    <ul className="grid gap-1.5 text-sm">
                                        {match.reasons.map((reason) => (
                                            <li key={reason} className="flex gap-2">
                                                <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-teal-600" />
                                                {reason}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            )}
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Status history</CardTitle>
                                <CardDescription>Remarks in the history are visible to the applicant.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <StatusTimeline entries={application.history.map((entry) => ({ ...entry, by: `by ${entry.by}` }))} />
                                {application.withdrawal_reason && (
                                    <p className="text-muted-foreground mt-4 text-sm">Reason for withdrawing: {application.withdrawal_reason}</p>
                                )}
                            </CardContent>
                        </Card>

                        <RemarksCard applicationId={application.id} remarks={application.remarks} canRemark={canRemark} />
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function StatusCard({
    applicationId,
    statusLabel,
    isFinal,
    nextStatuses,
}: {
    applicationId: number;
    statusLabel: string;
    isFinal: boolean;
    nextStatuses: Option[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({ status: '', remarks: '' });
    const needsRemarks = data.status === 'for_further_requirements';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('org.applications.status', applicationId), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Update status</CardTitle>
                <CardDescription>Currently {statusLabel}. The applicant is notified of every change.</CardDescription>
            </CardHeader>
            <CardContent>
                {nextStatuses.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {isFinal ? 'This application is final and can no longer change.' : 'Your role cannot change the status of this application.'}
                    </p>
                ) : (
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="new-status">New status</Label>
                            <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                <SelectTrigger id="new-status">
                                    <SelectValue placeholder="Choose a status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {nextStatuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="status-remarks">
                                Message to the applicant{' '}
                                <span className="text-muted-foreground font-normal">{needsRemarks ? '(required)' : '(optional)'}</span>
                            </Label>
                            <Textarea
                                id="status-remarks"
                                rows={4}
                                maxLength={2000}
                                required={needsRemarks}
                                value={data.remarks}
                                onChange={(e) => setData('remarks', e.target.value)}
                                placeholder={
                                    needsRemarks
                                        ? 'List the documents or information you need.'
                                        : data.status === 'for_interview'
                                          ? 'Date, time and place of the interview.'
                                          : 'Shown to the applicant with the new status.'
                                }
                            />
                            <InputError message={errors.remarks} />
                        </div>
                        <Button type="submit" disabled={processing || data.status === ''}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Update status
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function RemarksCard({ applicationId, remarks, canRemark }: { applicationId: number; remarks: ReviewApplication['remarks']; canRemark: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('org.applications.remarks.store', applicationId), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Lock className="h-4 w-4" /> Internal remarks
                </CardTitle>
                <CardDescription>Only your organization's personnel can see these.</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {canRemark && (
                    <form onSubmit={submit} className="grid gap-2">
                        <Textarea
                            rows={3}
                            maxLength={2000}
                            required
                            aria-label="New remark"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="e.g. Strong communication skills; verify NC II certificate."
                        />
                        <InputError message={errors.body} />
                        <Button type="submit" size="sm" variant="outline" disabled={processing} className="justify-self-end">
                            Add remark
                        </Button>
                    </form>
                )}
                {remarks.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No remarks yet.</p>
                ) : (
                    <ul className="grid gap-3">
                        {remarks.map((remark) => (
                            <li key={remark.id} className="bg-muted/40 rounded-lg p-3 text-sm">
                                <p className="whitespace-pre-line">{remark.body}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {remark.author}
                                    {remark.at && ` · ${formatDateTime(remark.at)}`}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function ProfileSection({ title, empty, children }: { title: string; empty: boolean; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {empty ? <p className="text-muted-foreground text-sm">Nothing listed.</p> : <ul className="divide-y">{children}</ul>}
            </CardContent>
        </Card>
    );
}

function Entry({ title, subtitle, aside, body }: { title: string; subtitle?: string | null; aside?: string | null; body?: string | null }) {
    return (
        <li className="py-3 text-sm first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="font-medium">{title}</p>
                {aside && <p className="text-muted-foreground text-xs">{aside}</p>}
            </div>
            {subtitle && <p className="text-muted-foreground">{subtitle}</p>}
            {body && <p className="mt-1 whitespace-pre-line">{body}</p>}
        </li>
    );
}
