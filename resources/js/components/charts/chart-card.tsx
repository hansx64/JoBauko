import { BarChart3, Table2 } from 'lucide-react';
import { ReactNode, useState } from 'react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * A chart with a switch to the same data as a table, so values never
 * depend on reading the graphic.
 */
export function ChartCard({
    title,
    description,
    chart,
    table,
    className,
}: {
    title: string;
    description?: ReactNode;
    chart: ReactNode;
    table: ReactNode;
    className?: string;
}) {
    const [view, setView] = useState<'chart' | 'table'>('chart');

    return (
        <Card className={className}>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="grid gap-1.5">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </div>
                <div className="flex shrink-0 rounded-md border p-0.5" role="group" aria-label="Display as">
                    {(
                        [
                            ['chart', BarChart3, 'Chart'],
                            ['table', Table2, 'Table'],
                        ] as const
                    ).map(([key, Icon, label]) => (
                        <button
                            key={key}
                            type="button"
                            aria-pressed={view === key}
                            title={label}
                            onClick={() => setView(key)}
                            className={cn(
                                'rounded px-2 py-1 text-xs',
                                view === key ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="h-3.5 w-3.5" />
                            <span className="sr-only">{label}</span>
                        </button>
                    ))}
                </div>
            </CardHeader>
            <CardContent>{view === 'chart' ? chart : <div className="max-h-72 overflow-auto">{table}</div>}</CardContent>
        </Card>
    );
}
