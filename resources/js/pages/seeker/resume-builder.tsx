import { Transition } from '@headlessui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Download, Eye, FileCheck2, LoaderCircle, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler, KeyboardEvent, useState } from 'react';

import InputError from '@/components/input-error';
import { FieldInput } from '@/components/profile/entry-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My profile', href: '/profile' },
    { title: 'Resume builder', href: '/resume-builder' },
];

const MAX_LANGUAGES = 8;
const MAX_REFERENCES = 3;
const COMMON_LANGUAGES = ['English', 'Filipino', 'Ilocano', 'Kankanaey'];
const NOT_INCLUDED = 'none';

type Reference = { name: string; position: string; organization: string; contact: string };

type DetailsForm = {
    desired_position: string;
    objective: string;
    languages: string[];
    references: Reference[];
    civil_status: string;
    nationality: string;
    show_birthdate: boolean;
    show_sex: boolean;
    include_certification: boolean;
};

interface ResumeBuilderProps {
    details: {
        desired_position: string | null;
        objective: string | null;
        languages: string[];
        references: Array<{ name: string; position: string | null; organization: string | null; contact: string }>;
        civil_status: string | null;
        nationality: string | null;
        show_birthdate: boolean;
        show_sex: boolean;
        include_certification: boolean;
    };
    contents: {
        name: string;
        address: string | null;
        mobile: string | null;
        email: string;
        experience: number;
        education: number;
        trainings: number;
        certifications: number;
        skills: number;
        has_birthdate: boolean;
        has_sex: boolean;
    };
    gaps: string[];
    currentResume: { original_name: string; is_generated: boolean; saved_at: string } | null;
    options: { civilStatuses: Option[] };
}

function entries(count: number, singular: string) {
    return count === 0 ? 'None yet' : `${count} ${count === 1 ? singular : `${singular}s`}`;
}

