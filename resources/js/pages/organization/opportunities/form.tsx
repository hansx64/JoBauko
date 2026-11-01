import { Head, Link, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, CircleAlert, GraduationCap, LoaderCircle, Plus, Presentation, Sprout, X } from 'lucide-react';
import { FormEventHandler, KeyboardEvent, ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { OpportunityActions } from '@/components/opportunity-actions';
import { StatusBadge } from '@/components/status-badge';
import { StringListEditor } from '@/components/string-list-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { VerificationBanner } from '@/components/verification-banner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type Barangay, type BreadcrumbItem, type Option } from '@/types';
import { type OpportunityFormData, type OpportunityFormOptions, type OrganizationSummary } from '@/types/organization';

interface OpportunityFormProps {
    organization: OrganizationSummary;
    opportunity: OpportunityFormData | null;
    publishProblems: string[];
    options: OpportunityFormOptions;
    barangays: Barangay[];
    skillSuggestions: string[];
}

type FormShape = {
    intent: 'draft' | 'publish';
    category: string;
    title: string;
    description: string;
    qualifications: string;
    benefits: string;
    application_instructions: string;
    min_education: string;
    barangay_id: string;
    location_text: string;
    is_remote: boolean;
    application_start: string;
    application_deadline: string;
    slots: string;
    contact_email: string;
    contact_number: string;
    requirements: string[];
    documents: { id: number | null; name: string; is_required: boolean }[];
    skills: string[];
    job: { employment_type: string; salary_min: string; salary_max: string; work_setup: string; min_experience_years: string };
    scholarship: { education_level: string; eligible_courses: string[]; academic_requirements: string; financial_requirements: string };
    livelihood: {
        assistance_type: string;
        target_beneficiaries: string;
        assistance_provided: string;
        training_requirements: string;
        target_classifications: string[];
    };
    training: {
        training_type: string;
        starts_on: string;
        ends_on: string;
        schedule: string;
        duration_hours: string;
        fee: string;
        certificate: string;
        topics: string;
        inclusions: string;
        target_participants: string;
        target_classifications: string[];
    };
};

const NONE = 'none';
const s = (value: string | number | null | undefined) => (value === null || value === undefined ? '' : String(value));

const categoryMeta: Record<string, { icon: typeof BriefcaseBusiness; titleLabel: string; titlePlaceholder: string }> = {
    job: { icon: BriefcaseBusiness, titleLabel: 'Job title', titlePlaceholder: 'e.g. Municipal IT Assistant' },
    scholarship: { icon: GraduationCap, titleLabel: 'Scholarship name', titlePlaceholder: 'e.g. Bauko Municipal Scholarship Program' },
    livelihood: { icon: Sprout, titleLabel: 'Program name', titlePlaceholder: 'e.g. Highland Vegetable Starter Kit Program' },
    training: { icon: Presentation, titleLabel: 'Training or seminar title', titlePlaceholder: 'e.g. Shielded Metal Arc Welding NC I Training' },
};

