import { useForm } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export interface ReportingOptions {
    alreadyReported: boolean;
    reasons: { value: string; label: string; requires_details: boolean }[];
}

/**
 * Lets signed-in users flag a listing for administrator review.
 */
export function ReportListing({ slug, reporting }: { slug: string; reporting: ReportingOptions }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '', details: '' });
    const reason = reporting.reasons.find((r) => r.value === form.data.reason);

    if (reporting.alreadyReported) {
        return (
            <p className="text-muted-foreground flex items-center gap-2 text-xs">
                <Flag className="h-3.5 w-3.5" /> You reported this listing. An administrator will review it.
            </p>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('opportunities.report', slug), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="text-muted-foreground hover:text-foreground flex items-center gap-2 justify-self-start text-xs"
            >
                <Flag className="h-3.5 w-3.5" /> Report this listing
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>Report this listing</DialogTitle>
                            <DialogDescription>
                                Tell the JoBauko administrators what is wrong. The organization is not told who reported it.
                            </DialogDescription>
                        </DialogHeader>
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">Reason</legend>
                            {reporting.reasons.map((r) => (
                                <label
                                    key={r.value}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm',
                                        form.data.reason === r.value && 'border-primary bg-primary/5',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="reason"
                                        value={r.value}
                                        checked={form.data.reason === r.value}
                                        onChange={() => form.setData('reason', r.value)}
                                        required
                                    />
                                    {r.label}
                                </label>
                            ))}
                            <InputError message={form.errors.reason} />
                        </fieldset>
                        <div className="grid gap-2">
                            <Label htmlFor="report-details">Details{reason?.requires_details ? '' : ' (optional)'}</Label>
                            <Textarea
                                id="report-details"
                                rows={3}
                                maxLength={1000}
                                required={reason?.requires_details}
                                value={form.data.details}
                                onChange={(e) => form.setData('details', e.target.value)}
                                placeholder="For example: they asked me to pay a processing fee before the interview."
                            />
                            <InputError message={form.errors.details} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Send report
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
