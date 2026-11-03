import { CircleCheck, CircleDashed } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { type Completion } from '@/types/profile';

export function CompletionMeter({ percent }: { percent: number }) {
    return (
        <div
            className="bg-muted h-2 w-full overflow-hidden rounded-full"
            role="progressbar"
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}
        >
            <div className="h-full rounded-full bg-emerald-600 transition-all" style={{ width: `${percent}%` }} />
        </div>
    );
}

export function CompletionCard({ completion }: { completion: Completion }) {
    const missing = completion.sections.filter((s) => !s.complete);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-baseline justify-between text-base">
                    Profile completion
                    <span className="text-2xl font-semibold tabular-nums">{completion.percent}%</span>
                </CardTitle>
                <CardDescription>
                    {missing.length === 0 ? 'Your profile is complete.' : 'A more complete profile gives you better recommendations.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <CompletionMeter percent={completion.percent} />
                <ul className="grid gap-2 text-sm">
                    {completion.sections.map((section) => (
                        <li key={section.key}>
                            <a href={`#${section.key}`} className="hover:bg-muted -mx-2 flex items-start gap-2 rounded-md px-2 py-1">
                                {section.complete ? (
                                    <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                ) : (
                                    <CircleDashed className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                )}
                                <span>
                                    <span className={section.complete ? '' : 'font-medium'}>{section.label}</span>
                                    {!section.complete && <span className="text-muted-foreground block text-xs">{section.hint}</span>}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
