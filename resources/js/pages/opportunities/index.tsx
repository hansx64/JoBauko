import { Head, Link, router } from '@inertiajs/react';
import { Info, Search, SlidersHorizontal, X } from 'lucide-react';
import { FormEventHandler, ReactNode, useState } from 'react';

import { OpportunityCard } from '@/components/opportunities/opportunity-card';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import { type Barangay, type Option, type Paginated } from '@/types';
import { type OpportunityCardData } from '@/types/opportunity';

interface Filters {
    q: string;
    category: string | null;
    barangay: number | null;
    remote: boolean;
    organization: number | null;
    education: string | null;
    skill: string | null;
    employment_type: string | null;
    salary_min: number | null;
    closing: string | null;
    status: 'open' | 'all';
    sort: string;
}

interface BrowseProps {
    opportunities: Paginated<OpportunityCardData>;
    filters: Filters;
    isSeeker: boolean;
    options: {
        categories: Option[];
        barangays: Barangay[];
        organizations: { id: number; name: string }[];
        educationLevels: Option[];
        employmentTypes: Option[];
        skills: string[];
    };
}

const ANY = 'any';
const tabLabels: Record<string, string> = { '': 'All', job: 'Jobs', scholarship: 'Scholarships', livelihood: 'Livelihood' };

