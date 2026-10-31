import { Transition } from '@headlessui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

interface Topic {
    value: string;
    label: string;
    email: boolean;
    sms: boolean;
}

interface NotificationSettingsProps {
    topics: Topic[];
    smsConsent: boolean;
    emailVerified: boolean;
    mobile: { masked: string | null; verified: boolean };
}

type Form = {
    preferences: Record<string, { email: boolean; sms: boolean }>;
    sms_consent: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notification settings', href: '/settings/notifications' }];

export default function NotificationSettings({ topics, smsConsent, emailVerified, mobile }: NotificationSettingsProps) {
    const form = useForm<Form>({
        preferences: Object.fromEntries(topics.map((t) => [t.value, { email: t.email, sms: t.sms }])),
        sms_consent: smsConsent,
    });
    const smsUsable = form.data.sms_consent && mobile.verified;

    const toggle = (topic: string, channel: 'email' | 'sms', value: boolean) =>
        form.setData('preferences', { ...form.data.preferences, [topic]: { ...form.data.preferences[topic], [channel]: value } });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('notification-preferences.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="SMS notifications" description="Text messages to your verified mobile number." />
                        <label className="flex items-start gap-3 rounded-lg border p-4 text-sm">
                            <Checkbox
                                checked={form.data.sms_consent}
                                onCheckedChange={(checked) => form.setData('sms_consent', checked === true)}
                                className="mt-0.5"
                            />
                            <span className="grid gap-1">
                                <span className="font-medium">I agree to receive SMS from JoBauko</span>
                                <span className="text-muted-foreground">
                                    Your mobile number is used only to send the notifications you choose below, under the{' '}
                                    <Link href={route('privacy')} className="text-primary hover:underline">
                                        privacy notice
                                    </Link>
                                    . You can withdraw this consent at any time by unticking this box.
                                </span>
                            </span>
                        </label>
                        {!mobile.verified && (
                            <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                {mobile.masked ? `Your mobile number ${mobile.masked} is not verified yet.` : 'You have not added a mobile number.'}{' '}
                                SMS are only sent to verified numbers.{' '}
                                <Link href={route(mobile.masked ? 'mobile.verification.notice' : 'profile.edit')} className="font-medium underline">
                                    {mobile.masked ? 'Verify it now' : 'Add one'}
                                </Link>
                            </p>
                        )}
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall
                            title="What to notify me about"
                            description="Everything also appears under the bell in JoBauko. Choose where else you want to hear about each topic."
                        />
                        {!emailVerified && <p className="text-muted-foreground text-sm">Verify your email address to receive email notifications.</p>}
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-2.5 font-medium">Topic</th>
                                        <th className="w-20 px-4 py-2.5 text-center font-medium">Email</th>
                                        <th className="w-20 px-4 py-2.5 text-center font-medium">SMS</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {topics.map((topic) => (
                                        <tr key={topic.value}>
                                            <td className="px-4 py-2.5">{topic.label}</td>
                                            <td className="px-4 py-2.5 text-center">
                                                <Checkbox
                                                    aria-label={`Email for ${topic.label}`}
                                                    checked={form.data.preferences[topic.value].email}
                                                    onCheckedChange={(checked) => toggle(topic.value, 'email', checked === true)}
                                                />
                                            </td>
                                            <td className="px-4 py-2.5 text-center">
                                                <Checkbox
                                                    aria-label={`SMS for ${topic.label}`}
                                                    checked={smsUsable && form.data.preferences[topic.value].sms}
                                                    disabled={!smsUsable}
                                                    onCheckedChange={(checked) => toggle(topic.value, 'sms', checked === true)}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <InputError message={form.errors.preferences} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={form.processing}>Save</Button>
                        <Transition
                            show={form.recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-neutral-600">Saved</p>
                        </Transition>
                    </div>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
