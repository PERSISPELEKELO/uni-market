<?php

declare(strict_types=1);

namespace App\Services\Insights;

/**
 * The same hex values as the brand/accent/warn/danger/info tokens in
 * resources/css/app.css's @theme block - duplicated here because a <canvas>
 * can't read Tailwind's CSS custom properties, so this is the one place to
 * update if those tokens ever change.
 */
class ChartPalette
{
    public const COLORS = [
        '#4338ca', // brand-700
        '#059669', // accent-600
        '#f59e0b', // warn-500
        '#dc2626', // danger-600
        '#38bdf8', // info-400
        '#818cf8', // brand-400
        '#6ee7b7', // accent-300
        '#92400e', // warn-800
    ];

    /**
     * @return array<int, string>
     */
    public static function take(int $count): array
    {
        return collect(range(0, max(0, $count - 1)))
            ->map(fn (int $i) => self::COLORS[$i % count(self::COLORS)])
            ->all();
    }
}
