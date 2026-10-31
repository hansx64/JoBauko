<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Counts per calendar day for the last N days, oldest first, with days that
 * have no rows filled in as zero so charts keep an even time axis.
 */
class DailyCounts
{
    /**
     * @return list<array{date: string, count: int}>
     */
    public static function lastDays(Builder $query, string $column, int $days = 30): array
    {
        $since = today()->subDays($days - 1);

        $counts = (clone $query)
            ->where($column, '>=', $since)
            ->selectRaw("date({$column}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return array_map(function (int $offset) use ($since, $counts) {
            $day = $since->copy()->addDays($offset)->toDateString();

            return ['date' => $day, 'count' => (int) ($counts[$day] ?? 0)];
        }, range(0, $days - 1));
    }
}
