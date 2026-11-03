import { cn } from '@/lib/utils';
import { Sparkles } from 'lucide-react';

export function matchLabel(score: number) {
    if (score >= 75) return 'Strong match';
    if (score >= 50) return 'Good match';
    if (score >= 30) return 'Possible match';
    return 'Low match';
}

/**
 * How well an opportunity fits the seeker's profile. A guide only: it never
 * decides who may apply.
 */
export function MatchBadge({ score, className }: { score: number; className?: string }) {
    const tone =
        score >= 75
            ? 'bg-emerald-600 text-white'
            : score >= 50
              ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-100'
              : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';

    return (
        <span
            className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap', tone, className)}
            title="Match indicator based on your profile. It does not decide eligibility."
        >
            <Sparkles className="h-3 w-3" />
            {score}% · {matchLabel(score)}
        </span>
    );
}
