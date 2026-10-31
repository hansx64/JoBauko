import { Link } from '@inertiajs/react';

export interface RankedRow {
    key: string;
    label: string;
    value: number;
    href?: string;
}

/**
 * Parts of a whole compared on one baseline, largest first, one hue. Each
 * value is printed at its bar's tip, so the bars need no axis or tooltip.
 */
export function RankedBars({ rows, valueLabel }: { rows: RankedRow[]; valueLabel: string }) {
    const max = Math.max(1, ...rows.map((row) => row.value));

    return (
        <ul className="grid gap-2.5" aria-label={valueLabel}>
            {rows.map((row) => {
                const label = row.href ? (
                    <Link href={row.href} className="hover:underline">
                        {row.label}
                    </Link>
                ) : (
                    row.label
                );

                return (
                    <li key={row.key} className="grid grid-cols-[minmax(7rem,10rem)_1fr] items-center gap-3 text-sm">
                        <span className="truncate">{label}</span>
                        <span className="flex items-center gap-2" title={`${row.label}: ${row.value}`}>
                            <span
                                className="h-3 rounded-r bg-[var(--viz-1)]"
                                style={{ width: `${Math.max(2, (row.value / max) * 100)}%` }}
                                aria-hidden="true"
                            />
                            <span className="text-muted-foreground shrink-0 text-xs tabular-nums">{row.value}</span>
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
