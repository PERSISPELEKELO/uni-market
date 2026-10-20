<?php

declare(strict_types=1);

namespace App\Services\Insights;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Generation-based cache invalidation: every Insights cache key embeds the
 * current "version" number. Bumping the version (done by TransactionObserver
 * whenever a transaction completes) instantly orphans every previous key
 * without needing to enumerate them - necessary because the app's cache
 * driver (database) does not support cache tags.
 *
 * Only ever stores plain arrays. Laravel's cache refuses to unserialize
 * arbitrary objects (config('cache.serializable_classes') = false, and it
 * should stay false - that's a real protection against unsafe
 * unserialization, not something to weaken), so a callback returning an
 * Eloquent Collection would otherwise come back from a *second* read as
 * __PHP_Incomplete_Class and crash whatever called it. Here, a Collection
 * result is unwrapped to a plain array before it's cached and rewrapped
 * when read back, so every existing `: Collection`-returning caller keeps
 * working without having to know any of this happened. A value that is
 * neither a Collection nor a plain array is never cached at all - it's
 * just returned as-is, since there's nothing safe to do with it here.
 */
class InsightsCache
{
    private const VERSION_KEY = 'insights:version';

    public static function remember(string $key, Closure $callback): mixed
    {
        $cacheKey = self::VERSION_KEY.':'.self::version().':'.$key;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            if (self::isWellFormed($cached)) {
                return $cached['is_collection'] ? collect($cached['data']) : $cached['data'];
            }

            Log::warning('InsightsCache: discarding a corrupted cache entry and recomputing it.', ['key' => $cacheKey]);
            Cache::forget($cacheKey);
        }

        $value = $callback();
        $isCollection = $value instanceof Collection;
        $data = $isCollection ? $value->toArray() : $value;

        if (! self::isPlainArray($data)) {
            Log::warning('InsightsCache: refusing to cache a value that is not a plain array; returning it uncached.', ['key' => $cacheKey, 'type' => get_debug_type($value)]);

            return $value;
        }

        Cache::put($cacheKey, ['is_collection' => $isCollection, 'data' => $data], now()->addMinutes((int) config('insights.cache_minutes')));

        return $value;
    }

    public static function bump(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }

    /**
     * @phpstan-assert-if-true array{is_collection: bool, data: array} $value
     */
    private static function isWellFormed(mixed $value): bool
    {
        return is_array($value)
            && array_key_exists('is_collection', $value)
            && array_key_exists('data', $value)
            && is_bool($value['is_collection'])
            && self::isPlainArray($value['data']);
    }

    /**
     * Recursively refuses anything that isn't a plain scalar/array tree -
     * exactly what every cache driver can serialize and unserialize back
     * safely, with nothing left to accidentally resolve as an object.
     */
    private static function isPlainArray(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (is_object($item)) {
                return false;
            }

            if (is_array($item) && ! self::isPlainArray($item)) {
                return false;
            }
        }

        return true;
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
