import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';

type Row = Record<string, string | boolean> & { include: boolean };

interface Extracted {
    skills: string[];
    education: { level: string | null; school: string | null; course: string | null; year_graduated: number | null; is_current: boolean }[];
    experience: { position: string | null; company: string | null; start_date: string | null; end_date: string | null; description: string | null }[];
    certifications: { name: string; issuer: string | null }[];
    trainings: { title: string; provider: string | null; hours: number | null }[];
    keywords: string[];
}

interface ResumeReviewProps {
    resume: { id: number; original_name: string; reviewed: boolean };
    extracted: Extracted;
    educationLevels: Option[];
}

type ReviewForm = {
    skills: { name: string; include: boolean }[];
    education: Row[];
    experience: Row[];
    certifications: Row[];
    trainings: Row[];
};

const str = (value: string | number | null | undefined) => (value === null || value === undefined ? '' : String(value));

export default function ResumeReview({ resume, extracted, educationLevels }: ResumeReviewProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My profile', href: '/profile' },
        { title: 'Review resume details', href: `/resume/${resume.id}/review` },
    ];

    const form = useForm<ReviewForm>({
        skills: extracted.skills.map((name) => ({ name, include: true })),
        education: extracted.education.map((e) => ({
            include: true,
            level: str(e.level),
            school: str(e.school),
            course: str(e.course),
            year_graduated: str(e.year_graduated),
            is_current: e.is_current,
        })),
        experience: extracted.experience.map((e) => ({
            include: true,
            position: str(e.position),
            company: str(e.company),
            start_date: str(e.start_date),
            end_date: str(e.end_date),
            description: str(e.description),
        })),
        certifications: extracted.certifications.map((c) => ({ include: true, name: c.name, issuer: str(c.issuer) })),
        trainings: extracted.trainings.map((t) => ({ include: true, title: t.title, provider: str(t.provider), hours: str(t.hours) })),
    });

    const errors = form.errors as Record<string, string>;

    // Error keys refer to positions in the submitted (included-only) list.
    const submittedIndex = (rows: { include: boolean }[], index: number) => rows.slice(0, index).filter((r) => r.include).length;

    const update = (section: Exclude<keyof ReviewForm, 'skills'>, index: number, key: string, value: string | boolean) =>
        form.setData(
            section,
            form.data[section].map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => {
            const included = (rows: Row[]) =>
                rows.filter((r) => r.include).map((row) => Object.fromEntries(Object.entries(row).filter(([key]) => key !== 'include')));

            return {
                skills: data.skills.filter((s) => s.include).map((s) => s.name),
                education: included(data.education),
                experience: included(data.experience),
                certifications: included(data.certifications),
                trainings: included(data.trainings),
            };
        });
        form.post(route('seeker.resume.review.store', resume.id));
    };

    const selectedCount =
        form.data.skills.filter((s) => s.include).length +
        (['education', 'experience', 'certifications', 'trainings'] as const).reduce(
            (n, key) => n + form.data[key].filter((r) => r.include).length,
            0,
        );
    const nothingFound =
        extracted.skills.length +
            extracted.education.length +
            extracted.experience.length +
            extracted.certifications.length +
            extracted.trainings.length ===
        0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review resume details" />
            <form onSubmit={submit} className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Review details from your resume</h1>
                    <p className="text-muted-foreground">
                        We read <span className="font-medium">{resume.original_name}</span> and found the details below. Automatic reading can make
                        mistakes: untick anything wrong and correct the rest. Only what you confirm is added to your profile.
                    </p>
                </div>

                {nothingFound && (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            We couldn't find profile details in this resume. You can add them yourself on your{' '}
                            <Link href={route('seeker.profile.edit')} className="underline underline-offset-4">
                                profile
                            </Link>
                            .
                        </CardContent>
                    </Card>
                )}

                {extracted.skills.length > 0 && (
                    <ReviewCard title="Skills" description="Skills mentioned in your resume that aren't on your profile yet.">
                        <div className="flex flex-wrap gap-2">
                            {form.data.skills.map((skill, index) => (
                                <label
                                    key={skill.name}
                                    className="has-[:checked]:border-primary has-[:checked]:bg-primary/5 flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                                >
                                    <Checkbox
                                        checked={skill.include}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'skills',
                                                form.data.skills.map((s, i) => (i === index ? { ...s, include: checked === true } : s)),
                                            )
                                        }
                                    />
                                    {skill.name}
                                </label>
                            ))}
                        </div>
                    </ReviewCard>
                )}

                {form.data.education.length > 0 && (
                    <ReviewCard title="Education" description="Check the level, school and year for each entry.">
                        {form.data.education.map((row, index) => {
                            const at = submittedIndex(form.data.education, index);
                            return (
                                <ReviewRow key={index} included={row.include} onToggle={(v) => update('education', index, 'include', v)}>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Level" error={row.include ? errors[`education.${at}.level`] : undefined}>
                                            <Select value={String(row.level)} onValueChange={(v) => update('education', index, 'level', v)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select level" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {educationLevels.map((o) => (
                                                        <SelectItem key={o.value} value={o.value}>
                                                            {o.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field label="School" error={row.include ? errors[`education.${at}.school`] : undefined}>
                                            <Input
                                                value={String(row.school)}
                                                onChange={(e) => update('education', index, 'school', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Course or program">
                                            <Input
                                                value={String(row.course)}
                                                onChange={(e) => update('education', index, 'course', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Year graduated" error={row.include ? errors[`education.${at}.year_graduated`] : undefined}>
                                            <Input
                                                type="number"
                                                value={String(row.year_graduated)}
                                                onChange={(e) => update('education', index, 'year_graduated', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </ReviewRow>
                            );
                        })}
                    </ReviewCard>
                )}

                {form.data.experience.length > 0 && (
                    <ReviewCard title="Work experience" description="Dates are approximate when your resume shows only the year.">
                        {form.data.experience.map((row, index) => {
                            const at = submittedIndex(form.data.experience, index);
                            return (
                                <ReviewRow key={index} included={row.include} onToggle={(v) => update('experience', index, 'include', v)}>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Position" error={row.include ? errors[`experience.${at}.position`] : undefined}>
                                            <Input
                                                value={String(row.position)}
                                                onChange={(e) => update('experience', index, 'position', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Company or employer" error={row.include ? errors[`experience.${at}.company`] : undefined}>
                                            <Input
                                                value={String(row.company)}
                                                onChange={(e) => update('experience', index, 'company', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Start date" error={row.include ? errors[`experience.${at}.start_date`] : undefined}>
                                            <Input
                                                type="date"
                                                value={String(row.start_date)}
                                                onChange={(e) => update('experience', index, 'start_date', e.target.value)}
                                            />
                                        </Field>
                                        <Field
                                            label="End date (empty if current)"
                                            error={row.include ? errors[`experience.${at}.end_date`] : undefined}
                                        >
                                            <Input
                                                type="date"
                                                value={String(row.end_date)}
                                                onChange={(e) => update('experience', index, 'end_date', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                    {row.description && (
                                        <p className="text-muted-foreground mt-2 text-sm whitespace-pre-line">{String(row.description)}</p>
                                    )}
                                </ReviewRow>
                            );
                        })}
                    </ReviewCard>
                )}

                {form.data.certifications.length > 0 && (
                    <ReviewCard title="Certifications and licenses">
                        {form.data.certifications.map((row, index) => {
                            const at = submittedIndex(form.data.certifications, index);
                            return (
                                <ReviewRow key={index} included={row.include} onToggle={(v) => update('certifications', index, 'include', v)}>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Name" error={row.include ? errors[`certifications.${at}.name`] : undefined}>
                                            <Input
                                                value={String(row.name)}
                                                onChange={(e) => update('certifications', index, 'name', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Issued by">
                                            <Input
                                                value={String(row.issuer)}
                                                onChange={(e) => update('certifications', index, 'issuer', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </ReviewRow>
                            );
                        })}
                    </ReviewCard>
                )}

                {form.data.trainings.length > 0 && (
                    <ReviewCard title="Trainings attended">
                        {form.data.trainings.map((row, index) => {
                            const at = submittedIndex(form.data.trainings, index);
                            return (
                                <ReviewRow key={index} included={row.include} onToggle={(v) => update('trainings', index, 'include', v)}>
                                    <div className="grid gap-3 sm:grid-cols-[1fr_1fr_8rem]">
                                        <Field label="Title" error={row.include ? errors[`trainings.${at}.title`] : undefined}>
                                            <Input value={String(row.title)} onChange={(e) => update('trainings', index, 'title', e.target.value)} />
                                        </Field>
                                        <Field label="Conducted by">
                                            <Input
                                                value={String(row.provider)}
                                                onChange={(e) => update('trainings', index, 'provider', e.target.value)}
                                            />
                                        </Field>
                                        <Field label="Hours" error={row.include ? errors[`trainings.${at}.hours`] : undefined}>
                                            <Input
                                                type="number"
                                                value={String(row.hours)}
                                                onChange={(e) => update('trainings', index, 'hours', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </ReviewRow>
                            );
                        })}
                    </ReviewCard>
                )}

                {extracted.keywords.length > 0 && (
                    <ReviewCard title="Keywords" description="Frequent terms in your resume, for your reference.">
                        <div className="flex flex-wrap gap-1.5">
                            {extracted.keywords.map((k) => (
                                <Badge key={k} variant="outline" className="font-normal">
                                    {k}
                                </Badge>
                            ))}
                        </div>
                    </ReviewCard>
                )}

                <div className="bg-background/95 sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t px-4 py-3 md:-mx-6 md:px-6">
                    <Button type="submit" disabled={form.processing || selectedCount === 0}>
                        {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Add {selectedCount} {selectedCount === 1 ? 'item' : 'items'} to my profile
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={route('seeker.profile.edit')}>Back to profile</Link>
                    </Button>
                    {resume.reviewed && (
                        <span className="text-muted-foreground text-sm">You already reviewed this resume. Duplicates are skipped.</span>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}

function ReviewCard({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent className="grid gap-3">{children}</CardContent>
        </Card>
    );
}

function ReviewRow({ included, onToggle, children }: { included: boolean; onToggle: (value: boolean) => void; children: ReactNode }) {
    return (
        <div className={`flex gap-3 rounded-lg border p-3 ${included ? '' : 'opacity-50'}`}>
            <Checkbox checked={included} onCheckedChange={(checked) => onToggle(checked === true)} className="mt-1" aria-label="Include this item" />
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
