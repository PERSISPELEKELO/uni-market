<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Generation-based cache invalidation: every Insights cache key embeds the
 * current "version" number. Bumping the version (done by TransactionObserver
 * whenever a transaction completes) instantly orphans every previous key
 * without needing to enumerate them - necessary because the app's cache
 * driver (database) does not support cache tags.
 */
class InsightsCache
{
    private const VERSION_KEY = 'insights:version';

    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(
            self::VERSION_KEY.':'.self::version().':'.$key,
            now()->addMinutes((int) config('insights.cache_minutes')),
            $callback
        );
    }

    public static function bump(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
