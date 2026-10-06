<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\BuyerInsightsService;

beforeEach(fn () => $this->service = app(BuyerInsightsService::class));

function completedPurchase(User $buyer, array $overrides = []): Transaction
{
    $listingOverrides = $overrides['listing'] ?? [];
    unset($overrides['listing']);

    $listing = Listing::factory()->create(array_merge(['category_id' => Category::factory()], $listingOverrides));

    return Transaction::factory()->create(array_merge([
        'buyer_id' => $buyer->id,
        'seller_id' => User::factory(),
        'listing_id' => $listing->id,
        'amount' => $listing->price,
        'status' => 'COMPLETED',
        'completed_at' => now(),
    ], $overrides));
}

it('computes real totals from the buyer\'s own completed purchases only', function () {
    $buyer = User::factory()->create();
    completedPurchase($buyer, ['amount' => 100]);
    completedPurchase($buyer, ['amount' => 300]);
    completedPurchase($buyer, ['status' => 'initiated']); // not completed - excluded
    completedPurchase(User::factory()->create()); // someone else's purchase - excluded

    $totals = $this->service->totals($buyer);

    expect($totals['total_items'])->toBe(2)
        ->and($totals['total_spent'])->toBe(400.0)
        ->and($totals['average_spend'])->toBe(200.0);
});

it('reports zero totals for a buyer with no completed purchases', function () {
    $totals = $this->service->totals(User::factory()->create());

    expect($totals)->toBe(['total_items' => 0, 'total_spent' => 0.0, 'average_spend' => 0.0]);
});

it('groups spending by category with correct percentages', function () {
    $buyer = User::factory()->create();
    $books = Category::factory()->create(['name' => 'Books']);
    $electronics = Category::factory()->create(['name' => 'Electronics']);

    completedPurchase($buyer, ['amount' => 300, 'listing' => ['category_id' => $books->id]]);
    completedPurchase($buyer, ['amount' => 100, 'listing' => ['category_id' => $books->id]]);
    completedPurchase($buyer, ['amount' => 100, 'listing' => ['category_id' => $electronics->id]]);

    $byCategory = collect($this->service->spendingByCategory($buyer))->keyBy('category');

    expect($byCategory['Books']['amount'])->toBe(400.0)
        ->and($byCategory['Books']['percentage'])->toBe(80.0)
        ->and($byCategory['Electronics']['percentage'])->toBe(20.0);
});

describe('popular with students like you - privacy suppression', function () {
    it('falls back to campus-wide when the buyer has no year/school set', function () {
        $buyer = User::factory()->create(['year_of_study' => null, 'school' => null]);

        $result = $this->service->popularWithStudentsLikeYou($buyer);

        expect($result['level'])->toBe('the whole campus');
    });

    it('suppresses the year+school level below the minimum group size, falling back to year only', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);

        // Only 2 other same-year, same-school students with a purchase - below threshold of 5.
        foreach (range(1, 2) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
            completedPurchase($peer, ['completed_at' => now()->subDays(5)]);
        }

        // 6 same-year (any school) students - meets the threshold.
        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'business']);
            completedPurchase($peer, ['completed_at' => now()->subDays(5)]);
        }

        $result = $this->service->popularWithStudentsLikeYou($buyer);

        expect($result['level'])->toBe('students in your year');
    });

    it('uses the year+school level once enough distinct buyers exist', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 3, 'school' => 'engineering']);

        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 3, 'school' => 'engineering']);
            completedPurchase($peer, ['completed_at' => now()->subDays(5)]);
        }

        $result = $this->service->popularWithStudentsLikeYou($buyer);

        expect($result['level'])->toBe('students in your year and school')
            ->and($result['categories'])->not->toBeEmpty();
    });

    it('never counts the buyer\'s own purchases toward someone else\'s "students like you" group', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 1, 'school' => 'health']);
        completedPurchase($buyer);

        // Only 4 peers (plus the buyer would make 5, but the buyer is not "someone like them").
        foreach (range(1, 4) as $_) {
            $peer = User::factory()->create(['year_of_study' => 1, 'school' => 'health']);
            completedPurchase($peer);
        }

        $result = $this->service->popularWithStudentsLikeYou($buyer);

        expect($result['level'])->not->toBe('students in your year and school');
    });
});

describe('popular listings', function () {
    it('returns real active listings from the top category for the group', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        $books = Category::factory()->create();
        $recommended = Listing::factory()->create(['category_id' => $books->id, 'title' => 'Recommended Book']);

        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
            completedPurchase($peer, ['listing' => ['category_id' => $books->id]]);
        }

        $listings = $this->service->popularListings($buyer);

        expect($listings->pluck('id'))->toContain($recommended->id);
    });

    it('never recommends the buyer their own listing', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        $books = Category::factory()->create();
        $ownListing = Listing::factory()->for($buyer, 'seller')->create(['category_id' => $books->id]);

        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
            completedPurchase($peer, ['listing' => ['category_id' => $books->id]]);
        }

        $listings = $this->service->popularListings($buyer);

        expect($listings->pluck('id'))->not->toContain($ownListing->id);
    });

    it('is empty when the category breakdown is suppressed', function () {
        config(['insights.min_group_size' => 5]);
        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        Listing::factory()->create();

        expect($this->service->popularListings($buyer))->toBeEmpty();
    });
});
