<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\FrequentlyBoughtTogetherService;

beforeEach(fn () => $this->service = app(FrequentlyBoughtTogetherService::class));

function boughtFrom(User $buyer, Category $category): void
{
    $listing = Listing::factory()->create(['category_id' => $category->id]);
    Transaction::factory()->create(['buyer_id' => $buyer->id, 'listing_id' => $listing->id, 'status' => 'COMPLETED']);
}

it('never shows a pair supported by fewer than the minimum number of distinct buyers', function () {
    config(['insights.min_group_size' => 5]);
    $books = Category::factory()->create(['name' => 'Books']);
    $pens = Category::factory()->create(['name' => 'Pens']);

    // Only 3 buyers bought both - below the threshold.
    foreach (range(1, 3) as $_) {
        $buyer = User::factory()->create();
        boughtFrom($buyer, $books);
        boughtFrom($buyer, $pens);
    }

    $pairs = $this->service->topPairs();

    expect($pairs->count())->toBe(0);
});

it('computes real support, confidence and lift once the threshold is met', function () {
    config(['insights.min_group_size' => 5]);
    $books = Category::factory()->create(['name' => 'Books']); // alphabetically "category_a"
    $pens = Category::factory()->create(['name' => 'Pens']); // alphabetically "category_b"
    $shoes = Category::factory()->create(['name' => 'Shoes']);

    // 5 buyers bought both books and pens.
    foreach (range(1, 5) as $_) {
        $buyer = User::factory()->create();
        boughtFrom($buyer, $books);
        boughtFrom($buyer, $pens);
    }

    // 5 more buyers bought only books (no pens) - dilutes books' own pen-attach rate.
    foreach (range(1, 5) as $_) {
        boughtFrom(User::factory()->create(), $books);
    }

    // 10 buyers who bought neither books nor pens - dilutes pens' overall population share,
    // which is what makes buying books a genuinely positive signal for pens (lift > 1).
    foreach (range(1, 10) as $_) {
        boughtFrom(User::factory()->create(), $shoes);
    }

    $pair = $this->service->topPairs()->first();

    // 20 total buyers; 5 bought both; 10 bought books; 5 bought pens.
    expect($pair['category_a'])->toBe('Books')
        ->and($pair['category_b'])->toBe('Pens')
        ->and($pair['buyers'])->toBe(5)
        ->and($pair['support'])->toBe(25.0)      // 5/20
        ->and($pair['confidence'])->toBe(50.0)   // 5 bought both / 10 bought books
        ->and($pair['lift'])->toBeGreaterThan(1.0); // P(pens|books)=50% > P(pens)=25% overall
});

it('only returns pairs that genuinely involve the requested category', function () {
    config(['insights.min_group_size' => 5]);
    $books = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);
    $pens = Category::factory()->create(['name' => 'Pens', 'slug' => 'pens']);
    $shoes = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);

    foreach (range(1, 5) as $_) {
        $buyer = User::factory()->create();
        boughtFrom($buyer, $books);
        boughtFrom($buyer, $pens);
    }
    foreach (range(1, 5) as $_) {
        $buyer = User::factory()->create();
        boughtFrom($buyer, $shoes);
        boughtFrom($buyer, $pens);
    }

    $pairs = $this->service->forCategory('books');

    expect($pairs)->toHaveCount(1)
        ->and(collect($pairs)->first())->toMatchArray(['category_a' => 'Books', 'category_b' => 'Pens']);
});
