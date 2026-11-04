import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, FileCheck2, LoaderCircle, UserRoundCheck } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { PasswordInput } from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/public-layout';
import { type Barangay, type Option } from '@/types';

type RegisterOrganizationForm = {
    name: string;
    type: string;
    official_email: string;
    contact_number: string;
    office_address: string;
    barangay_id: string;
    description: string;
    logo: File | null;
    documents: File[];
    representative_name: string;
    representative_position: string;
    email: string;
    mobile_number: string;
    password: string;
    password_confirmation: string;
    privacy_consent: boolean;
};

const OUTSIDE_BAUKO = 'outside';

export default function RegisterOrganization({ organizationTypes, barangays }: { organizationTypes: Option[]; barangays: Barangay[] }) {
    const { data, setData, post, processing, errors, progress } = useForm<RegisterOrganizationForm>({
        name: '',
        type: '',
        official_email: '',
        contact_number: '',
        office_address: '',
        barangay_id: '',
        description: '',
        logo: null,
        documents: [],
        representative_name: '',
        representative_position: '',
        email: '',
        mobile_number: '',
        password: '',
        password_confirmation: '',
        privacy_consent: false,
    });

    const allErrors = errors as Record<string, string>;
    const documentErrors = Object.entries(allErrors)
        .filter(([key]) => key.startsWith('documents.'))
        .map(([, message]) => message);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('organizations.register'), { forceFormData: true });
    };

    return (
        <PublicLayout>
            <Head title="Register an organization" />
            <div className="mx-auto max-w-3xl px-4 py-10">
                <div className="mb-8 space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Register your organization</h1>
                    <p className="text-muted-foreground">
                        Companies, LGUs, government agencies, schools and NGOs can post jobs, scholarships, livelihood programs and trainings for the
                        people of Bauko. Every organization is verified by the JoBauko administrator before its opportunities are published.
                    </p>
                </div>

                <form onSubmit={submit} className="grid gap-6">
                    <Section icon={<Building2 className="h-5 w-5" />} title="Organization details" description="Shown on your public profile.">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Organization name" error={errors.name} className="sm:col-span-2">
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </Field>
                            <Field label="Organization type" error={errors.type}>
                                <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
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
                                    value={data.official_email}
                                    onChange={(e) => setData('official_email', e.target.value)}
                                    required
                                />
                            </Field>
                            <Field label="Contact number" error={errors.contact_number}>
                                <Input type="tel" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} required />
                            </Field>
                            <Field label="Barangay (if the office is in Bauko)" error={errors.barangay_id}>
                                <Select
                                    value={data.barangay_id || OUTSIDE_BAUKO}
                                    onValueChange={(v) => setData('barangay_id', v === OUTSIDE_BAUKO ? '' : v)}
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
                                <Input value={data.office_address} onChange={(e) => setData('office_address', e.target.value)} required />
                            </Field>
                            <Field label="About the organization (optional)" error={errors.description} className="sm:col-span-2">
                                <Textarea rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </Field>
                            <Field label="Logo (optional, JPG/PNG/WebP, max 2 MB)" error={errors.logo} className="sm:col-span-2">
                                <Input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section
                        icon={<FileCheck2 className="h-5 w-5" />}
                        title="Verification documents"
                        description="Upload proof that your organization is legitimate, such as a business or mayor's permit, SEC/DTI/CDA registration, or an office order. PDF, JPG or PNG, up to 5 files of 5 MB each. Only JoBauko administrators can see these."
                    >
                        <Input
                            type="file"
                            multiple
                            accept="application/pdf,image/png,image/jpeg"
                            onChange={(e) => setData('documents', Array.from(e.target.files ?? []).slice(0, 5))}
                            aria-label="Verification documents"
                        />
                        {data.documents.length > 0 && (
                            <ul className="text-muted-foreground mt-2 list-disc pl-5 text-sm">
                                {data.documents.map((file) => (
                                    <li key={file.name}>{file.name}</li>
                                ))}
                            </ul>
                        )}
                        <InputError message={errors.documents} />
                        {documentErrors.map((message) => (
                            <InputError key={message} message={message} />
                        ))}
                    </Section>

                    <Section
                        icon={<UserRoundCheck className="h-5 w-5" />}
                        title="Authorized representative"
                        description="This person becomes the organization administrator and can add other personnel later."
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Full name" error={errors.representative_name}>
                                <Input
                                    value={data.representative_name}
                                    onChange={(e) => setData('representative_name', e.target.value)}
                                    autoComplete="name"
                                    required
                                />
                            </Field>
                            <Field label="Position (optional)" error={errors.representative_position}>
                                <Input value={data.representative_position} onChange={(e) => setData('representative_position', e.target.value)} />
                            </Field>
                            <Field label="Login email" error={errors.email}>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="email"
                                    required
                                />
                            </Field>
                            <Field label="Mobile number (optional)" error={errors.mobile_number}>
                                <Input
                                    type="tel"
                                    value={data.mobile_number}
                                    onChange={(e) => setData('mobile_number', e.target.value)}
                                    placeholder="0917 123 4567"
                                />
                            </Field>
                            <Field label="Password" error={errors.password}>
                                <PasswordInput
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    required
                                />
                            </Field>
                            <Field label="Confirm password" error={errors.password_confirmation}>
                                <PasswordInput
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                    required
                                />
                            </Field>
                        </div>
                    </Section>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="privacy_consent"
                            checked={data.privacy_consent}
                            onCheckedChange={(checked) => setData('privacy_consent', checked === true)}
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="privacy_consent" className="leading-snug">
                                I agree to the{' '}
                                <a href={route('privacy')} target="_blank" rel="noreferrer" className="underline underline-offset-4">
                                    Privacy Notice
                                </a>{' '}
                                and confirm I am authorized to register this organization. Applicant data we receive will only be used to evaluate
                                applications.
                            </Label>
                            <InputError message={errors.privacy_consent} />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-4">
                        <Button type="submit" size="lg" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Submit for verification
                        </Button>
                        {progress && <span className="text-muted-foreground text-sm">Uploading… {progress.percentage}%</span>}
                        <span className="text-muted-foreground text-sm">
                            Already registered?{' '}
                            <Link href={route('login')} className="underline underline-offset-4">
                                Log in
                            </Link>
                        </span>
                    </div>
                </form>
            </div>
        </PublicLayout>
    );
}

function Section({ icon, title, description, children }: { icon: ReactNode; title: string; description: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    {icon}
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
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
