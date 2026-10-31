import { Link } from '@inertiajs/react';
import { BriefcaseBusiness, CalendarClock, GraduationCap, MapPin, Sprout, Wallet } from 'lucide-react';

import { MatchBadge } from '@/components/opportunities/match-badge';
import { SaveButton } from '@/components/opportunities/save-button';
import { StatusBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';
import { type OpportunityCardData } from '@/types/opportunity';
import { formatDate } from '@/types/organization';

export const categoryIcons = {
    job: BriefcaseBusiness,
    scholarship: GraduationCap,
    livelihood: Sprout,
};

export const categoryTones = {
    job: 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
    scholarship: 'bg-violet-50 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
    livelihood: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
};

export function deadlineText(opportunity: Pick<OpportunityCardData, 'deadline' | 'days_left' | 'accepting'>) {
    if (!opportunity.deadline) return 'No deadline set';
    if (!opportunity.accepting) return `Closed · deadline was ${formatDate(opportunity.deadline)}`;
    if (opportunity.days_left === 0) return 'Closes today';
    if (opportunity.days_left === 1) return 'Closes tomorrow';
    if (opportunity.days_left !== null && opportunity.days_left <= 14)
        return `${opportunity.days_left} days left · ${formatDate(opportunity.deadline)}`;
    return `Apply by ${formatDate(opportunity.deadline)}`;
}

interface OpportunityCardProps {
    opportunity: OpportunityCardData;
    showSave?: boolean;
    showReasons?: boolean;
    className?: string;
}

export function OpportunityCard({ opportunity, showSave = false, showReasons = false, className }: OpportunityCardProps) {
    const Icon = categoryIcons[opportunity.category];
    const urgent = opportunity.accepting && opportunity.days_left !== null && opportunity.days_left <= 7;

    return (
        <article
            className={cn(
                'bg-card group hover:border-primary/40 relative flex flex-col gap-3 rounded-xl border p-5 shadow-xs transition-all hover:shadow-md',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    {opportunity.organization.logo_url ? (
                        <img src={opportunity.organization.logo_url} alt="" className="size-10 shrink-0 rounded-lg border object-cover" />
                    ) : (
                        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', categoryTones[opportunity.category])}>
                            <Icon className="h-5 w-5" />
                        </span>
                    )}
                    <p className="text-muted-foreground truncate text-sm">{opportunity.organization.name}</p>
                </div>
                {showSave && (
                    <div className="relative z-10">
                        <SaveButton opportunityId={opportunity.id} saved={opportunity.saved} />
                    </div>
                )}
            </div>

            <div className="space-y-1.5">
                <h3 className="leading-snug font-semibold">
                    <Link href={route('opportunities.show', opportunity.slug)} className="group-hover:underline after:absolute after:inset-0">
                        {opportunity.title}
                    </Link>
                </h3>
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', categoryTones[opportunity.category])}>
                        {opportunity.category_label}
                    </span>
                    {opportunity.employment_type && (
                        <span className="bg-muted rounded-full px-2 py-0.5 text-xs font-medium">{opportunity.employment_type}</span>
                    )}
                    {!opportunity.accepting && <StatusBadge status={opportunity.status} label={opportunity.status_label} />}
                    {opportunity.match && <MatchBadge score={opportunity.match.score} />}
                </div>
            </div>

            {opportunity.summary && <p className="text-muted-foreground line-clamp-2 text-sm">{opportunity.summary}</p>}

            {showReasons && opportunity.match && opportunity.match.reasons.length > 0 && (
                <ul className="space-y-0.5 text-xs text-teal-800 dark:text-teal-300">
                    {opportunity.match.reasons.slice(0, 2).map((reason) => (
                        <li key={reason}>✓ {reason}</li>
                    ))}
                </ul>
            )}

            <div className="text-muted-foreground mt-auto flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs">
                <span className="flex items-center gap-1">
                    <MapPin className="h-3.5 w-3.5" />
                    {opportunity.location}
                </span>
                {opportunity.salary && (
                    <span className="flex items-center gap-1">
                        <Wallet className="h-3.5 w-3.5" />
                        {opportunity.salary}
                    </span>
                )}
                <span className={cn('flex items-center gap-1', urgent && 'font-medium text-amber-700 dark:text-amber-400')}>
                    <CalendarClock className="h-3.5 w-3.5" />
                    {deadlineText(opportunity)}
                </span>
            </div>
        </article>
    );
}
