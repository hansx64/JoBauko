import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * Confirms an administrator action that must explain itself, such as a
 * suspension or deactivation. The reason is posted as `reason`.
 */
export function ReasonDialog({
    open,
    onOpenChange,
    action,
    title,
    description,
    label = 'Reason',
    submitLabel,
    maxLength = 1000,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: string;
    title: string;
    description: string;
    label?: string;
    submitLabel: string;
    maxLength?: number;
}) {
    const form = useForm({ reason: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="reason">{label}</Label>
                        <Textarea
                            id="reason"
                            rows={4}
                            maxLength={maxLength}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            required
                            minLength={10}
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
