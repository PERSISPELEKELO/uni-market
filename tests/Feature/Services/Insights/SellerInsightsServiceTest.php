<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\SellerInsightsService;

beforeEach(fn () => $this->service = app(SellerInsightsService::class));

function completedSale(User $seller, array $overrides = []): Transaction
{
    $listingOverrides = $overrides['listing'] ?? [];
    unset($overrides['listing']);

    $listing = Listing::factory()->create(array_merge(['user_id' => $seller->id, 'category_id' => Category::factory()], $listingOverrides));

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

describe('totals by period', function () {
    it('only counts sales within the requested date range', function () {
        $seller = User::factory()->create();
        completedSale($seller, ['amount' => 100, 'completed_at' => now()->subDays(200)]);
        completedSale($seller, ['amount' => 300, 'completed_at' => now()->subDays(5)]);

        expect($this->service->totals($seller, '30d')['items_sold'])->toBe(1)
            ->and($this->service->totals($seller, '30d')['total_earned'])->toBe(300.0)
            ->and($this->service->totals($seller, 'all')['items_sold'])->toBe(2);
    });
});

describe('sold by category', function () {
    it('groups sold items by category and flags the top one as trending', function () {
        $seller = User::factory()->create();
        $books = Category::factory()->create(['name' => 'Books']);
        $pens = Category::factory()->create(['name' => 'Pens']);

        completedSale($seller, ['listing' => ['category_id' => $books->id]]);
        completedSale($seller, ['listing' => ['category_id' => $books->id]]);
        completedSale($seller, ['listing' => ['category_id' => $pens->id]]);

        $result = collect($this->service->soldByCategory($seller))->keyBy('category');

        expect($result['Books']['total_items'])->toBe(2)
            ->and($result['Books']['is_trending'])->toBeTrue()
            ->and($result['Pens']['total_items'])->toBe(1)
            ->and($result['Pens']['is_trending'])->toBeFalse();
    });

    it('keeps the category a sale happened in even if the listing is later recategorised', function () {
        $seller = User::factory()->create();
        $original = Category::factory()->create(['name' => 'Original Category']);
        $changed = Category::factory()->create(['name' => 'Changed Category']);

        $sale = completedSale($seller, ['listing' => ['category_id' => $original->id]]);
        $sale->listing->update(['category_id' => $changed->id]);

        $result = collect($this->service->soldByCategory($seller))->pluck('category');

        expect($result)->toContain('Original Category')->not->toContain('Changed Category');
    });

    it('is empty when nothing has sold yet', function () {
        $seller = User::factory()->create();

        expect($this->service->soldByCategory($seller))->toBe([]);
    });
});

describe('buyers by gender - privacy', function () {
    it('includes a by_gender breakdown alongside year and school, never cross-tabulated', function () {
        config(['insights.min_group_size' => 5]);
        $seller = User::factory()->create();

        foreach (range(1, 4) as $_) {
            completedSale($seller)->buyer->update(['year_of_study' => 1, 'school' => 'ict', 'gender' => 'female']);
        }
        completedSale($seller)->buyer->update(['year_of_study' => 1, 'school' => 'ict', 'gender' => 'male']);

        $result = $this->service->buyersByYearAndSchool($seller);
        $byGender = collect($result['by_gender'])->keyBy('gender');

        expect($byGender['Female']['percentage'])->toBe(80.0)
            ->and($byGender['Male']['percentage'])->toBe(20.0)
            ->and($result['by_year'])->not->toBeEmpty()
            ->and($result['by_school'])->not->toBeEmpty();
    });

    it('suppresses by_gender too, below the threshold', function () {
        config(['insights.min_group_size' => 5]);
        $seller = User::factory()->create();

        foreach (range(1, 2) as $_) {
            completedSale($seller)->buyer->update(['year_of_study' => 1, 'school' => 'ict']);
        }

        $result = $this->service->buyersByYearAndSchool($seller);

        expect($result['suppressed'])->toBeTrue()
            ->and($result['by_gender'])->toBe([]);
    });
});

describe('seller funnel', function () {
    it('counts real views, enquiries and sales for the seller\'s own listings only', function () {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $othersListing = Listing::factory()->create(['user_id' => $otherSeller->id]);

        ListingView::create(['listing_id' => $listing->id, 'session_hash' => hash('sha256', 'a'), 'viewed_at' => now()]);
        ListingView::create(['listing_id' => $listing->id, 'session_hash' => hash('sha256', 'b'), 'viewed_at' => now()]);
        ListingView::create(['listing_id' => $othersListing->id, 'session_hash' => hash('sha256', 'c'), 'viewed_at' => now()]);

        $buyer = User::factory()->create();
        Message::create(['listing_id' => $listing->id, 'sender_id' => $buyer->id, 'receiver_id' => $seller->id, 'message' => 'Still available?']);
        Message::create(['listing_id' => $listing->id, 'sender_id' => $seller->id, 'receiver_id' => $buyer->id, 'message' => 'Yes!']);

        completedSale($seller, ['listing' => ['user_id' => $seller->id]]);

        $result = $this->service->funnel($seller);

        expect($result['views'])->toBe(2)
            ->and($result['enquiries'])->toBe(1)
            ->and($result['sales'])->toBe(1);
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
