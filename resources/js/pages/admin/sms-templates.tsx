import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface Template {
    key: string;
    label: string;
    description: string;
    placeholders: string[];
    default_body: string;
    body: string;
    is_active: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SMS templates', href: '/admin/sms-templates' }];

/**
 * Standard SMS fit 160 characters, or 153 each when a message is split.
 * Placeholders are filled in when sending, so the final length varies.
 */
function segments(length: number) {
    return length <= 160 ? 1 : Math.ceil(length / 153);
}

export default function SmsTemplates({ templates, maxLength }: { templates: Template[]; maxLength: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SMS templates" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">SMS templates</h1>
                    <p className="text-muted-foreground">
                        The wording of each text message. Placeholders in braces are filled in when sending. Messages are cut to {maxLength}{' '}
                        characters (two SMS).
                    </p>
                </div>
                {templates.map((template) => (
                    <TemplateCard key={template.key} template={template} maxLength={maxLength} />
                ))}
            </div>
        </AppLayout>
    );
}

function TemplateCard({ template, maxLength }: { template: Template; maxLength: number }) {
    const form = useForm({ body: template.body, is_active: template.is_active });
    const id = `template-${template.key}`;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.sms-templates.update', template.key), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{template.label}</CardTitle>
                <CardDescription>{template.description}</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor={id}>Message</Label>
                        <Textarea
                            id={id}
                            rows={3}
                            maxLength={maxLength}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span>
                                Placeholders:{' '}
                                {template.placeholders.map((p) => (
                                    <code key={p} className="bg-muted mr-1 rounded px-1 py-0.5">{`{${p}}`}</code>
                                ))}
                            </span>
                            <span className="tabular-nums">
                                {form.data.body.length} characters before placeholders · about {segments(form.data.body.length)} SMS
                            </span>
                        </div>
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
                            Send this message
                        </label>
                        <div className="flex gap-2">
                            {form.data.body !== template.default_body && (
                                <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('body', template.default_body)}>
                                    Restore default
                                </Button>
                            )}
                            <Button type="submit" size="sm" disabled={form.processing || !form.isDirty}>
                                Save
                            </Button>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
