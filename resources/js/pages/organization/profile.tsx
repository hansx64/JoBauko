import { Head, useForm } from '@inertiajs/react';
import { Download, FileText, LoaderCircle } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { VerificationBanner } from '@/components/verification-banner';
import AppLayout from '@/layouts/app-layout';
import { type Barangay, type BreadcrumbItem, type Option } from '@/types';
import { formatDate, type OrganizationSummary } from '@/types/organization';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Organization profile', href: '/org/profile' }];
const OUTSIDE_BAUKO = 'outside';

interface OrganizationProfileProps {
    organization: OrganizationSummary & {
        id: number;
        type: string;
        official_email: string;
        contact_number: string;
        office_address: string;
        barangay_id: number | null;
        description: string | null;
        representative_name: string;
        representative_position: string | null;
    };
    documents: { id: number; original_name: string; size_bytes: number; uploaded_at: string }[];
    canEdit: boolean;
    organizationTypes: Option[];
    barangays: Barangay[];
}

type ProfileForm = {
    name: string;
    type: string;
    official_email: string;
    contact_number: string;
    office_address: string;
    barangay_id: string;
    description: string;
    representative_name: string;
    representative_position: string;
    logo: File | null;
    documents: File[];
};

export default function OrganizationProfile({ organization, documents, canEdit, organizationTypes, barangays }: OrganizationProfileProps) {
    const form = useForm<ProfileForm>({
        name: organization.name,
        type: organization.type,
        official_email: organization.official_email,
        contact_number: organization.contact_number,
        office_address: organization.office_address,
        barangay_id: organization.barangay_id ? String(organization.barangay_id) : '',
        description: organization.description ?? '',
        representative_name: organization.representative_name,
        representative_position: organization.representative_position ?? '',
        logo: null,
        documents: [],
    });
    const errors = form.errors as Record<string, string>;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('org.profile.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData((data) => ({ ...data, logo: null, documents: [] })),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization profile" />
            <form onSubmit={submit} className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">Organization profile</h1>
                    <StatusBadge status={organization.verification_status} label={organization.verification_label} />
                </div>

                <VerificationBanner organization={organization} />

                <fieldset disabled={!canEdit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                            <CardDescription>
                                {canEdit ? 'Shown to applicants on your opportunities.' : 'Only organization administrators can edit these details.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label="Organization name" error={errors.name} className="sm:col-span-2">
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            </Field>
                            <Field label="Type" error={errors.type}>
                                <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)} disabled={!canEdit}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {organizationTypes.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Official email" error={errors.official_email}>
                                <Input
                                    type="email"
                                    value={form.data.official_email}
                                    onChange={(e) => form.setData('official_email', e.target.value)}
                                />
                            </Field>
                            <Field label="Contact number" error={errors.contact_number}>
                                <Input value={form.data.contact_number} onChange={(e) => form.setData('contact_number', e.target.value)} />
                            </Field>
                            <Field label="Barangay" error={errors.barangay_id}>
                                <Select
                                    value={form.data.barangay_id || OUTSIDE_BAUKO}
                                    onValueChange={(v) => form.setData('barangay_id', v === OUTSIDE_BAUKO ? '' : v)}
                                    disabled={!canEdit}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={OUTSIDE_BAUKO}>Outside Bauko</SelectItem>
                                        {barangays.map((b) => (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Office address" error={errors.office_address} className="sm:col-span-2">
                                <Input value={form.data.office_address} onChange={(e) => form.setData('office_address', e.target.value)} />
                            </Field>
                            <Field label="About the organization" error={errors.description} className="sm:col-span-2">
                                <Textarea rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </Field>
                            <Field label="Authorized representative" error={errors.representative_name}>
                                <Input value={form.data.representative_name} onChange={(e) => form.setData('representative_name', e.target.value)} />
                            </Field>
                            <Field label="Representative's position" error={errors.representative_position}>
                                <Input
                                    value={form.data.representative_position}
                                    onChange={(e) => form.setData('representative_position', e.target.value)}
                                />
                            </Field>
                            <Field label="Replace logo (JPG/PNG/WebP, max 2 MB)" error={errors.logo} className="sm:col-span-2">
                                <div className="flex items-center gap-3">
                                    {organization.logo_url && (
                                        <img src={organization.logo_url} alt="" className="size-12 rounded-lg border object-cover" />
                                    )}
                                    <Input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)}
                                    />
                                </div>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Verification documents</CardTitle>
                            <CardDescription>Visible only to your organization and the JoBauko administrator.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
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
                                            <a
                                                href={route('organization-documents.show', d.id)}
                                                className="text-muted-foreground hover:text-foreground shrink-0"
                                                aria-label={`Download ${d.original_name}`}
                                            >
                                                <Download className="h-4 w-4" />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {canEdit && (
                                <Field label="Add documents (PDF/JPG/PNG, up to 5 files of 5 MB)" error={errors.documents}>
                                    <Input
                                        type="file"
                                        multiple
                                        accept="application/pdf,image/png,image/jpeg"
                                        onChange={(e) => form.setData('documents', Array.from(e.target.files ?? []).slice(0, 5))}
                                    />
                                    {Object.entries(errors)
                                        .filter(([key]) => key.startsWith('documents.'))
                                        .map(([key, message]) => (
                                            <InputError key={key} message={message} />
                                        ))}
                                </Field>
                            )}
                        </CardContent>
                    </Card>

                    {canEdit && (
                        <div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                {organization.verification_status === 'rejected' ? 'Save and resubmit for verification' : 'Save changes'}
                            </Button>
                        </div>
                    )}
                </fieldset>
            </form>
        </AppLayout>
    );
}

function Field({ label, error, className, children }: { label: string; error?: string; className?: string; children: ReactNode }) {
    return (
        <div className={`grid gap-2 ${className ?? ''}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
