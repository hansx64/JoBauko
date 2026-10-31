import { Head, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface VerifyMobileProps {
    mobileNumber: string;
    verified: boolean;
    codePending: boolean;
    status?: string;
}

export default function VerifyMobile({ mobileNumber, verified, codePending, status }: VerifyMobileProps) {
    const { errors } = usePage().props;
    const sendForm = useForm({});
    const verifyForm = useForm({ code: '' });

    const sendCode: FormEventHandler = (e) => {
        e.preventDefault();
        sendForm.post(route('mobile.verification.send'), {
            preserveScroll: true,
            onSuccess: () => verifyForm.reset('code'),
        });
    };

    const verify: FormEventHandler = (e) => {
        e.preventDefault();
        verifyForm.post(route('mobile.verification.verify'));
    };

    if (verified) {
        return (
            <AuthLayout title="Mobile number verified" description={`${mobileNumber} is verified.`}>
                <Head title="Mobile verification" />
                <div className="flex flex-col items-center gap-6 text-center">
                    <CircleCheck className="h-10 w-10 text-green-600" />
                    <TextLink href={route('dashboard')}>Go to dashboard</TextLink>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title="Verify your mobile number" description={`We'll send a 6-digit code to ${mobileNumber}.`}>
            <Head title="Mobile verification" />

            {status === 'verification-code-sent' && (
                <div className="mb-2 text-center text-sm font-medium text-green-600">A code has been sent. It expires in 10 minutes.</div>
            )}

            <div className="flex flex-col gap-6">
                {codePending && (
                    <form onSubmit={verify} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Verification code</Label>
                            <Input
                                id="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                autoFocus
                                required
                                value={verifyForm.data.code}
                                onChange={(e) => verifyForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                disabled={verifyForm.processing}
                                placeholder="123456"
                                className="text-center text-lg tracking-[0.5em]"
                            />
                            <InputError message={verifyForm.errors.code} />
                        </div>
                        <Button type="submit" disabled={verifyForm.processing || verifyForm.data.code.length !== 6}>
                            {verifyForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Verify
                        </Button>
                    </form>
                )}

                <form onSubmit={sendCode} className="grid gap-2">
                    <Button type="submit" variant={codePending ? 'outline' : 'default'} disabled={sendForm.processing}>
                        {sendForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {codePending ? 'Send a new code' : 'Send verification code'}
                    </Button>
                    {!codePending && <InputError message={errors.code} />}
                </form>

                <p className="text-muted-foreground text-center text-sm">
                    Wrong number? <TextLink href={route('profile.edit')}>Update it in your profile</TextLink>
                </p>
            </div>
        </AuthLayout>
    );
}
