import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface AcceptInvitationProps {
    name: string;
    email: string;
    organization: string;
    role: string;
    action: string;
}

export default function AcceptInvitation({ name, email, organization, role, action }: AcceptInvitationProps) {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '', password_confirmation: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(action, { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title={`Join ${organization}`} description={`Hello ${name}, you were added as ${role}. Set a password to activate your account.`}>
            <Head title="Accept invitation" />

            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={email} readOnly autoComplete="username" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        autoFocus
                        required
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Activate my account
                </Button>
            </form>
        </AuthLayout>
    );
}
