<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Illuminate\Support\Carbon;

/**
 * The student-facing date-range filter (My Activity's Insights tab).
 * Deliberately a smaller, simpler set than the admin Market Insights page's
 * own period options - these are two different audiences with different
 * needs, not one shared config to keep in sync.
 */
class PeriodBoundary
{
    public const OPTIONS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '28d' => 'Last 28 days',
        '30d' => 'Last 30 days',
        'semester' => 'This semester',
        'all' => 'All time',
    ];

    /**
     * Null means no lower bound at all - i.e. "all time".
     */
    public static function start(string $period): ?Carbon
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '28d' => now()->subDays(28),
            '30d' => now()->subDays(30),
            'semester' => now()->subDays(120),
            default => null,
        };
    }

    /**
     * The start of the immediately-preceding period of equal length, for a
     * KPI tile's "vs previous period" comparison. Null for 'all', which has
     * no meaningful "previous" period.
     */
    public static function previousStart(string $period): ?Carbon
    {
        $length = self::lengthInDays($period);

        return $length !== null ? now()->subDays($length * 2) : null;
    }

    /**
     * The (exclusive) end of the previous period - i.e. where the current
     * period begins.
     */
    public static function previousEnd(string $period): ?Carbon
    {
        $length = self::lengthInDays($period);

        return $length !== null ? now()->subDays($length) : null;
    }

    private static function lengthInDays(string $period): ?int
    {
        return match ($period) {
            'today' => 1,
            '7d' => 7,
            '28d' => 28,
            '30d' => 30,
            'semester' => 120,
            default => null,
        };
    }
}
