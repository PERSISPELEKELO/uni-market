<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Illuminate\Support\Carbon;

/**
 * The student-facing date-range filter (My Activity's Insights tab):
 * last 30 days, this semester, or all time. Deliberately a smaller,
 * simpler set than the admin Market Insights page's own period options -
 * these are two different audiences with different needs, not one
 * shared config to keep in sync.
 */
class PeriodBoundary
{
    public const OPTIONS = [
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
            '30d' => now()->subDays(30),
            'semester' => now()->subDays(120),
            default => null,
        };
    }
}
