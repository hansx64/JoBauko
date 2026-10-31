import { Link, router, useForm } from '@inertiajs/react';
import { Archive, CalendarPlus, CircleSlash, EllipsisVertical, Pencil, RotateCcw, Send, Undo2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface OpportunityActionsProps {
    opportunity: { id: number; title: string; status: string; deadline: string | null };
    showEdit?: boolean;
}

type Action = 'publish' | 'unpublish' | 'close' | 'archive';
type DeadlineAction = 'extend' | 'reopen';

/**
 * Lifecycle actions offered for the opportunity's current status. The
 * server enforces the same rules; this only hides actions that can't apply.
 */
export function OpportunityActions({ opportunity, showEdit = true }: OpportunityActionsProps) {
    const [deadlineAction, setDeadlineAction] = useState<DeadlineAction | null>(null);
    const [confirmArchive, setConfirmArchive] = useState(false);
    const deadlineForm = useForm({ application_deadline: '' });
    const { status } = opportunity;

    const run = (action: Action) => router.post(route(`org.opportunities.${action}`, opportunity.id), {}, { preserveScroll: true });

    const submitDeadline: FormEventHandler = (e) => {
        e.preventDefault();
        if (!deadlineAction) return;
        deadlineForm.post(route(`org.opportunities.${deadlineAction}`, opportunity.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDeadlineAction(null);
                deadlineForm.reset();
            },
        });
    };

    const editable = !['archived', 'suspended'].includes(status);
    const items = [
        status === 'draft' && { key: 'publish', label: 'Publish', icon: Send, onSelect: () => run('publish') },
        status === 'published' && { key: 'extend', label: 'Extend deadline', icon: CalendarPlus, onSelect: () => setDeadlineAction('extend') },
        (status === 'closed' || status === 'expired') && {
            key: 'reopen',
            label: 'Reopen applications',
            icon: RotateCcw,
            onSelect: () => setDeadlineAction('reopen'),
        },
        status === 'published' && { key: 'close', label: 'Close applications', icon: CircleSlash, onSelect: () => run('close') },
        status === 'published' && { key: 'unpublish', label: 'Unpublish (back to draft)', icon: Undo2, onSelect: () => run('unpublish') },
    ].filter(Boolean) as { key: string; label: string; icon: typeof Send; onSelect: () => void }[];

    if (!editable) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label={`Actions for ${opportunity.title}`}>
                        <EllipsisVertical className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    {showEdit && (
                        <DropdownMenuItem asChild>
                            <Link href={route('org.opportunities.edit', opportunity.id)}>
                                <Pencil className="h-4 w-4" /> Edit
                            </Link>
                        </DropdownMenuItem>
                    )}
                    {items.map((item) => (
                        <DropdownMenuItem key={item.key} onSelect={item.onSelect}>
                            <item.icon className="h-4 w-4" /> {item.label}
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={() => setConfirmArchive(true)} className="text-destructive">
                        <Archive className="h-4 w-4" /> Archive
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={deadlineAction !== null} onOpenChange={(open) => !open && setDeadlineAction(null)}>
                <DialogContent>
                    <form onSubmit={submitDeadline} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>{deadlineAction === 'extend' ? 'Extend the deadline' : 'Reopen applications'}</DialogTitle>
                            <DialogDescription>
                                {opportunity.title}
                                {opportunity.deadline && ` · current deadline ${opportunity.deadline}`}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor={`deadline-${opportunity.id}`}>New application deadline</Label>
                            <Input
                                id={`deadline-${opportunity.id}`}
                                type="date"
                                required
                                value={deadlineForm.data.application_deadline}
                                onChange={(e) => deadlineForm.setData('application_deadline', e.target.value)}
                            />
                            <InputError message={deadlineForm.errors.application_deadline} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="secondary" onClick={() => setDeadlineAction(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={deadlineForm.processing}>
                                {deadlineAction === 'extend' ? 'Extend' : 'Reopen'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmArchive} onOpenChange={setConfirmArchive}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive this opportunity?</DialogTitle>
                        <DialogDescription>
                            “{opportunity.title}” will be removed from public listings and can no longer be edited. Applications already received are
                            kept.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setConfirmArchive(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                setConfirmArchive(false);
                                run('archive');
                            }}
                        >
                            Archive
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