export default function Browse({ opportunities, filters, isSeeker, options }: BrowseProps) {
    const [query, setQuery] = useState(filters.q);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const params = Object.fromEntries(
            Object.entries(next).filter(([key, value]) => {
                if (value === null || value === '' || value === false) return false;
                if (key === 'status' && value === 'open') return false;
                return true;
            }),
        );

        router.get(route('opportunities.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        apply({ q: query.trim() });
    };

    const activeCount = [
        filters.barangay,
        filters.remote,
        filters.organization,
        filters.education,
        filters.skill,
        filters.employment_type,
        filters.salary_min,
        filters.closing,
        filters.status === 'all',
    ].filter(Boolean).length;
    const hasAnyFilter = activeCount > 0 || filters.q !== '' || filters.category !== null;

    const clearAll = () => {
        setQuery('');
        router.get(route('opportunities.index'), filters.sort !== (isSeeker ? 'match' : 'newest') ? { sort: filters.sort } : {}, {
            preserveScroll: true,
        });
    };

    const filterPanel = (
        <div className="grid gap-5">
            <FilterField label="Location">
                <Select
                    value={filters.barangay ? String(filters.barangay) : ANY}
                    onValueChange={(v) => apply({ barangay: v === ANY ? null : Number(v) })}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any location</SelectItem>
                        {options.barangays.map((b) => (
                            <SelectItem key={b.id} value={String(b.id)}>
                                Brgy. {b.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={filters.remote} onCheckedChange={(v) => apply({ remote: v === true })} />
                    Remote only
                </label>
            </FilterField>

            <FilterField label="Organization">
                <Select
                    value={filters.organization ? String(filters.organization) : ANY}
                    onValueChange={(v) => apply({ organization: v === ANY ? null : Number(v) })}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any organization</SelectItem>
                        {options.organizations.map((o) => (
                            <SelectItem key={o.id} value={String(o.id)}>
                                {o.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="My education level" hint="Shows opportunities whose requirement your level meets.">
                <Select value={filters.education ?? ANY} onValueChange={(v) => apply({ education: v === ANY ? null : v })}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any requirement</SelectItem>
                        {options.educationLevels.map((o) => (
                            <SelectItem key={o.value} value={o.value}>
                                {o.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Skill">
                <Select value={filters.skill ?? ANY} onValueChange={(v) => apply({ skill: v === ANY ? null : v })}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any skill</SelectItem>
                        {options.skills.map((s) => (
                            <SelectItem key={s} value={s}>
                                {s}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Employment type (jobs)">
                <Select value={filters.employment_type ?? ANY} onValueChange={(v) => apply({ employment_type: v === ANY ? null : v })}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any type</SelectItem>
                        {options.employmentTypes.map((o) => (
                            <SelectItem key={o.value} value={o.value}>
                                {o.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Monthly salary of at least (jobs)">
                <Select
                    value={filters.salary_min ? String(filters.salary_min) : ANY}
                    onValueChange={(v) => apply({ salary_min: v === ANY ? null : Number(v) })}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any salary</SelectItem>
                        {[10000, 15000, 20000, 30000, 50000].map((amount) => (
                            <SelectItem key={amount} value={String(amount)}>
                                ₱{amount.toLocaleString()}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Application deadline">
                <Select value={filters.closing ?? ANY} onValueChange={(v) => apply({ closing: v === ANY ? null : v })}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ANY}>Any deadline</SelectItem>
                        <SelectItem value="7">Closing within 7 days</SelectItem>
                        <SelectItem value="30">Closing within 30 days</SelectItem>
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Status">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={filters.status === 'all'} onCheckedChange={(v) => apply({ status: v === true ? 'all' : 'open' })} />
                    Include closed and expired
                </label>
            </FilterField>

            {hasAnyFilter && (
                <Button variant="outline" onClick={clearAll}>
                    <X className="h-4 w-4" /> Clear filters
                </Button>
            )}
        </div>
    );

    return (
        <PublicLayout>
            <Head title="Browse opportunities" />
            <div className="mx-auto max-w-6xl px-4 py-8">
                <div className="mb-6 space-y-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Opportunities</h1>
                        <p className="text-muted-foreground">Jobs, scholarships and livelihood programs from verified organizations.</p>
                    </div>
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search by title, organization or skill"
                                aria-label="Search opportunities"
                                className="h-11 pl-9"
                            />
                        </div>
                        <Button type="submit" className="h-11">
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap gap-1" role="tablist" aria-label="Category">
                            {[{ value: '', label: 'All' }, ...options.categories].map((category) => {
                                const active = (filters.category ?? '') === category.value;
                                return (
                                    <button
                                        key={category.value || 'all'}
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => apply({ category: category.value || null })}
                                        className={cn(
                                            'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                            active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                                        )}
                                    >
                                        {tabLabels[category.value] ?? category.label}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex items-center gap-2">
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button variant="outline" className="lg:hidden">
                                        <SlidersHorizontal className="h-4 w-4" /> Filters{activeCount > 0 && ` (${activeCount})`}
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="left" className="w-80 overflow-y-auto">
                                    <SheetHeader>
                                        <SheetTitle>Filters</SheetTitle>
                                    </SheetHeader>
                                    <div className="px-4 pb-6">{filterPanel}</div>
                                </SheetContent>
                            </Sheet>
                            <Select value={filters.sort} onValueChange={(v) => apply({ sort: v })}>
                                <SelectTrigger className="w-48" aria-label="Sort by">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {isSeeker && <SelectItem value="match">Best match for me</SelectItem>}
                                    <SelectItem value="newest">Newest first</SelectItem>
                                    <SelectItem value="deadline">Deadline soonest</SelectItem>
                                    <SelectItem value="salary">Highest salary</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-[16rem_1fr]">
                    <aside className="hidden lg:block">
                        <div className="sticky top-20">{filterPanel}</div>
                    </aside>

                    <div className="grid content-start gap-4">
                        <p className="text-muted-foreground text-sm">
                            {opportunities.total} {opportunities.total === 1 ? 'opportunity' : 'opportunities'}
                            {filters.q && ` for “${filters.q}”`}
                        </p>

                        {isSeeker ? (
                            <p className="text-muted-foreground flex items-start gap-2 text-xs">
                                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                Match indicators are based on your profile and are only a guide. You can view and apply to any opportunity.
                            </p>
                        ) : (
                            <p className="bg-accent text-accent-foreground rounded-lg px-4 py-3 text-sm">
                                <Link href={route('register')} className="font-medium underline underline-offset-4">
                                    Create a free account
                                </Link>{' '}
                                to see which opportunities match your skills and qualifications.
                            </p>
                        )}

                        {opportunities.data.length === 0 ? (
                            <div className="text-muted-foreground rounded-xl border p-10 text-center">
                                <p className="text-foreground font-medium">No opportunities match your search.</p>
                                <p className="mt-1 text-sm">Try other keywords or remove some filters.</p>
                                {hasAnyFilter && (
                                    <Button variant="outline" className="mt-4" onClick={clearAll}>
                                        Clear filters
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {opportunities.data.map((opportunity) => (
                                    <OpportunityCard key={opportunity.id} opportunity={opportunity} showSave={isSeeker} showReasons={isSeeker} />
                                ))}
                            </div>
                        )}

                        <Pagination page={opportunities} />
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function FilterField({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label className="text-sm font-medium">{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
        </div>
    );
}
