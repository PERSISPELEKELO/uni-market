<?php

use App\Models\Category;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\MarketInsightsService;

beforeEach(fn () => $this->service = app(MarketInsightsService::class));

it('computes real totals for the period and the previous period\'s change', function () {
    Transaction::factory()->create(['status' => 'COMPLETED', 'amount' => 100, 'completed_at' => now()->subDays(2)]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'amount' => 200, 'completed_at' => now()->subDays(2)]);
    // Previous period (8-14 days ago): one transaction.
    Transaction::factory()->create(['status' => 'COMPLETED', 'amount' => 100, 'completed_at' => now()->subDays(10)]);

    $totals = $this->service->totals('7d');

    expect($totals['completed_transactions'])->toBe(2)
        ->and($totals['total_value'])->toBe(300.0)
        ->and($totals['change']['transactions'])->toBe(100.0); // 1 -> 2 is +100%
});

it('never includes a group of fewer than the minimum distinct buyers in the year x category heatmap', function () {
    config(['insights.min_group_size' => 5]);
    $category = Category::factory()->create();

    // Only 3 distinct year-2 buyers - below the threshold.
    foreach (range(1, 3) as $_) {
        $buyer = User::factory()->create(['year_of_study' => 2]);
        $listing = Listing::factory()->create(['category_id' => $category->id]);
        Transaction::factory()->create(['buyer_id' => $buyer->id, 'listing_id' => $listing->id, 'status' => 'COMPLETED', 'completed_at' => now()]);
    }

    $heatmap = $this->service->buyersByYearAndCategory('30d');
    $yearIndex = array_search(2, $heatmap['years'], true);

    expect($yearIndex)->not->toBeFalse();
    foreach ($heatmap['cells'][$yearIndex] as $cell) {
        expect($cell)->toBeNull();
    }
});

it('reveals a real cell once enough distinct buyers exist', function () {
    config(['insights.min_group_size' => 5]);
    $category = Category::factory()->create();

    foreach (range(1, 5) as $_) {
        $buyer = User::factory()->create(['year_of_study' => 3]);
        $listing = Listing::factory()->create(['category_id' => $category->id]);
        Transaction::factory()->create(['buyer_id' => $buyer->id, 'listing_id' => $listing->id, 'status' => 'COMPLETED', 'completed_at' => now()]);
    }

    $heatmap = $this->service->buyersByYearAndCategory('30d');
    $yearIndex = array_search(3, $heatmap['years'], true);
    $categoryIndex = array_search($category->name, $heatmap['categories'], true);

    expect($heatmap['cells'][$yearIndex][$categoryIndex])->toBe(5);
});

it('computes a real dispute rate from genuine disputed transactions', function () {
    $category = Category::factory()->create();
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    $disputed = Transaction::factory()->create(['listing_id' => $listing->id, 'status' => 'DISPUTED', 'created_at' => now()->subDays(1)]);
    Dispute::create(['transaction_id' => $disputed->id, 'raised_by' => $disputed->buyer_id, 'reason' => 'Not as described.', 'status' => 'open']);

    Transaction::factory()->create(['listing_id' => $listing->id, 'status' => 'COMPLETED', 'created_at' => now()->subDays(1)]);
    Transaction::factory()->create(['listing_id' => $listing->id, 'status' => 'COMPLETED', 'created_at' => now()->subDays(1)]);
    Transaction::factory()->create(['listing_id' => $listing->id, 'status' => 'COMPLETED', 'created_at' => now()->subDays(1)]);

    $trust = $this->service->trust('30d');

    expect($trust['dispute_rate'])->toBe(25.0); // 1 disputed out of 4 transactions
});

it('only ever shows unmet searches with a genuinely low average result count', function () {
    SearchLog::insert([
        ['term' => 'rare item', 'results_count' => 0, 'user_id' => null, 'created_at' => now()],
        ['term' => 'rare item', 'results_count' => 0, 'user_id' => null, 'created_at' => now()],
        ['term' => 'common item', 'results_count' => 20, 'user_id' => null, 'created_at' => now()],
    ]);

    $unmet = collect($this->service->unmetSearches())->keyBy('term');

    expect($unmet->has('rare item'))->toBeTrue()
        ->and($unmet->has('common item'))->toBeFalse();
});

it('counts new accounts per week for user growth, within the period only', function () {
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(60)]); // outside a 30d period

    $growth = $this->service->userGrowth('30d');

    expect(collect($growth)->sum('total'))->toBe(2);
});

it('splits participants into buyers-only, sellers-only and both, for the period', function () {
    $buyerOnly = User::factory()->create();
    $sellerOnly = User::factory()->create();
    $both = User::factory()->create();

    // buyerOnly only ever buys; sellerOnly only ever sells; both does both -
    // each against a disposable counterparty that isn't itself asserted on.
    Transaction::factory()->create(['status' => 'COMPLETED', 'buyer_id' => $buyerOnly->id, 'seller_id' => User::factory(), 'completed_at' => now()]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'buyer_id' => User::factory(), 'seller_id' => $sellerOnly->id, 'completed_at' => now()]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'buyer_id' => $both->id, 'seller_id' => User::factory(), 'completed_at' => now()]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'buyer_id' => User::factory(), 'seller_id' => $both->id, 'completed_at' => now()]);

    $result = $this->service->sellersVsBuyersVsBoth('30d');

    expect($result['both'])->toBe(1)
        ->and($result['buyers_only'])->toBeGreaterThanOrEqual(1)
        ->and($result['sellers_only'])->toBeGreaterThanOrEqual(1);
});

it('ranks top listings by completed sale value within the period', function () {
    $cheap = Listing::factory()->create(['title' => 'Cheap Item']);
    $expensive = Listing::factory()->create(['title' => 'Expensive Item']);

    Transaction::factory()->create(['status' => 'COMPLETED', 'listing_id' => $cheap->id, 'amount' => 50, 'completed_at' => now()]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'listing_id' => $expensive->id, 'amount' => 5000, 'completed_at' => now()]);
    Transaction::factory()->create(['status' => 'COMPLETED', 'listing_id' => $cheap->id, 'amount' => 50, 'completed_at' => now()->subDays(60)]); // outside period

    $top = $this->service->topListings('30d');

    expect($top[0]['listing']->title)->toBe('Expensive Item')
        ->and($top[0]['amount'])->toBe(5000.0);
});
