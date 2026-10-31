import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, MailPlus, UserPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { formatDate, type OrganizationSummary } from '@/types/organization';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    is_you: boolean;
    invitation_pending: boolean;
    last_login_at: string | null;
    joined_at: string | null;
}

interface Role {
    value: string;
    label: string;
    permissions: string[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Personnel', href: '/org/personnel' }];

export default function Personnel({ organization, members, roles }: { organization: OrganizationSummary; members: Member[]; roles: Role[] }) {
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState<Member | null>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Personnel" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Personnel</h1>
                        <p className="text-muted-foreground">People who manage opportunities and applications for {organization.name}.</p>
                    </div>
                    <Button onClick={() => setInviting(true)}>
                        <UserPlus className="h-4 w-4" /> Add personnel
                    </Button>
                </div>

                <InputError message={errors.member} />

                <Card className="overflow-hidden py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Name</th>
                                    <th className="px-4 py-3 font-medium">Role</th>
                                    <th className="px-4 py-3 font-medium">Access</th>
                                    <th className="px-4 py-3 font-medium">Last sign-in</th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {members.map((member) => (
                                    <tr key={member.id} className={cn(!member.is_active && 'text-muted-foreground')}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {member.name} {member.is_you && <span className="text-muted-foreground font-normal">(you)</span>}
                                            </p>
                                            <p className="text-muted-foreground text-xs">{member.email}</p>
                                        </td>
                                        <td className="px-4 py-3">{member.role_label}</td>
                                        <td className="px-4 py-3">
                                            {!member.is_active ? (
                                                <Badge variant="outline">Deactivated</Badge>
                                            ) : member.invitation_pending ? (
                                                <Badge variant="secondary">Invitation sent</Badge>
                                            ) : (
                                                <Badge variant="secondary" className="bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200">
                                                    Active
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {member.last_login_at ? formatDate(member.last_login_at) : 'Never'}
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            {member.invitation_pending && member.is_active && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(route('org.personnel.resend', member.id), {}, { preserveScroll: true })
                                                    }
                                                >
                                                    <MailPlus className="h-4 w-4" /> Resend
                                                </Button>
                                            )}
                                            {!member.is_you && (
                                                <Button variant="ghost" size="sm" onClick={() => setEditing(member)}>
                                                    Edit
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">What each role can do</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {roles.map((role) => (
                            <Card key={role.value}>
                                <CardHeader>
                                    <CardTitle className="text-base">{role.label}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="grid gap-1.5 text-sm">
                                        {role.permissions.map((permission) => (
                                            <li key={permission} className="flex gap-2">
                                                <Check className="mt-0.5 h-4 w-4 shrink-0 text-teal-600" /> {permission}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Deactivated personnel can no longer sign in to your organization. Their past actions stay in the history.
                    </p>
                </section>
            </div>

            <InviteDialog open={inviting} onOpenChange={setInviting} roles={roles} />
            {editing && <EditDialog member={editing} roles={roles} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function RoleSelect({ id, value, roles, onChange }: { id: string; value: string; roles: Role[]; onChange: (value: string) => void }) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id}>
                <SelectValue placeholder="Choose a role" />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {role.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function InviteDialog({ open, onOpenChange, roles }: { open: boolean; onOpenChange: (open: boolean) => void; roles: Role[] }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ name: '', email: '', role: 'reviewer' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('org.personnel.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) clearErrors();
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Add personnel</DialogTitle>
                        <DialogDescription>We will email them a link to set their password. The link is valid for 3 days.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="invite-name">Full name</Label>
                        <Input id="invite-name" required value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="invite-email">Work email</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            required
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value.toLowerCase())}
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="invite-role">Role</Label>
                        <RoleSelect id="invite-role" value={data.role} roles={roles} onChange={(value) => setData('role', value)} />
                        <InputError message={errors.role} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Send invitation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({ member, roles, onClose }: { member: Member; roles: Role[]; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({ role: member.role, is_active: member.is_active });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('org.personnel.update', member.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{member.name}</DialogTitle>
                        <DialogDescription>{member.email}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-role">Role</Label>
                        <RoleSelect id="edit-role" value={data.role} roles={roles} onChange={(value) => setData('role', value)} />
                        <InputError message={errors.role} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Access</Label>
                        <div className="grid grid-cols-2 gap-2">
                            {[
                                [true, 'Active'],
                                [false, 'Deactivated'],
                            ].map(([value, label]) => (
                                <Button
                                    key={String(value)}
                                    type="button"
                                    variant={data.is_active === value ? 'default' : 'outline'}
                                    aria-pressed={data.is_active === value}
                                    onClick={() => setData('is_active', value as boolean)}
                                >
                                    {label as string}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.is_active ?? (errors as Record<string, string>).member} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
