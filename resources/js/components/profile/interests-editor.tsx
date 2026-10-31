import { useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, X } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Option } from '@/types';
import { type Interest } from '@/types/profile';

interface InterestsEditorProps {
    interests: Interest[];
    categories: Option[];
}

export function InterestsEditor({ interests, categories }: InterestsEditorProps) {
    const form = useForm<{ interests: { category: string; field: string }[] }>({
        interests: interests.map((i) => ({ category: i.category, field: i.field ?? '' })),
    });

    const update = (index: number, key: 'category' | 'field', value: string) =>
        form.setData(
            'interests',
            form.data.interests.map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('seeker.profile.interests.update'), { preserveScroll: true });
    };

    const errorFor = (index: number, key: string) => (form.errors as Record<string, string>)[`interests.${index}.${key}`];

    return (
        <Card id="interests" className="scroll-mt-20">
            <CardHeader>
                <CardTitle className="text-base">Areas of interest</CardTitle>
                <CardDescription>What kinds of opportunities are you looking for, and in which field?</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-3">
                    {form.data.interests.length === 0 && <p className="text-muted-foreground text-sm">No interests added yet.</p>}

                    {form.data.interests.map((row, index) => (
                        <div key={index} className="grid gap-2 sm:grid-cols-[12rem_1fr_auto]">
                            <div>
                                <Select value={row.category} onValueChange={(value) => update(index, 'category', value)}>
                                    <SelectTrigger aria-label="Opportunity type">
                                        <SelectValue placeholder="Opportunity type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errorFor(index, 'category')} />
                            </div>
                            <div>
                                <Input
                                    value={row.field}
                                    onChange={(e) => update(index, 'field', e.target.value)}
                                    placeholder="Field, e.g. Information Technology or Agriculture (optional)"
                                    aria-label="Field"
                                    maxLength={100}
                                />
                                <InputError message={errorFor(index, 'field')} />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Remove interest"
                                onClick={() =>
                                    form.setData(
                                        'interests',
                                        form.data.interests.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}

                    <div className="flex flex-wrap gap-2 pt-1">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => form.setData('interests', [...form.data.interests, { category: '', field: '' }])}
                            disabled={form.data.interests.length >= 20}
                        >
                            <Plus className="h-4 w-4" /> Add interest
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.isDirty}>
                            {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Save interests
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