export default function ResumeBuilder({ details, contents, gaps, currentResume, options }: ResumeBuilderProps) {
    const form = useForm<DetailsForm>({
        desired_position: details.desired_position ?? '',
        objective: details.objective ?? '',
        languages: details.languages,
        references: details.references.map((r) => ({
            name: r.name,
            position: r.position ?? '',
            organization: r.organization ?? '',
            contact: r.contact,
        })),
        civil_status: details.civil_status ?? NOT_INCLUDED,
        nationality: details.nationality ?? '',
        show_birthdate: details.show_birthdate,
        show_sex: details.show_sex,
        include_certification: details.include_certification,
    });
    const errors = form.errors as Record<string, string>;
    const [language, setLanguage] = useState('');
    const [using, setUsing] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, civil_status: data.civil_status === NOT_INCLUDED ? null : data.civil_status }));
        form.put(route('seeker.resume-builder.update'), { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    const addLanguage = (name: string) => {
        const value = name.trim();
        if (!value || form.data.languages.length >= MAX_LANGUAGES) return;
        if (form.data.languages.some((l) => l.toLowerCase() === value.toLowerCase())) return;

        form.setData('languages', [...form.data.languages, value]);
        setLanguage('');
    };

    const onLanguageKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addLanguage(language);
        }
    };

    const setReference = (index: number, field: keyof Reference, value: string) => {
        form.setData(
            'references',
            form.data.references.map((r, i) => (i === index ? { ...r, [field]: value } : r)),
        );
    };

    const useAsResume = () => {
        router.post(route('seeker.resume-builder.store'), {}, { onStart: () => setUsing(true), onFinish: () => setUsing(false) });
    };

    const fromProfile = [
        {
            label: 'Name and contact',
            value: [contents.address, contents.mobile, contents.email].filter(Boolean).join(' · '),
            ok: !!contents.mobile && !!contents.address,
        },
        { label: 'Work experience', value: entries(contents.experience, 'entry'), ok: contents.experience > 0 },
        { label: 'Education', value: entries(contents.education, 'school'), ok: contents.education > 0 },
        { label: 'Trainings and seminars', value: entries(contents.trainings, 'training'), ok: contents.trainings > 0 },
        { label: 'Licenses and certifications', value: entries(contents.certifications, 'certification'), ok: contents.certifications > 0 },
        { label: 'Skills', value: entries(contents.skills, 'skill'), ok: contents.skills > 0 },
    ];
    const unsaved = form.isDirty;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Resume builder" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Resume builder</h1>
                    <p className="text-muted-foreground">
                        We turn your profile into a resume in the standard format employers ask for. Add the details below, preview it, then use it
                        for your applications.
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <form onSubmit={submit} className="grid min-w-0 gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Position and objective</CardTitle>
                                <CardDescription>Shown at the top of your resume, right under your name.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FieldInput
                                    idPrefix="resume"
                                    field={{
                                        name: 'desired_position',
                                        label: 'Position you are applying for',
                                        type: 'text',
                                        placeholder: 'e.g. Office Clerk',
                                        hint: 'Change this for each job you apply to, or leave it empty.',
                                    }}
                                    value={form.data.desired_position}
                                    error={errors.desired_position}
                                    onChange={(value) => form.setData('desired_position', String(value))}
                                    disabled={form.processing}
                                />
                                <FieldInput
                                    idPrefix="resume"
                                    field={{
                                        name: 'objective',
                                        label: 'Objective',
                                        type: 'textarea',
                                        placeholder:
                                            'e.g. To work as an office clerk where I can use my computer and record-keeping skills and grow with the organization.',
                                        hint: 'Two or three sentences: the work you are looking for and what you offer. Starts from "About me" on your profile.',
                                    }}
                                    value={form.data.objective}
                                    error={errors.objective}
                                    onChange={(value) => form.setData('objective', String(value))}
                                    disabled={form.processing}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Languages</CardTitle>
                                <CardDescription>Languages you can speak and write.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {form.data.languages.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {form.data.languages.map((name, index) => (
                                            <Badge key={name} variant="secondary" className="gap-1 py-1 pr-1 pl-2.5">
                                                {name}
                                                <button
                                                    type="button"
                                                    className="hover:bg-muted rounded-sm p-0.5"
                                                    onClick={() =>
                                                        form.setData(
                                                            'languages',
                                                            form.data.languages.filter((_, i) => i !== index),
                                                        )
                                                    }
                                                    aria-label={`Remove ${name}`}
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                                {form.data.languages.length < MAX_LANGUAGES && (
                                    <>
                                        <div className="flex gap-2">
                                            <Input
                                                aria-label="Add a language"
                                                value={language}
                                                onChange={(e) => setLanguage(e.target.value)}
                                                onKeyDown={onLanguageKey}
                                                placeholder="Type a language"
                                                maxLength={50}
                                            />
                                            <Button type="button" variant="outline" onClick={() => addLanguage(language)}>
                                                <Plus className="h-4 w-4" /> Add
                                            </Button>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {COMMON_LANGUAGES.filter(
                                                (l) => !form.data.languages.some((chosen) => chosen.toLowerCase() === l.toLowerCase()),
                                            ).map((l) => (
                                                <Button key={l} type="button" variant="ghost" size="sm" onClick={() => addLanguage(l)}>
                                                    <Plus className="h-3 w-3" /> {l}
                                                </Button>
                                            ))}
                                        </div>
                                    </>
                                )}
                                <InputError message={errors.languages ?? Object.entries(errors).find(([key]) => key.startsWith('languages.'))?.[1]} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Character references</CardTitle>
                                <CardDescription>
                                    Up to three people who can vouch for you, such as a former employer, teacher or barangay official. Not a relative.
                                    Ask them first: their name and contact details will be on your resume. With none listed, your resume says
                                    "Available upon request".
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {form.data.references.map((reference, index) => (
                                    <div key={index} className="grid gap-3 rounded-lg border p-4">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-medium">Reference {index + 1}</p>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'references',
                                                        form.data.references.filter((_, i) => i !== index),
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" /> Remove
                                            </Button>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {(
                                                [
                                                    { name: 'name', label: 'Full name', required: true },
                                                    { name: 'position', label: 'Position', placeholder: 'e.g. Barangay Captain' },
                                                    { name: 'organization', label: 'Company or office', placeholder: 'e.g. Barangay Abatan' },
                                                    { name: 'contact', label: 'Mobile number or email', required: true },
                                                ] as Array<{ name: keyof Reference; label: string; required?: boolean; placeholder?: string }>
                                            ).map((field) => (
                                                <FieldInput
                                                    key={field.name}
                                                    idPrefix={`reference-${index}`}
                                                    field={{ ...field, type: 'text' }}
                                                    value={reference[field.name]}
                                                    error={errors[`references.${index}.${field.name}`]}
                                                    onChange={(value) => setReference(index, field.name, String(value))}
                                                    disabled={form.processing}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {form.data.references.length < MAX_REFERENCES && (
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData('references', [
                                                    ...form.data.references,
                                                    { name: '', position: '', organization: '', contact: '' },
                                                ])
                                            }
                                        >
                                            <Plus className="h-4 w-4" /> Add a reference
                                        </Button>
                                    </div>
                                )}
                                <InputError message={errors.references} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Personal information</CardTitle>
                                <CardDescription>
                                    All optional. Employers may not require your age or date of birth (Anti-Age Discrimination in Employment Act, RA
                                    10911). Include these only if you want to.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-3">
                                    <FieldInput
                                        idPrefix="resume"
                                        field={{ name: 'show_birthdate', label: 'Show my date of birth and age', type: 'checkbox' }}
                                        value={form.data.show_birthdate}
                                        error={errors.show_birthdate}
                                        onChange={(value) => form.setData('show_birthdate', value === true)}
                                        disabled={form.processing || !contents.has_birthdate}
                                    />
                                    <FieldInput
                                        idPrefix="resume"
                                        field={{ name: 'show_sex', label: 'Show my sex', type: 'checkbox' }}
                                        value={form.data.show_sex}
                                        error={errors.show_sex}
                                        onChange={(value) => form.setData('show_sex', value === true)}
                                        disabled={form.processing || !contents.has_sex}
                                    />
                                    {(!contents.has_birthdate || !contents.has_sex) && (
                                        <p className="text-muted-foreground text-xs">
                                            Date of birth and gender come from your{' '}
                                            <Link href={route('seeker.profile.edit') + '#personal'} className="underline underline-offset-4">
                                                personal information
                                            </Link>
                                            .
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FieldInput
                                        idPrefix="resume"
                                        field={{
                                            name: 'civil_status',
                                            label: 'Civil status',
                                            type: 'select',
                                            options: [{ value: NOT_INCLUDED, label: "Don't include" }, ...options.civilStatuses],
                                        }}
                                        value={form.data.civil_status}
                                        error={errors.civil_status}
                                        onChange={(value) => form.setData('civil_status', String(value))}
                                        disabled={form.processing}
                                    />
                                    <FieldInput
                                        idPrefix="resume"
                                        field={{ name: 'nationality', label: 'Nationality', type: 'text', placeholder: 'e.g. Filipino' }}
                                        value={form.data.nationality}
                                        error={errors.nationality}
                                        onChange={(value) => form.setData('nationality', String(value))}
                                        disabled={form.processing}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Closing</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <FieldInput
                                    idPrefix="resume"
                                    field={{
                                        name: 'include_certification',
                                        label: 'End with "I hereby certify that the above information is true and correct" and a signature line',
                                        type: 'checkbox',
                                    }}
                                    value={form.data.include_certification}
                                    error={errors.include_certification}
                                    onChange={(value) => form.setData('include_certification', value === true)}
                                    disabled={form.processing}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save details
                            </Button>
                            <Transition
                                show={form.recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-muted-foreground text-sm">Saved</p>
                            </Transition>
                        </div>
                    </form>

                    <Card className="lg:sticky lg:top-4">
                        <CardHeader>
                            <CardTitle className="text-base">Your resume</CardTitle>
                            <CardDescription>
                                From your{' '}
                                <Link href={route('seeker.profile.edit')} className="underline underline-offset-4">
                                    profile
                                </Link>
                                . Update it there and your resume follows.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <ul className="grid gap-2.5">
                                <li className="text-sm font-medium">{contents.name}</li>
                                {fromProfile.map((item) => (
                                    <li key={item.label} className="flex items-start gap-2 text-sm">
                                        {item.ok ? (
                                            <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        ) : (
                                            <CircleAlert className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                        )}
                                        <div className="min-w-0">
                                            <p>{item.label}</p>
                                            <p className="text-muted-foreground text-xs break-words">{item.value}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            {gaps.length > 0 && (
                                <div className="grid gap-1.5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                    <p className="font-medium">To make your resume stronger</p>
                                    <ul className="list-disc space-y-1 pl-4">
                                        {gaps.map((gap) => (
                                            <li key={gap}>{gap}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {unsaved && (
                                <p className="text-sm text-amber-700 dark:text-amber-300">Save your details first to see them on your resume.</p>
                            )}

                            <div className="grid gap-2">
                                <Button variant="outline" asChild>
                                    <a
                                        href={route('seeker.resume-builder.pdf')}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-disabled={unsaved}
                                        className={unsaved ? 'pointer-events-none opacity-50' : undefined}
                                    >
                                        <Eye className="h-4 w-4" /> Preview PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a
                                        href={route('seeker.resume-builder.pdf', { download: 1 })}
                                        aria-disabled={unsaved}
                                        className={unsaved ? 'pointer-events-none opacity-50' : undefined}
                                    >
                                        <Download className="h-4 w-4" /> Download PDF
                                    </a>
                                </Button>
                                <Button onClick={useAsResume} disabled={unsaved || using}>
                                    {using ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <FileCheck2 className="h-4 w-4" />}
                                    Use as my resume
                                </Button>
                            </div>

                            <p className="text-muted-foreground text-xs">
                                {currentResume === null
                                    ? 'This becomes the resume sent with your applications.'
                                    : currentResume.is_generated
                                      ? `Your current resume was made here on ${new Date(currentResume.saved_at).toLocaleDateString()}. Use it again after changes so new applications get the latest version.`
                                      : `This replaces your uploaded resume "${currentResume.original_name}" for new applications.`}{' '}
                                Applications you already sent keep the resume they were sent with.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
