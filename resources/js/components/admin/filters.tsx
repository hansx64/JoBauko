import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const ALL = 'all';

type FilterValue = string | number | null | undefined;

/**
 * Reload the page with the given filters, leaving empty ones out of the URL.
 */
export function visitWithFilters(url: string, filters: Record<string, FilterValue>) {
    router.get(
        url,
        Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== undefined && value !== '' && value !== ALL)),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export function FilterSelect({
    id,
    label,
    value,
    allLabel,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | number | null;
    allLabel: string;
    options: { value: string | number; label: string }[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value === null ? ALL : String(value)} onValueChange={(next) => onChange(next === ALL ? null : next)}>
                <SelectTrigger id={id}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{allLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={String(option.value)}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export function SearchBox({
    id,
    label,
    placeholder,
    initial,
    onSearch,
}: {
    id: string;
    label: string;
    placeholder: string;
    initial: string;
    onSearch: (q: string) => void;
}) {
    const [q, setQ] = useState(initial);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        onSearch(q.trim());
    };

    return (
        <form onSubmit={submit} className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex gap-2">
                <Input id={id} value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} />
                <Button type="submit" variant="outline" size="icon" aria-label="Search">
                    <Search className="h-4 w-4" />
                </Button>
            </div>
        </form>
    );
}
