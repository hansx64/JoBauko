import { cn } from '@/lib/utils';
import { formatDateTime } from '@/types/application';

export interface TimelineEntry {
    status: string;
    label: string;
    remarks: string | null;
    at: string;
    by: string;
}

/**
 * Status history, newest first.
 */
export function StatusTimeline({ entries }: { entries: TimelineEntry[] }) {
    return (
        <ol className="relative grid gap-5 border-l pl-5">
            {[...entries].reverse().map((entry, index) => (
                <li key={`${entry.status}-${entry.at}-${index}`} className="relative">
                    <span
                        className={cn(
                            'ring-background absolute top-1 -left-[1.6rem] size-3 rounded-full ring-4',
                            index === 0 ? 'bg-primary' : 'bg-muted-foreground/40',
                        )}
                    />
                    <p className="text-sm font-medium">{entry.label}</p>
                    <p className="text-muted-foreground text-xs">
                        {formatDateTime(entry.at)} · {entry.by}
                    </p>
                    {entry.remarks && <p className="mt-1 text-sm whitespace-pre-line">{entry.remarks}</p>}
                </li>
            ))}
        </ol>
    );
}