export default function OpportunityForm({ organization, opportunity, publishProblems, options, barangays, skillSuggestions }: OpportunityFormProps) {
    const isNew = opportunity === null;
    const isDraft = isNew || opportunity.stored_status === 'draft';
    const readOnly = !isNew && ['archived', 'suspended'].includes(opportunity.stored_status);
    const canPublish = organization.verification_status === 'verified';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Opportunities', href: '/org/opportunities' },
        {
            title: isNew ? 'New opportunity' : opportunity.title,
            href: isNew ? '/org/opportunities/create' : `/org/opportunities/${opportunity.id}/edit`,
        },
    ];

    const form = useForm<FormShape>({
        intent: 'draft',
        category: opportunity?.category ?? 'job',
        title: s(opportunity?.title),
        description: s(opportunity?.description),
        qualifications: s(opportunity?.qualifications),
        benefits: s(opportunity?.benefits),
        application_instructions: s(opportunity?.application_instructions),
        min_education: s(opportunity?.min_education),
        barangay_id: s(opportunity?.barangay_id),
        location_text: s(opportunity?.location_text),
        is_remote: opportunity?.is_remote ?? false,
        application_start: s(opportunity?.application_start),
        application_deadline: s(opportunity?.application_deadline),
        slots: s(opportunity?.slots),
        contact_email: s(opportunity?.contact_email),
        contact_number: s(opportunity?.contact_number),
        requirements: opportunity?.requirements ?? [],
        documents: opportunity?.documents.map((d) => ({ id: d.id, name: d.name, is_required: d.is_required })) ?? [
            { id: null, name: 'Resume / CV', is_required: true },
        ],
        skills: opportunity?.skills ?? [],
        job: {
            employment_type: s(opportunity?.job?.employment_type),
            salary_min: s(opportunity?.job?.salary_min),
            salary_max: s(opportunity?.job?.salary_max),
            work_setup: s(opportunity?.job?.work_setup) || 'onsite',
            min_experience_years: s(opportunity?.job?.min_experience_years),
        },
        scholarship: {
            education_level: s(opportunity?.scholarship?.education_level),
            eligible_courses: opportunity?.scholarship?.eligible_courses ?? [],
            academic_requirements: s(opportunity?.scholarship?.academic_requirements),
            financial_requirements: s(opportunity?.scholarship?.financial_requirements),
        },
        livelihood: {
            assistance_type: s(opportunity?.livelihood?.assistance_type),
            target_beneficiaries: s(opportunity?.livelihood?.target_beneficiaries),
            assistance_provided: s(opportunity?.livelihood?.assistance_provided),
            training_requirements: s(opportunity?.livelihood?.training_requirements),
            target_classifications: opportunity?.livelihood?.target_classifications ?? [],
        },
        training: {
            training_type: s(opportunity?.training?.training_type),
            starts_on: s(opportunity?.training?.starts_on),
            ends_on: s(opportunity?.training?.ends_on),
            schedule: s(opportunity?.training?.schedule),
            duration_hours: s(opportunity?.training?.duration_hours),
            fee: s(opportunity?.training?.fee),
            certificate: s(opportunity?.training?.certificate),
            topics: s(opportunity?.training?.topics),
            inclusions: s(opportunity?.training?.inclusions),
            target_participants: s(opportunity?.training?.target_participants),
            target_classifications: opportunity?.training?.target_classifications ?? [],
        },
    });

    const { data, setData } = form;
    const errors = form.errors as Record<string, string>;
    const meta = categoryMeta[data.category];

    const submit =
        (intent: 'draft' | 'publish'): FormEventHandler =>
        (e) => {
            e.preventDefault();
            form.transform((current) => ({ ...current, intent }));

            if (isNew) {
                form.post(route('org.opportunities.store'), { preserveScroll: true });
            } else {
                form.patch(route('org.opportunities.update', opportunity.id), { preserveScroll: true });
            }
        };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isNew ? 'New opportunity' : `Edit ${opportunity.title}`} />
            <form onSubmit={submit(isDraft ? 'draft' : 'publish')} className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{isNew ? 'New opportunity' : 'Edit opportunity'}</h1>
                            {!isNew && <StatusBadge status={opportunity.status} label={opportunity.status_label} />}
                        </div>
                        <p className="text-muted-foreground">
                            {isDraft
                                ? 'Drafts can be saved incomplete. Everything marked * is needed to publish.'
                                : 'Changes are visible to applicants right away.'}
                        </p>
                    </div>
                    {!isNew && !readOnly && (
                        <OpportunityActions
                            opportunity={{
                                id: opportunity.id,
                                title: opportunity.title,
                                status: opportunity.status,
                                deadline: opportunity.application_deadline,
                            }}
                            showEdit={false}
                        />
                    )}
                </div>

                <VerificationBanner organization={organization} />

                {errors.status && (
                    <Alert>
                        <InputError message={errors.status} />
                    </Alert>
                )}

                {publishProblems.length > 0 && (
                    <Alert>
                        <p className="font-medium">Before this can be published:</p>
                        <ul className="list-disc pl-5">
                            {publishProblems.map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    </Alert>
                )}

                {readOnly && (
                    <Alert>
                        <p>This opportunity is {opportunity?.status_label.toLowerCase()} and can no longer be edited.</p>
                        {opportunity?.suspension_reason && (
                            <p className="mt-1">
                                <span className="font-medium">Reason given by the administrator: </span>
                                {opportunity.suspension_reason}
                            </p>
                        )}
                    </Alert>
                )}

                <fieldset disabled={readOnly || form.processing} className="grid gap-6">
                    <Section title="Type of opportunity">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="radiogroup" aria-label="Category">
                            {options.categories.map((category) => {
                                const Icon = categoryMeta[category.value].icon;
                                const locked = !isNew && (opportunity.was_published || !isDraft);
                                const selected = data.category === category.value;

                                return (
                                    <button
                                        key={category.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        disabled={locked && !selected}
                                        onClick={() => setData('category', category.value)}
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                            selected ? 'border-primary bg-primary/5 ring-primary ring-1' : 'hover:bg-muted',
                                        )}
                                    >
                                        <Icon className="text-primary h-5 w-5" />
                                        <span className="font-medium">{category.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <InputError message={errors.category} />
                    </Section>

                    <Section title="Basic information">
                        <Field label={`${meta.titleLabel} *`} error={errors.title}>
                            <Input
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder={meta.titlePlaceholder}
                                required
                            />
                        </Field>
                        <Field label="Description *" error={errors.description}>
                            <Textarea
                                rows={6}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="What is this opportunity about, and who is it for?"
                            />
                        </Field>
                        <Field label="Qualifications" error={errors.qualifications}>
                            <Textarea
                                rows={4}
                                value={data.qualifications}
                                onChange={(e) => setData('qualifications', e.target.value)}
                                placeholder="Who is qualified to apply?"
                            />
                        </Field>
                    </Section>

                    {data.category === 'job' && (
                        <Section title="Job details">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Employment type *" error={errors['job.employment_type']}>
                                    <OptionSelect
                                        value={data.job.employment_type}
                                        options={options.employmentTypes}
                                        onChange={(v) => setData('job', { ...data.job, employment_type: v })}
                                    />
                                </Field>
                                <Field label="Work setup" error={errors['job.work_setup']}>
                                    <OptionSelect
                                        value={data.job.work_setup}
                                        options={options.workSetups}
                                        onChange={(v) => setData('job', { ...data.job, work_setup: v })}
                                    />
                                </Field>
                                <Field label="Minimum monthly salary (₱)" error={errors['job.salary_min']}>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={data.job.salary_min}
                                        onChange={(e) => setData('job', { ...data.job, salary_min: e.target.value })}
                                    />
                                </Field>
                                <Field label="Maximum monthly salary (₱)" error={errors['job.salary_max']}>
                                    <Input
                                        type="number"
                                        min={0}
                                        value={data.job.salary_max}
                                        onChange={(e) => setData('job', { ...data.job, salary_max: e.target.value })}
                                    />
                                </Field>
                                <Field label="Required experience (years)" error={errors['job.min_experience_years']}>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={50}
                                        value={data.job.min_experience_years}
                                        onChange={(e) => setData('job', { ...data.job, min_experience_years: e.target.value })}
                                    />
                                </Field>
                                <Field label="Educational requirement" error={errors.min_education}>
                                    <OptionSelect
                                        value={data.min_education}
                                        options={options.educationLevels}
                                        onChange={(v) => setData('min_education', v)}
                                        noneLabel="No requirement"
                                    />
                                </Field>
                            </div>
                        </Section>
                    )}

                    {data.category === 'scholarship' && (
                        <Section title="Scholarship details">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Educational level of scholars *" error={errors['scholarship.education_level']}>
                                    <OptionSelect
                                        value={data.scholarship.education_level}
                                        options={options.educationLevels}
                                        onChange={(v) => setData('scholarship', { ...data.scholarship, education_level: v })}
                                    />
                                </Field>
                                <Field label="Minimum attainment to apply" error={errors.min_education}>
                                    <OptionSelect
                                        value={data.min_education}
                                        options={options.educationLevels}
                                        onChange={(v) => setData('min_education', v)}
                                        noneLabel="No requirement"
                                    />
                                </Field>
                            </div>
                            <Field label="Eligible courses or programs" hint="Leave empty if open to all courses.">
                                <StringListEditor
                                    items={data.scholarship.eligible_courses}
                                    onChange={(items) => setData('scholarship', { ...data.scholarship, eligible_courses: items })}
                                    placeholder="e.g. BS Agriculture"
                                    addLabel="Add course"
                                    errors={errors}
                                    errorPrefix="scholarship.eligible_courses"
                                    max={50}
                                />
                            </Field>
                            <Field label="Academic requirements" error={errors['scholarship.academic_requirements']}>
                                <Textarea
                                    rows={3}
                                    value={data.scholarship.academic_requirements}
                                    onChange={(e) => setData('scholarship', { ...data.scholarship, academic_requirements: e.target.value })}
                                    placeholder="e.g. General weighted average of at least 85%"
                                />
                            </Field>
                            <Field label="Financial requirements" error={errors['scholarship.financial_requirements']}>
                                <Textarea
                                    rows={3}
                                    value={data.scholarship.financial_requirements}
                                    onChange={(e) => setData('scholarship', { ...data.scholarship, financial_requirements: e.target.value })}
                                    placeholder="e.g. Combined family income below ₱250,000 a year"
                                />
                            </Field>
                        </Section>
                    )}

                    {data.category === 'livelihood' && (
                        <Section title="Livelihood program details">
                            <Field label="Type of assistance *" error={errors['livelihood.assistance_type']}>
                                <OptionSelect
                                    value={data.livelihood.assistance_type}
                                    options={options.assistanceTypes}
                                    onChange={(v) => setData('livelihood', { ...data.livelihood, assistance_type: v })}
                                />
                            </Field>
                            <Field label="Target beneficiaries *" error={errors['livelihood.target_beneficiaries']}>
                                <Textarea
                                    rows={3}
                                    value={data.livelihood.target_beneficiaries}
                                    onChange={(e) => setData('livelihood', { ...data.livelihood, target_beneficiaries: e.target.value })}
                                    placeholder="e.g. Unemployed residents and out-of-school youth of Bauko"
                                />
                            </Field>
                            <Field label="Assistance provided *" error={errors['livelihood.assistance_provided']}>
                                <Textarea
                                    rows={3}
                                    value={data.livelihood.assistance_provided}
                                    onChange={(e) => setData('livelihood', { ...data.livelihood, assistance_provided: e.target.value })}
                                    placeholder="e.g. Vegetable seeds, fertilizer and a set of farm tools"
                                />
                            </Field>
                            <Field label="Training requirements" error={errors['livelihood.training_requirements']}>
                                <Textarea
                                    rows={3}
                                    value={data.livelihood.training_requirements}
                                    onChange={(e) => setData('livelihood', { ...data.livelihood, training_requirements: e.target.value })}
                                />
                            </Field>
                            <Field
                                label="Especially for"
                                hint="Used to recommend this program to the right people. It does not restrict who may apply."
                            >
                                <ClassificationPicker
                                    options={options.classifications}
                                    selected={data.livelihood.target_classifications}
                                    onChange={(selected) => setData('livelihood', { ...data.livelihood, target_classifications: selected })}
                                />
                            </Field>
                            <Field label="Minimum educational attainment" error={errors.min_education}>
                                <OptionSelect
                                    value={data.min_education}
                                    options={options.educationLevels}
                                    onChange={(v) => setData('min_education', v)}
                                    noneLabel="No requirement"
                                />
                            </Field>
                        </Section>
                    )}

                    {data.category === 'training' && (
                        <Section title="Training or seminar details">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Type *" error={errors['training.training_type']}>
                                    <OptionSelect
                                        value={data.training.training_type}
                                        options={options.trainingTypes}
                                        onChange={(v) => setData('training', { ...data.training, training_type: v })}
                                    />
                                </Field>
                                <Field label="Fee (₱)" error={errors['training.fee']} hint="Leave empty if the training is free.">
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.training.fee}
                                        onChange={(e) => setData('training', { ...data.training, fee: e.target.value })}
                                    />
                                </Field>
                                <Field label="Starts on" error={errors['training.starts_on']}>
                                    <Input
                                        type="date"
                                        value={data.training.starts_on}
                                        onChange={(e) => setData('training', { ...data.training, starts_on: e.target.value })}
                                    />
                                </Field>
                                <Field label="Ends on" error={errors['training.ends_on']} hint="Leave empty for a one-day event.">
                                    <Input
                                        type="date"
                                        value={data.training.ends_on}
                                        onChange={(e) => setData('training', { ...data.training, ends_on: e.target.value })}
                                    />
                                </Field>
                                <Field label="Schedule" error={errors['training.schedule']}>
                                    <Input
                                        value={data.training.schedule}
                                        onChange={(e) => setData('training', { ...data.training, schedule: e.target.value })}
                                        placeholder="e.g. Saturdays, 8:00 AM – 5:00 PM"
                                    />
                                </Field>
                                <Field label="Total number of hours" error={errors['training.duration_hours']}>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.training.duration_hours}
                                        onChange={(e) => setData('training', { ...data.training, duration_hours: e.target.value })}
                                    />
                                </Field>
                            </div>
                            <Field label="Certificate given" error={errors['training.certificate']}>
                                <Input
                                    value={data.training.certificate}
                                    onChange={(e) => setData('training', { ...data.training, certificate: e.target.value })}
                                    placeholder="e.g. TESDA National Certificate II, certificate of attendance"
                                />
                            </Field>
                            <Field label="What participants will learn *" error={errors['training.topics']} hint="One topic per line works well.">
                                <Textarea
                                    rows={4}
                                    value={data.training.topics}
                                    onChange={(e) => setData('training', { ...data.training, topics: e.target.value })}
                                />
                            </Field>
                            <Field label="What is provided" error={errors['training.inclusions']}>
                                <Textarea
                                    rows={3}
                                    value={data.training.inclusions}
                                    onChange={(e) => setData('training', { ...data.training, inclusions: e.target.value })}
                                    placeholder="e.g. Training materials, meals, transportation allowance, starter toolkit"
                                />
                            </Field>
                            <Field label="Who should attend" error={errors['training.target_participants']}>
                                <Textarea
                                    rows={3}
                                    value={data.training.target_participants}
                                    onChange={(e) => setData('training', { ...data.training, target_participants: e.target.value })}
                                    placeholder="e.g. Out-of-school youth and unemployed residents interested in welding"
                                />
                            </Field>
                            <Field
                                label="Especially for"
                                hint="Used to recommend this training to the right people. It does not restrict who may apply."
                            >
                                <ClassificationPicker
                                    options={options.classifications}
                                    selected={data.training.target_classifications}
                                    onChange={(selected) => setData('training', { ...data.training, target_classifications: selected })}
                                />
                            </Field>
                            <Field label="Minimum educational attainment" error={errors.min_education}>
                                <OptionSelect
                                    value={data.min_education}
                                    options={options.educationLevels}
                                    onChange={(v) => setData('min_education', v)}
                                    noneLabel="No requirement"
                                />
                            </Field>
                        </Section>
                    )}

                    <Section title="Location and schedule">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Barangay in Bauko" error={errors.barangay_id}>
                                <Select
                                    value={data.barangay_id || NONE}
                                    onValueChange={(v) => setData('barangay_id', v === NONE ? '' : v)}
                                    disabled={readOnly}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>Not in Bauko / not specific</SelectItem>
                                        {barangays.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Other location" error={errors.location_text} hint="For places outside Bauko, e.g. La Trinidad, Benguet.">
                                <Input value={data.location_text} onChange={(e) => setData('location_text', e.target.value)} />
                            </Field>
                            <div className="flex items-center gap-2 sm:col-span-2">
                                <Checkbox id="is_remote" checked={data.is_remote} onCheckedChange={(v) => setData('is_remote', v === true)} />
                                <Label htmlFor="is_remote">Can be done remotely</Label>
                            </div>
                            <Field
                                label="Applications open on"
                                error={errors.application_start}
                                hint="Leave empty to open as soon as it's published."
                            >
                                <Input type="date" value={data.application_start} onChange={(e) => setData('application_start', e.target.value)} />
                            </Field>
                            <Field label="Application deadline *" error={errors.application_deadline}>
                                <Input
                                    type="date"
                                    value={data.application_deadline}
                                    onChange={(e) => setData('application_deadline', e.target.value)}
                                />
                            </Field>
                            <Field label="Available slots" error={errors.slots} hint="Leave empty if not limited.">
                                <Input type="number" min={1} value={data.slots} onChange={(e) => setData('slots', e.target.value)} />
                            </Field>
                        </div>
                    </Section>

                    <Section title="Requirements">
                        <Field label="Requirements" hint="One per line, e.g. “Must be a resident of Bauko”.">
                            <StringListEditor
                                items={data.requirements}
                                onChange={(items) => setData('requirements', items)}
                                placeholder="Requirement"
                                addLabel="Add requirement"
                                errors={errors}
                                errorPrefix="requirements"
                            />
                        </Field>
                        <Field label="Skills" hint="Skills applicants should have. Used to recommend this opportunity to matching seekers.">
                            <SkillInput skills={data.skills} suggestions={skillSuggestions} onChange={(skills) => setData('skills', skills)} />
                            {Object.entries(errors)
                                .filter(([key]) => key.startsWith('skills.'))
                                .map(([key, message]) => (
                                    <InputError key={key} message={message} />
                                ))}
                        </Field>
                        <Field label="Documents applicants must submit">
                            <div className="grid gap-2">
                                {data.documents.map((doc, index) => (
                                    <div key={index}>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                value={doc.name}
                                                onChange={(e) =>
                                                    setData(
                                                        'documents',
                                                        data.documents.map((d, i) => (i === index ? { ...d, name: e.target.value } : d)),
                                                    )
                                                }
                                                placeholder="e.g. Transcript of Records"
                                                aria-label={`Document ${index + 1}`}
                                            />
                                            <label className="flex shrink-0 items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={doc.is_required}
                                                    onCheckedChange={(v) =>
                                                        setData(
                                                            'documents',
                                                            data.documents.map((d, i) => (i === index ? { ...d, is_required: v === true } : d)),
                                                        )
                                                    }
                                                />
                                                Required
                                            </label>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setData(
                                                        'documents',
                                                        data.documents.filter((_, i) => i !== index),
                                                    )
                                                }
                                                aria-label="Remove document"
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <InputError message={errors[`documents.${index}.name`]} />
                                    </div>
                                ))}
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setData('documents', [...data.documents, { id: null, name: '', is_required: true }])}
                                        disabled={data.documents.length >= 15}
                                    >
                                        <Plus className="h-4 w-4" /> Add document
                                    </Button>
                                </div>
                            </div>
                        </Field>
                    </Section>

                    <Section title="Benefits, instructions and contact">
                        <Field label={data.category === 'job' ? 'Benefits and compensation' : 'Benefits'} error={errors.benefits}>
                            <Textarea rows={3} value={data.benefits} onChange={(e) => setData('benefits', e.target.value)} />
                        </Field>
                        <Field label="How to apply" error={errors.application_instructions}>
                            <Textarea
                                rows={3}
                                value={data.application_instructions}
                                onChange={(e) => setData('application_instructions', e.target.value)}
                                placeholder="Steps or notes for applicants, e.g. interview schedule"
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Contact email" error={errors.contact_email}>
                                <Input type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} />
                            </Field>
                            <Field label="Contact number" error={errors.contact_number}>
                                <Input type="tel" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} />
                            </Field>
                        </div>
                    </Section>
                </fieldset>

                {!readOnly && (
                    <div className="bg-background/95 sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t px-4 py-3 md:-mx-6 md:px-6">
                        {isDraft ? (
                            <>
                                <Button type="button" onClick={submit('publish')} disabled={form.processing || !canPublish}>
                                    {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Save and publish
                                </Button>
                                <Button type="button" variant="outline" onClick={submit('draft')} disabled={form.processing}>
                                    Save draft
                                </Button>
                                {!canPublish && (
                                    <span className="text-muted-foreground text-sm">Publishing is available once your organization is verified.</span>
                                )}
                            </>
                        ) : (
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save changes
                            </Button>
                        )}
                        <Button type="button" variant="ghost" asChild>
                            <Link href={route('org.opportunities.index')}>Cancel</Link>
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">{children}</CardContent>
        </Card>
    );
}

function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Alert({ children }: { children: ReactNode }) {
    return (
        <div className="flex gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" />
            <div className="space-y-1">{children}</div>
        </div>
    );
}

function ClassificationPicker({ options, selected, onChange }: { options: Option[]; selected: string[]; onChange: (selected: string[]) => void }) {
    return (
        <div className="flex flex-wrap gap-2">
            {options.map((c) => (
                <label
                    key={c.value}
                    className="has-[:checked]:border-primary has-[:checked]:bg-primary/5 flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                >
                    <Checkbox
                        checked={selected.includes(c.value)}
                        onCheckedChange={(value) => onChange(value === true ? [...selected, c.value] : selected.filter((v) => v !== c.value))}
                    />
                    {c.label}
                </label>
            ))}
        </div>
    );
}

function OptionSelect({
    value,
    options,
    onChange,
    noneLabel,
}: {
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    noneLabel?: string;
}) {
    return (
        <Select value={value || (noneLabel ? NONE : '')} onValueChange={(v) => onChange(v === NONE ? '' : v)}>
            <SelectTrigger>
                <SelectValue placeholder="Select…" />
            </SelectTrigger>
            <SelectContent>
                {noneLabel && <SelectItem value={NONE}>{noneLabel}</SelectItem>}
                {options.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function SkillInput({ skills, suggestions, onChange }: { skills: string[]; suggestions: string[]; onChange: (skills: string[]) => void }) {
    const [draft, setDraft] = useState('');
    const has = (name: string) => skills.some((skill) => skill.toLowerCase() === name.toLowerCase());

    const add = () => {
        const value = draft.trim().replace(/\s+/g, ' ');
        if (value.length >= 2 && !has(value) && skills.length < 30) {
            onChange([...skills, value]);
        }
        setDraft('');
    };

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            add();
        }
    };

    return (
        <div className="grid gap-2">
            {skills.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {skills.map((skill) => (
                        <Badge key={skill} variant="secondary" className="gap-1 py-1 pr-1 pl-2.5 text-sm font-normal">
                            {skill}
                            <button
                                type="button"
                                onClick={() => onChange(skills.filter((s) => s !== skill))}
                                className="hover:bg-muted-foreground/20 rounded-full p-0.5"
                                aria-label={`Remove ${skill}`}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
            <div className="flex gap-2">
                <Input
                    list="opportunity-skill-suggestions"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Type a skill and press Enter"
                    aria-label="Add a skill"
                    maxLength={100}
                />
                <datalist id="opportunity-skill-suggestions">
                    {suggestions
                        .filter((name) => !has(name))
                        .map((name) => (
                            <option key={name} value={name} />
                        ))}
                </datalist>
                <Button type="button" variant="outline" onClick={add} disabled={draft.trim().length < 2}>
                    <Plus className="h-4 w-4" /> Add
                </Button>
            </div>
        </div>
    );
}
