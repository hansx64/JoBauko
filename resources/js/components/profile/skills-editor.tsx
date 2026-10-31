import { useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, X } from 'lucide-react';
import { FormEventHandler, KeyboardEvent, useState } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface SkillsEditorProps {
    skills: string[];
    suggestions: string[];
}

export function SkillsEditor({ skills, suggestions }: SkillsEditorProps) {
    const form = useForm<{ skills: string[] }>({ skills });
    const [draft, setDraft] = useState('');

    const has = (name: string) => form.data.skills.some((s) => s.toLowerCase() === name.toLowerCase());
    const changed = form.data.skills.join('|') !== skills.join('|');

    const add = (name: string) => {
        const value = name.trim().replace(/\s+/g, ' ');
        if (value.length >= 2 && !has(value)) {
            form.setData('skills', [...form.data.skills, value]);
        }
        setDraft('');
    };

    const remove = (name: string) =>
        form.setData(
            'skills',
            form.data.skills.filter((s) => s !== name),
        );

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            add(draft);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('seeker.profile.skills.update'), { preserveScroll: true });
    };

    const errors = Object.entries(form.errors).map(([, message]) => message);

    return (
        <Card id="skills" className="scroll-mt-20">
            <CardHeader>
                <CardTitle className="text-base">Skills</CardTitle>
                <CardDescription>Technical and soft skills. These are the strongest signal for your recommendations.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="flex flex-wrap gap-2">
                        {form.data.skills.length === 0 && <p className="text-muted-foreground text-sm">No skills added yet.</p>}
                        {form.data.skills.map((skill) => (
                            <Badge key={skill} variant="secondary" className="gap-1 py-1 pr-1 pl-2.5 text-sm font-normal">
                                {skill}
                                <button
                                    type="button"
                                    onClick={() => remove(skill)}
                                    className="hover:bg-muted-foreground/20 rounded-full p-0.5"
                                    aria-label={`Remove ${skill}`}
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            </Badge>
                        ))}
                    </div>

                    <div className="flex gap-2">
                        <Input
                            list="skill-suggestions"
                            value={draft}
                            onChange={(e) => setDraft(e.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Type a skill, e.g. Organic Farming"
                            aria-label="Add a skill"
                            maxLength={100}
                        />
                        <datalist id="skill-suggestions">
                            {suggestions
                                .filter((s) => !has(s))
                                .map((s) => (
                                    <option key={s} value={s} />
                                ))}
                        </datalist>
                        <Button type="button" variant="outline" onClick={() => add(draft)} disabled={draft.trim().length < 2}>
                            <Plus className="h-4 w-4" /> Add
                        </Button>
                    </div>

                    {errors.map((message) => (
                        <InputError key={message} message={message} />
                    ))}

                    <div>
                        <Button type="submit" disabled={form.processing || !changed}>
                            {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Save skills
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
