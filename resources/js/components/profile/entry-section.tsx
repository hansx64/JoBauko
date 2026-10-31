import { useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type Option } from '@/types';

export type FieldValue = string | boolean;

export interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'textarea' | 'date' | 'number' | 'select' | 'checkbox';
    required?: boolean;
    options?: Option[];
    placeholder?: string;
    hint?: string;
}

interface EntrySectionProps<T extends { id: number }> {
    id: string;
    title: string;
    description: string;
    emptyText: string;
    addLabel: string;
    items: T[];
    fields: FieldDef[];
    toForm: (item: T | null) => Record<string, FieldValue>;
    renderItem: (item: T) => ReactNode;
    storeUrl: string;
    itemUrl: (item: T) => string;
}

/**
 * A profile section listing entries (education, experience, ...) with a
 * dialog to add or edit one entry at a time.
 */
export function EntrySection<T extends { id: number }>({
    id,
    title,
    description,
    emptyText,
    addLabel,
    items,
    fields,
    toForm,
    renderItem,
    storeUrl,
    itemUrl,
}: EntrySectionProps<T>) {
    const [editing, setEditing] = useState<T | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm<Record<string, FieldValue>>(toForm(null));
    const deleteForm = useForm({});

    const openDialog = (item: T | null) => {
        setEditing(item);
        form.clearErrors();
        form.setData(toForm(item));
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };

        if (editing) {
            form.patch(itemUrl(editing), options);
        } else {
            form.post(storeUrl, options);
        }
    };

    const remove = (item: T) => {
        deleteForm.delete(itemUrl(item), { preserveScroll: true });
    };

    return (
        <Card id={id} className="scroll-mt-20">
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <Button variant="outline" size="sm" onClick={() => openDialog(null)}>
                    <Plus className="h-4 w-4" /> {addLabel}
                </Button>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{emptyText}</p>
                ) : (
                    <ul className="divide-y">
                        {items.map((item) => (
                            <li key={item.id} className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div className="min-w-0 text-sm">{renderItem(item)}</div>
                                <div className="flex shrink-0 gap-1">
                                    <Button variant="ghost" size="icon" onClick={() => openDialog(item)} aria-label="Edit">
                                        <Pencil className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => remove(item)}
                                        disabled={deleteForm.processing}
                                        aria-label="Remove"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Edit ${title.toLowerCase()}` : addLabel}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        {fields.map((field) => (
                            <FieldInput
                                key={field.name}
                                idPrefix={id}
                                field={field}
                                value={form.data[field.name]}
                                error={form.errors[field.name]}
                                onChange={(value) => form.setData(field.name, value)}
                                disabled={form.processing}
                            />
                        ))}
                        <DialogFooter>
                            <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Save
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

interface FieldInputProps {
    idPrefix: string;
    field: FieldDef;
    value: FieldValue | undefined;
    error?: string;
    onChange: (value: FieldValue) => void;
    disabled: boolean;
}

export function FieldInput({ idPrefix, field, value, error, onChange, disabled }: FieldInputProps) {
    const inputId = `${idPrefix}-${field.name}`;

    if (field.type === 'checkbox') {
        return (
            <div className="flex items-center gap-2">
                <Checkbox id={inputId} checked={value === true} onCheckedChange={(checked) => onChange(checked === true)} disabled={disabled} />
                <Label htmlFor={inputId}>{field.label}</Label>
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={inputId}>
                {field.label}
                {!field.required && <span className="text-muted-foreground font-normal"> (optional)</span>}
            </Label>
            {field.type === 'select' ? (
                <Select value={String(value ?? '')} onValueChange={onChange} disabled={disabled}>
                    <SelectTrigger id={inputId}>
                        <SelectValue placeholder={field.placeholder ?? 'Select…'} />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options?.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : field.type === 'textarea' ? (
                <Textarea
                    id={inputId}
                    value={String(value ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    placeholder={field.placeholder}
                    rows={4}
                />
            ) : (
                <Input
                    id={inputId}
                    type={field.type}
                    value={String(value ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                    required={field.required}
                    disabled={disabled}
                    placeholder={field.placeholder}
                />
            )}
            {field.hint && <p className="text-muted-foreground text-xs">{field.hint}</p>}
            <InputError message={error} />
        </div>
    );
}
