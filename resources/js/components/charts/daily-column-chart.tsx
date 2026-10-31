import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, type TooltipContentProps, XAxis, YAxis } from 'recharts';
import { type NameType, type ValueType } from 'recharts/types/component/DefaultTooltipContent';

export interface DailyCount {
    date: string;
    count: number;
}

export type Granularity = 'day' | 'month';

const formats: Record<Granularity, { tick: Intl.DateTimeFormatOptions; full: Intl.DateTimeFormatOptions }> = {
    day: { tick: { month: 'short', day: 'numeric' }, full: { weekday: 'short', month: 'short', day: 'numeric' } },
    month: { tick: { month: 'short', year: '2-digit' }, full: { month: 'long', year: 'numeric' } },
};

const formatDate = (date: string, options: Intl.DateTimeFormatOptions) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, options);

/**
 * One count per day (or month) as columns on a single baseline. Hovering a
 * column's band shows its value. Monthly data uses the first of the month.
 */
export function DailyColumnChart({
    data,
    unit,
    granularity = 'day',
}: {
    data: DailyCount[];
    unit: [singular: string, plural: string];
    granularity?: Granularity;
}) {
    return (
        <div className="h-56 w-full">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 8, right: 4, bottom: 0, left: -20 }} barCategoryGap={2}>
                    <CartesianGrid vertical={false} stroke="var(--border)" strokeWidth={1} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={(date: string) => formatDate(date, formats[granularity].tick)}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--border)' }}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        interval="preserveStartEnd"
                        minTickGap={24}
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                        width={48}
                    />
                    <Tooltip cursor={{ fill: 'var(--muted)' }} content={(props) => <DayTooltip {...props} unit={unit} granularity={granularity} />} />
                    <Bar dataKey="count" fill="var(--viz-1)" radius={[4, 4, 0, 0]} maxBarSize={24} isAnimationActive={false} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function DayTooltip({
    active,
    payload,
    unit,
    granularity,
}: TooltipContentProps<ValueType, NameType> & { unit: [string, string]; granularity: Granularity }) {
    const point = payload?.[0]?.payload as DailyCount | undefined;

    if (!active || !point) {
        return null;
    }

    return (
        <div className="bg-popover text-popover-foreground rounded-md border px-3 py-2 text-xs shadow-md">
            <p className="text-muted-foreground">{formatDate(point.date, formats[granularity].full)}</p>
            <p className="flex items-center gap-1.5 font-medium">
                <span className="size-2 rounded-sm bg-[var(--viz-1)]" />
                {point.count} {point.count === 1 ? unit[0] : unit[1]}
            </p>
        </div>
    );
}

export function DailyCountTable({ data, label, granularity = 'day' }: { data: DailyCount[]; label: string; granularity?: Granularity }) {
    return (
        <table className="w-full text-sm">
            <thead className="text-muted-foreground text-left text-xs">
                <tr>
                    <th className="py-1.5 font-medium">{granularity === 'month' ? 'Month' : 'Date'}</th>
                    <th className="py-1.5 text-right font-medium">{label}</th>
                </tr>
            </thead>
            <tbody className="divide-y">
                {[...data].reverse().map((row) => (
                    <tr key={row.date}>
                        <td className="py-1.5">{formatDate(row.date, granularity === 'month' ? formats.month.full : formats.day.tick)}</td>
                        <td className="py-1.5 text-right tabular-nums">{row.count}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
