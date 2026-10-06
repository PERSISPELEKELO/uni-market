<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\SellerInsightsService;

beforeEach(fn () => $this->service = app(SellerInsightsService::class));

function completedSale(User $seller, array $overrides = []): Transaction
{
    $listing = Listing::factory()->create(array_merge(['user_id' => $seller->id, 'category_id' => Category::factory()], $overrides['listing'] ?? []));

    return Transaction::factory()->create(array_merge([
        'seller_id' => $seller->id,
        'buyer_id' => User::factory(),
        'listing_id' => $listing->id,
        'amount' => $listing->price,
        'status' => 'COMPLETED',
        'created_at' => now()->subDays(5),
        'completed_at' => now(),
    ], $overrides));
}

it('computes real totals from the seller\'s own completed sales only', function () {
    $seller = User::factory()->create();
    completedSale($seller, ['amount' => 200]);
    completedSale($seller, ['amount' => 400]);
    completedSale(User::factory()->create()); // someone else's sale - excluded

    $totals = $this->service->totals($seller);

    expect($totals['items_sold'])->toBe(2)
        ->and($totals['total_earned'])->toBe(600.0)
        ->and($totals['average_price'])->toBe(300.0)
        ->and($totals['average_days_to_sale'])->toBe(5.0);
});

describe('buyers by year and school - privacy suppression', function () {
    it('suppresses the breakdown below the minimum distinct-buyer threshold', function () {
        config(['insights.min_group_size' => 5]);
        $seller = User::factory()->create();

        foreach (range(1, 3) as $_) {
            completedSale($seller)->buyer->update(['year_of_study' => 1, 'school' => 'ict']);
        }

        $result = $this->service->buyersByYearAndSchool($seller);

        expect($result['suppressed'])->toBeTrue()
            ->and($result['by_year'])->toBe([])
            ->and($result['by_school'])->toBe([]);
    });

    it('reports real percentages once enough distinct buyers have a year and school set', function () {
        config(['insights.min_group_size' => 5]);
        $seller = User::factory()->create();

        foreach (range(1, 4) as $_) {
            $tx = completedSale($seller);
            $tx->buyer->update(['year_of_study' => 1, 'school' => 'ict']);
        }
        $tx = completedSale($seller);
        $tx->buyer->update(['year_of_study' => 2, 'school' => 'business']);

        $result = $this->service->buyersByYearAndSchool($seller);

        expect($result['suppressed'])->toBeFalse();
        $byYear = collect($result['by_year'])->keyBy('year');
        expect($byYear[1]['percentage'])->toBe(80.0)
            ->and($byYear[2]['percentage'])->toBe(20.0);
    });
});

describe('price check', function () {
    it('leaves the comparison null when fewer than the minimum comparable sales exist', function () {
        config(['insights.min_group_size' => 5]);
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $category->id, 'condition' => 'good', 'price' => 500, 'status' => 'active']);

        // Only 2 comparable completed sales in this category+condition.
        foreach ([400, 450] as $price) {
            $soldListing = Listing::factory()->create(['category_id' => $category->id, 'condition' => 'good', 'price' => $price]);
            Transaction::factory()->create(['listing_id' => $soldListing->id, 'status' => 'COMPLETED', 'amount' => $price]);
        }

        $result = $this->service->priceCheck($seller)->firstWhere('listing.id', $listing->id);

        expect($result['median'])->toBeNull();
    });

    it('compares against the real median once enough comparable sales exist', function () {
        config(['insights.min_group_size' => 5]);
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $category->id, 'condition' => 'good', 'price' => 600, 'status' => 'active']);

        foreach ([400, 450, 500, 550, 600] as $price) {
            $soldListing = Listing::factory()->create(['category_id' => $category->id, 'condition' => 'good', 'price' => $price]);
            Transaction::factory()->create(['listing_id' => $soldListing->id, 'status' => 'COMPLETED', 'amount' => $price]);
        }

        $result = $this->service->priceCheck($seller)->firstWhere('listing.id', $listing->id);

        expect($result['median'])->toBe(500.0)
            ->and($result['difference_percentage'])->toBe(20.0); // 600 is 20% above the median of 500
    });
});
