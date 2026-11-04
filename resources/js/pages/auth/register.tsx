import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { PasswordInput } from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AuthLayout from '@/layouts/auth-layout';
import { type Barangay, type Option } from '@/types';

type RegisterForm = {
    name: string;
    email: string;
    mobile_number: string;
    barangay_id: string;
    classification: string;
    password: string;
    password_confirmation: string;
    privacy_consent: boolean;
    sms_consent: boolean;
};

interface RegisterProps {
    barangays: Barangay[];
    classifications: Option[];
    classificationTopics: Record<string, string[]>;
}

export default function Register({ barangays, classifications, classificationTopics }: RegisterProps) {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        mobile_number: '',
        barangay_id: '',
        classification: '',
        password: '',
        password_confirmation: '',
        privacy_consent: false,
        sms_consent: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const suggestedTopics = data.classification ? (classificationTopics[data.classification] ?? []) : [];

    return (
        <AuthLayout title="Create an account" description="Find jobs, scholarships, livelihood programs and trainings in Bauko">
            <Head title="Register" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder="Juan Dela Cruz"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="mobile_number">Mobile number</Label>
                        <Input
                            id="mobile_number"
                            type="tel"
                            inputMode="tel"
                            required
                            autoComplete="tel"
                            value={data.mobile_number}
                            onChange={(e) => setData('mobile_number', e.target.value)}
                            disabled={processing}
                            placeholder="0917 123 4567"
                        />
                        <InputError message={errors.mobile_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="barangay_id">Barangay</Label>
                        <Select value={data.barangay_id} onValueChange={(value) => setData('barangay_id', value)} disabled={processing}>
                            <SelectTrigger id="barangay_id">
                                <SelectValue placeholder="Select your barangay" />
                            </SelectTrigger>
                            <SelectContent>
                                {barangays.map((barangay) => (
                                    <SelectItem key={barangay.id} value={String(barangay.id)}>
                                        {barangay.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">Bauko, Mountain Province</p>
                        <InputError message={errors.barangay_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="classification">Current status</Label>
                        <Select value={data.classification} onValueChange={(value) => setData('classification', value)} disabled={processing}>
                            <SelectTrigger id="classification">
                                <SelectValue placeholder="Select what best describes you" />
                            </SelectTrigger>
                            <SelectContent>
                                {classifications.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">
                            You can change this any time.
                            {suggestedTopics.length > 0 && <> We'll suggest notifications for: {suggestedTopics.join(', ')}.</>}
                        </p>
                        <InputError message={errors.classification} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            strength
                            id="password"
                            required
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            disabled={processing}
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm password</Label>
                        <PasswordInput
                            mustMatch={data.password}
                            id="password_confirmation"
                            required
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            disabled={processing}
                            placeholder="Confirm password"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="grid gap-4 rounded-lg border p-4">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="privacy_consent"
                                checked={data.privacy_consent}
                                onCheckedChange={(checked) => setData('privacy_consent', checked === true)}
                                disabled={processing}
                                className="mt-0.5"
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="privacy_consent" className="leading-snug">
                                    I have read and agree to the{' '}
                                    <a href={route('privacy')} target="_blank" rel="noreferrer" className="underline underline-offset-4">
                                        Privacy Notice
                                    </a>
                                    .
                                </Label>
                                <p className="text-muted-foreground text-xs">Required. Explains how your personal data is used and protected.</p>
                                <InputError message={errors.privacy_consent} />
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="sms_consent"
                                checked={data.sms_consent}
                                onCheckedChange={(checked) => setData('sms_consent', checked === true)}
                                disabled={processing}
                                className="mt-0.5"
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="sms_consent" className="leading-snug">
                                    Send me SMS notifications.
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    Optional. We'll text you about opportunities and application updates you choose. You can change topics or withdraw
                                    consent at any time.
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Create account
                    </Button>
                </div>

                <div className="text-muted-foreground grid gap-1 text-center text-sm">
                    <span>
                        Already have an account? <TextLink href={route('login')}>Log in</TextLink>
                    </span>
                    <span>
                        Posting opportunities for an organization?{' '}
                        <TextLink href={route('organizations.register')}>Register your organization</TextLink>
                    </span>
                </div>
            </form>
        </AuthLayout>
    );
}
