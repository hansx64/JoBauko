import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, FileText, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { formatDate } from '@/types/organization';

interface ApplicantRow {
    id: number;
    applicant: { name: string; email: string; barangay: string | null; classification: string | null };
    opportunity: { id: number; title: string; category_label: string };
    status: string;
    status_label: string;
    match_score: number | null;
    documents_count: number;
    submitted_at: string;
}

interface Filters {
    opportunity: number | null;
    status: string | null;
    q: string;
    sort: 'newest' | 'oldest' | 'match';
}

interface ApplicantsProps {
    applications: Paginated<ApplicantRow>;
    filters: Filters;
    counts: Record<string, number>;
    options: { opportunities: { id: number; title: string }[]; statuses: Option[] };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Applicants', href: '/org/applications' }];
const ALL = 'all';

export default function Applicants({ applications, filters, counts, options }: ApplicantsProps) {
    const [q, setQ] = useState(filters.q);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };

        router.get(
            route('org.applications.index'),
            Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== '' && !(value === 'newest'))),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visit({ q });
    };

    const statusOptions = [
        { value: ALL, label: `All statuses (${counts.all})` },
        { value: 'active', label: `In progress (${counts.active})` },
        ...options.statuses.map((status) => ({ value: status.value, label: `${status.label} (${counts[status.value] ?? 0})` })),
    ];
    const filtered = filters.opportunity !== null || filters.status !== null || filters.q !== '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Applicants" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Applicants</h1>
                    <p className="text-muted-foreground">Review applications to your opportunities and move them through your process.</p>
                </div>

                <div className="grid gap-3 md:grid-cols-[1fr_1fr_12rem] lg:grid-cols-[1fr_1fr_12rem_1fr]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-opportunity">Opportunity</Label>
                        <Select
                            value={filters.opportunity ? String(filters.opportunity) : ALL}
                            onValueChange={(value) => visit({ opportunity: value === ALL ? null : Number(value) })}
                        >
                            <SelectTrigger id="filter-opportunity">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All opportunities</SelectItem>
                                {options.opportunities.map((o) => (
                                    <SelectItem key={o.id} value={String(o.id)}>
                                        {o.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-status">Status</Label>
                        <Select value={filters.status ?? ALL} onValueChange={(value) => visit({ status: value === ALL ? null : value })}>
                            <SelectTrigger id="filter-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-sort">Sort by</Label>
                        <Select value={filters.sort} onValueChange={(value) => visit({ sort: value as Filters['sort'] })}>
                            <SelectTrigger id="filter-sort">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="newest">Newest first</SelectItem>
                                <SelectItem value="oldest">Oldest first</SelectItem>
                                <SelectItem value="match">Best match</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <form onSubmit={submitSearch} className="grid gap-1.5 md:col-span-3 lg:col-span-1">
                        <Label htmlFor="filter-q">Applicant</Label>
                        <div className="flex gap-2">
                            <Input id="filter-q" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name or email" />
                            <Button type="submit" variant="outline" size="icon" aria-label="Search">
                                <Search className="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </div>

                <Card className="overflow-hidden py-0">
                    {applications.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                            <ClipboardList className="h-6 w-6" />
                            {filtered ? 'No applications match these filters.' : 'No applications yet.'}
                            {filtered && (
                                <Button variant="link" onClick={() => router.get(route('org.applications.index'))}>
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Applicant</th>
                                        <th className="px-4 py-3 font-medium">Opportunity</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium" title="Match score when the person applied">
                                            Match
                                        </th>
                                        <th className="px-4 py-3 font-medium">Applied</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {applications.data.map((row) => (
                                        <tr key={row.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Link href={route('org.applications.show', row.id)} className="font-medium hover:underline">
                                                    {row.applicant.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {[row.applicant.classification, row.applicant.barangay].filter(Boolean).join(' · ') ||
                                                        row.applicant.email}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="line-clamp-1">{row.opportunity.title}</p>
                                                <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                                    {row.opportunity.category_label}
                                                    {row.documents_count > 0 && (
                                                        <>
                                                            {' · '}
                                                            <FileText className="h-3 w-3" /> {row.documents_count}
                                                        </>
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={row.status} label={row.status_label} />
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {row.match_score === null ? '—' : `${row.match_score}%`}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{formatDate(row.submitted_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <p className="text-muted-foreground text-xs">
                    Match is the applicant's score when they applied, a guide based on their profile and not an eligibility decision.
                </p>

                <Pagination page={applications} />
            </div>
        </AppLayout>
    );
}
