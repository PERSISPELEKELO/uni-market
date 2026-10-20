<?php

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\InsightsCache;
use App\Services\Insights\SellerInsightsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The `array` cache driver this suite uses by default never actually
 * serializes anything, which is exactly why this bug (a cached Collection
 * coming back as __PHP_Incomplete_Class) never showed up in tests before.
 * `database` forces a real serialize/unserialize round trip, the same as
 * production's CACHE_STORE.
 */
beforeEach(fn () => config(['cache.default' => 'database']));

it('returns a plain array correctly on both the computing call and the cached call', function () {
    $calls = 0;
    $callback = function () use (&$calls) {
        $calls++;

        return ['total' => 3, 'items' => ['a', 'b', 'c']];
    };

    $first = InsightsCache::remember('test:array-key', $callback);
    $second = InsightsCache::remember('test:array-key', $callback);

    expect($first)->toBe(['total' => 3, 'items' => ['a', 'b', 'c']])
        ->and($second)->toBe($first)
        ->and($calls)->toBe(1); // second call came from cache, not a recompute
});

it('returns a correct Collection on both the computing call and the cached call', function () {
    $callback = fn () => collect([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);

    $first = InsightsCache::remember('test:collection-key', $callback);
    $second = InsightsCache::remember('test:collection-key', $callback);

    expect($first)->toBeInstanceOf(Collection::class)
        ->and($second)->toBeInstanceOf(Collection::class)
        ->and($second->toArray())->toBe($first->toArray());
});

it('recovers from a corrupted cache entry instead of returning it broken', function () {
    config(['insights.min_group_size' => 1]);

    // Simulate what __PHP_Incomplete_Class looked like at this layer: a
    // value that isn't the well-formed ['is_collection' => ..., 'data' =>
    // ...] shape this class itself always writes.
    $versionKey = 'insights:version';
    Cache::forever($versionKey, 1);
    Cache::put($versionKey.':1:test:corrupted-key', 'not a well-formed cache entry', now()->addMinutes(5));

    Log::spy();

    $result = InsightsCache::remember('test:corrupted-key', fn () => ['recomputed' => true]);

    expect($result)->toBe(['recomputed' => true]);
    Log::shouldHaveReceived('warning')->once();
});

it('never caches a value that is not a plain array or Collection, and still returns it', function () {
    Log::spy();

    $object = new stdClass;
    $object->foo = 'bar';

    $result = InsightsCache::remember('test:unsafe-key', fn () => $object);

    expect($result)->toBe($object);
    Log::shouldHaveReceived('warning')->once();
    expect(Cache::has('insights:version:1:test:unsafe-key'))->toBeFalse();
});

it('round-trips a real Insights service method through the database cache store without breaking its return type', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);
    Transaction::factory()->create(['listing_id' => $listing->id, 'seller_id' => $seller->id, 'status' => 'COMPLETED', 'amount' => 100, 'completed_at' => now()]);

    $service = app(SellerInsightsService::class);

    $first = $service->totals($seller);
    $second = $service->totals($seller);

    expect($first)->toBeArray()
        ->and($second)->toBeArray()
        ->and($second)->toBe($first)
        ->and($second['items_sold'])->toBe(1);
});
