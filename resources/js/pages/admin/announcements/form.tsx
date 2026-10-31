import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type Barangay, type BreadcrumbItem, type Option } from '@/types';

type AnnouncementForm = {
    title: string;
    body: string;
    audience: { account_types: string[]; classifications: string[]; barangays: number[] };
    channels: string[];
};

interface AnnouncementFormProps {
    announcement: (AnnouncementForm & { id: number }) | null;
    options: { accountTypes: Option[]; classifications: Option[]; barangays: Barangay[] };
}

const accountTypeLabels: Record<string, string> = {
    seeker: 'Opportunity seekers',
    organization: 'Organization personnel',
    admin: 'System administrators',
};

const toggled = <T,>(list: T[], value: T, on: boolean) => (on ? [...list, value] : list.filter((item) => item !== value));

export default function AnnouncementFormPage({ announcement, options }: AnnouncementFormProps) {
    const isNew = announcement === null;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Announcements', href: '/admin/announcements' },
        {
            title: isNew ? 'New announcement' : 'Edit draft',
            href: isNew ? '/admin/announcements/create' : `/admin/announcements/${announcement.id}/edit`,
        },
    ];
    const form = useForm<AnnouncementForm>({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        audience: announcement?.audience ?? { account_types: ['seeker'], classifications: [], barangays: [] },
        channels: announcement?.channels ?? ['email'],
    });
    const { audience } = form.data;
    const includesSeekers = audience.account_types.includes('seeker');

    const setAudience = (changes: Partial<AnnouncementForm['audience']>) => form.setData('audience', { ...audience, ...changes });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isNew) {
            form.post(route('admin.announcements.store'));
        } else {
            form.put(route('admin.announcements.update', announcement.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isNew ? 'New announcement' : 'Edit announcement'} />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{isNew ? 'New announcement' : 'Edit draft'}</h1>
                    <p className="text-muted-foreground">Saving keeps it as a draft. You will see how many people it reaches before sending.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Message</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                maxLength={150}
                                onChange={(e) => form.setData('title', e.target.value)}
                                required
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="body">Message</Label>
                            <Textarea
                                id="body"
                                rows={7}
                                value={form.data.body}
                                maxLength={2000}
                                onChange={(e) => form.setData('body', e.target.value)}
                                required
                            />
                            <p className="text-muted-foreground text-xs">
                                {form.data.body.length}/2000. By SMS the title and message are shortened to fit two text messages.
                            </p>
                            <InputError message={form.errors.body} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recipients</CardTitle>
                        <CardDescription>Only active accounts with a verified email address receive announcements.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Group label="Send to">
                            {options.accountTypes.map((type) => (
                                <Check
                                    key={type.value}
                                    label={accountTypeLabels[type.value] ?? type.label}
                                    checked={audience.account_types.includes(type.value)}
                                    onChange={(on) => setAudience({ account_types: toggled(audience.account_types, type.value, on) })}
                                />
                            ))}
                            <InputError message={(form.errors as Record<string, string>)['audience.account_types']} />
                        </Group>

                        {includesSeekers && (
                            <>
                                <Group label="Seekers' classification" hint="Leave all unticked to include every classification.">
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {options.classifications.map((c) => (
                                            <Check
                                                key={c.value}
                                                label={c.label}
                                                checked={audience.classifications.includes(c.value)}
                                                onChange={(on) => setAudience({ classifications: toggled(audience.classifications, c.value, on) })}
                                            />
                                        ))}
                                    </div>
                                </Group>
                                <Group label="Seekers' barangay" hint="Leave all unticked to include all 22 barangays.">
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {options.barangays.map((b) => (
                                            <Check
                                                key={b.id}
                                                label={b.name}
                                                checked={audience.barangays.includes(b.id)}
                                                onChange={(on) => setAudience({ barangays: toggled(audience.barangays, b.id, on) })}
                                            />
                                        ))}
                                    </div>
                                </Group>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Channels</CardTitle>
                        <CardDescription>
                            Everyone gets it in the app. Seekers only get email or SMS if they chose that for announcements, and SMS also needs their
                            consent and a verified number.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Check label="In-app (always)" checked disabled onChange={() => undefined} />
                        <Check
                            label="Email"
                            checked={form.data.channels.includes('email')}
                            onChange={(on) => form.setData('channels', toggled(form.data.channels, 'email', on))}
                        />
                        <Check
                            label="SMS (seekers only)"
                            checked={form.data.channels.includes('sms')}
                            onChange={(on) => form.setData('channels', toggled(form.data.channels, 'sms', on))}
                        />
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Save draft
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Cancel
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Group({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            {hint && <p className="text-muted-foreground -mt-1 text-xs">{hint}</p>}
            {children}
        </fieldset>
    );
}

function Check({ label, checked, disabled, onChange }: { label: string; checked: boolean; disabled?: boolean; onChange: (on: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={checked} disabled={disabled} onCheckedChange={(value) => onChange(value === true)} />
            {label}
        </label>
    );
}
