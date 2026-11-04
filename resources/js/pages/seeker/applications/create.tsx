import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronRight, CircleAlert, CircleDashed, ClipboardList, FileText, LoaderCircle, Send, ShieldCheck, UserRound } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { categoryIcons, categoryTones, deadlineText } from '@/components/opportunities/opportunity-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type OpportunityCardData } from '@/types/opportunity';
import { formatDate } from '@/types/organization';

interface RequiredDocument {
    id: number;
    name: string;
    is_required: boolean;
    is_resume: boolean;
    accept: string;
    types_label: string;
}

interface ApplyProps {
    opportunity: OpportunityCardData;
    instructions: string | null;
    documents: RequiredDocument[];
    resume: { id: number; original_name: string; uploaded_at: string | null; url: string } | null;
    resumeRequired: boolean;
    completion: number;
    /** Required profile sections still to complete; applying is not possible until this is empty. */
    missing: { key: string; label: string; hint: string }[];
}

type ApplyForm = {
    cover_letter: string;
    include_resume: boolean;
    consent: boolean;
    documents: Record<number, File | null>;
};

const COVER_LETTER_MAX = 5000;

export default function Apply({ opportunity, instructions, documents, resume, resumeRequired, completion, missing }: ApplyProps) {
    const Icon = categoryIcons[opportunity.category];
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Browse opportunities', href: route('opportunities.index') },
        { title: opportunity.title, href: route('opportunities.show', opportunity.slug) },
        { title: 'Apply', href: route('seeker.applications.create', opportunity.slug) },
    ];

    const { data, setData, post, processing, errors, progress } = useForm<ApplyForm>({
        cover_letter: '',
        include_resume: resume !== null,
        consent: false,
        documents: {},
    });
    const fieldErrors = errors as Record<string, string | undefined>;
    const blocked = resumeRequired && resume === null;
    // The profile resume is sent for any document that asks for a resume, so it is not uploaded twice.
    const resumeSent = resume !== null && (resumeRequired || data.include_resume);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('seeker.applications.store', opportunity.slug), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Apply: ${opportunity.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Apply to {opportunity.title}</h1>
                    <p className="text-muted-foreground">
                        {opportunity.organization.name} · {deadlineText(opportunity)}
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
                    {missing.length > 0 ? (
                        <FinishProfileFirst missing={missing} />
                    ) : (
                        <form onSubmit={submit} className="grid min-w-0 gap-6">
                            {fieldErrors.application && (
                                <Alert variant="destructive">
                                    <CircleAlert className="h-4 w-4" />
                                    <AlertTitle>Your application was not submitted</AlertTitle>
                                    <AlertDescription>{fieldErrors.application}</AlertDescription>
                                </Alert>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <UserRound className="h-4 w-4" /> Your profile and resume
                                    </CardTitle>
                                    <CardDescription>
                                        {opportunity.organization.name} will see your applicant profile, which is {completion}% complete.{' '}
                                        <Link href={route('seeker.profile.edit')} className="text-primary font-medium hover:underline">
                                            Review my profile
                                        </Link>
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {resume ? (
                                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <FileText className="text-muted-foreground h-5 w-5 shrink-0" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{resume.original_name}</p>
                                                    <p className="text-muted-foreground text-xs">Uploaded {formatDate(resume.uploaded_at)}</p>
                                                </div>
                                            </div>
                                            <a
                                                href={resume.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary text-sm font-medium hover:underline"
                                            >
                                                View
                                            </a>
                                        </div>
                                    ) : (
                                        <Alert variant={resumeRequired ? 'destructive' : 'default'}>
                                            <CircleAlert className="h-4 w-4" />
                                            <AlertTitle>
                                                {resumeRequired ? 'A resume is required for job applications' : 'No resume on your profile'}
                                            </AlertTitle>
                                            <AlertDescription>
                                                <p>
                                                    {resumeRequired
                                                        ? 'Upload your resume or create one with the resume builder on your profile, then come back to apply.'
                                                        : 'A resume is optional for this opportunity, but it can help your application.'}{' '}
                                                    <Link href={route('seeker.profile.edit')} className="font-medium underline">
                                                        Upload a resume
                                                    </Link>
                                                </p>
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {resume && !resumeRequired && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.include_resume}
                                                onCheckedChange={(checked) => setData('include_resume', checked === true)}
                                            />
                                            Include my resume with this application
                                        </label>
                                    )}
                                    {resume && resumeRequired && (
                                        <p className="text-muted-foreground text-xs">Your resume is included with every job application.</p>
                                    )}
                                    <InputError message={fieldErrors.resume} />
                                </CardContent>
                            </Card>

                            {documents.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <FileText className="h-4 w-4" /> Documents
                                        </CardTitle>
                                        <CardDescription>
                                            Attach the documents {opportunity.organization.name} asks for. Each file can be up to 5 MB.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-5">
                                        {documents.map((document) => (
                                            <div key={document.id} className="grid gap-2">
                                                <Label htmlFor={`document-${document.id}`}>
                                                    {document.name}{' '}
                                                    <span className="text-muted-foreground font-normal">
                                                        {document.is_required ? '(required)' : '(optional)'}
                                                    </span>
                                                </Label>
                                                {document.is_resume && resumeSent ? (
                                                    <p
                                                        id={`document-${document.id}`}
                                                        className="bg-muted/50 flex items-center gap-2 rounded-md p-3 text-sm"
                                                    >
                                                        <FileText className="text-muted-foreground h-4 w-4 shrink-0" />
                                                        <span className="min-w-0">
                                                            Your resume from your profile,{' '}
                                                            <span className="font-medium break-all">{resume.original_name}</span>, is sent for this.
                                                            No need to upload it again.
                                                        </span>
                                                    </p>
                                                ) : (
                                                    <>
                                                        <Input
                                                            id={`document-${document.id}`}
                                                            type="file"
                                                            accept={document.accept}
                                                            required={document.is_required}
                                                            onChange={(e) =>
                                                                setData('documents', {
                                                                    ...data.documents,
                                                                    [document.id]: e.target.files?.[0] ?? null,
                                                                })
                                                            }
                                                        />
                                                        <p className="text-muted-foreground text-xs">Accepted: {document.types_label}</p>
                                                    </>
                                                )}
                                                <InputError message={fieldErrors[`documents.${document.id}`]} />
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Cover letter</CardTitle>
                                    <CardDescription>Optional. Tell the organization why you are interested and what you can bring.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    <Textarea
                                        id="cover_letter"
                                        rows={8}
                                        maxLength={COVER_LETTER_MAX}
                                        value={data.cover_letter}
                                        onChange={(e) => setData('cover_letter', e.target.value)}
                                        placeholder="Dear Hiring Team, ..."
                                    />
                                    <p className="text-muted-foreground text-right text-xs tabular-nums">
                                        {data.cover_letter.length} / {COVER_LETTER_MAX}
                                    </p>
                                    <InputError message={errors.cover_letter} />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="grid gap-4">
                                    <label className="flex items-start gap-3 text-sm">
                                        <Checkbox
                                            className="mt-0.5"
                                            checked={data.consent}
                                            onCheckedChange={(checked) => setData('consent', checked === true)}
                                        />
                                        <span>
                                            I agree to share my applicant profile{resume && data.include_resume ? ', resume' : ''} and the documents
                                            above with <strong>{opportunity.organization.name}</strong> so they can evaluate this application, as
                                            described in the{' '}
                                            <Link href={route('privacy')} className="text-primary font-medium hover:underline">
                                                Privacy Notice
                                            </Link>
                                            .
                                        </span>
                                    </label>
                                    <InputError message={errors.consent} />
                                    <p className="text-muted-foreground flex items-start gap-2 text-xs">
                                        <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" />
                                        You can withdraw while your application is being processed. After withdrawing, you cannot apply to this
                                        opportunity again.
                                    </p>
                                    {progress && (
                                        <progress value={progress.percentage} max="100" className="w-full">
                                            {progress.percentage}%
                                        </progress>
                                    )}
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button asChild type="button" variant="outline">
                                            <Link href={route('opportunities.show', opportunity.slug)}>Cancel</Link>
                                        </Button>
                                        <Button type="submit" disabled={processing || blocked || !data.consent}>
                                            {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                            Submit application
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>
                    )}

                    <aside className="grid gap-4 lg:sticky lg:top-6">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    {opportunity.organization.logo_url ? (
                                        <img src={opportunity.organization.logo_url} alt="" className="size-10 rounded-lg border object-cover" />
                                    ) : (
                                        <span
                                            className={cn('flex size-10 items-center justify-center rounded-lg', categoryTones[opportunity.category])}
                                        >
                                            <Icon className="h-5 w-5" />
                                        </span>
                                    )}
                                    <div className="min-w-0">
                                        <CardTitle className="text-base">{opportunity.title}</CardTitle>
                                        <CardDescription>{opportunity.organization.name}</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="text-muted-foreground grid gap-1 text-sm">
                                <p>{opportunity.category_label}</p>
                                <p>{opportunity.location}</p>
                                <p>{deadlineText(opportunity)}</p>
                                <Link href={route('opportunities.show', opportunity.slug)} className="text-primary mt-2 font-medium hover:underline">
                                    View full details
                                </Link>
                            </CardContent>
                        </Card>
                        {instructions && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">How to apply</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-line">{instructions}</p>
                                </CardContent>
                            </Card>
                        )}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

/** Shown instead of the form until the required parts of the profile are done. */
function FinishProfileFirst({ missing }: { missing: ApplyProps['missing'] }) {
    return (
        <Card className="min-w-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <ClipboardList className="h-4 w-4" /> Finish your profile to apply
                </CardTitle>
                <CardDescription>
                    Organizations review your applicant profile and resume, so these parts must be filled in before you can apply. It only needs to be
                    done once; after that you can apply to any opportunity.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <ul className="divide-y rounded-lg border">
                    {missing.map((section) => (
                        <li key={section.key}>
                            <Link
                                href={`${route('seeker.profile.edit')}#${section.key}`}
                                className="hover:bg-muted/50 flex items-start gap-3 px-4 py-3"
                            >
                                <CircleDashed className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">{section.label}</span>
                                    {section.hint && <span className="text-muted-foreground block text-xs">{section.hint}</span>}
                                </span>
                                <ChevronRight className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                            </Link>
                        </li>
                    ))}
                </ul>
                <div className="flex flex-wrap justify-end gap-2">
                    <Button asChild>
                        <Link href={route('seeker.profile.edit')}>Complete my profile</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
