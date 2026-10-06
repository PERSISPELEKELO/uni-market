<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('selling fast', function () {
    it('shows an active listing that has real recent views', function () {
        $listing = Listing::factory()->create(['title' => 'Viewed Textbook']);
        ListingView::create(['listing_id' => $listing->id, 'session_hash' => hash('sha256', 'session-1'), 'viewed_at' => now()->subDay()]);

        Livewire::test(ListingIndex::class)->assertSee('Selling fast')->assertSee('Viewed Textbook');
    });

    it('never shows an active listing with zero recent views', function () {
        Listing::factory()->create(['title' => 'Never Viewed Item']);

        Livewire::test(ListingIndex::class)->assertDontSee('Selling fast');
    });

    it('ignores views older than 7 days', function () {
        $listing = Listing::factory()->create(['title' => 'Old View Item']);
        ListingView::create(['listing_id' => $listing->id, 'session_hash' => hash('sha256', 'session-2'), 'viewed_at' => now()->subDays(10)]);

        Livewire::test(ListingIndex::class)->assertDontSee('Selling fast');
    });

    it('hides the carousel once a filter is active', function () {
        $listing = Listing::factory()->create(['title' => 'Viewed Textbook']);
        ListingView::create(['listing_id' => $listing->id, 'session_hash' => hash('sha256', 'session-3'), 'viewed_at' => now()->subDay()]);

        Livewire::test(ListingIndex::class)->set('search', 'something')->assertDontSee('Selling fast');
    });
});

describe('popular with students like you', function () {
    it('is hidden for guests', function () {
        Listing::factory()->create();

        Livewire::test(ListingIndex::class)->assertDontSee('Popular with students like you');
    });

    it('shows listings from the viewer\'s top category once the group is large enough', function () {
        config(['insights.min_group_size' => 5]);

        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        $books = Category::factory()->create(['name' => 'Books']);
        $listing = Listing::factory()->create(['category_id' => $books->id, 'title' => 'Popular Book']);

        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
            Transaction::factory()->create([
                'buyer_id' => $peer->id,
                'listing_id' => Listing::factory()->create(['category_id' => $books->id])->id,
                'status' => 'COMPLETED',
                'completed_at' => now()->subDays(5),
            ]);
        }

        Livewire::actingAs($buyer)->test(ListingIndex::class)
            ->assertSee('Popular with students like you')
            ->assertSee('Popular Book');

        expect($listing)->not->toBeNull();
    });

    it('never recommends the viewer their own listing', function () {
        config(['insights.min_group_size' => 5]);

        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        $books = Category::factory()->create(['name' => 'Books']);
        $ownListing = Listing::factory()->for($buyer, 'seller')->create(['category_id' => $books->id, 'title' => 'My Own Book']);

        foreach (range(1, 6) as $_) {
            $peer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
            Transaction::factory()->create([
                'buyer_id' => $peer->id,
                'listing_id' => Listing::factory()->create(['category_id' => $books->id])->id,
                'status' => 'COMPLETED',
                'completed_at' => now()->subDays(5),
            ]);
        }

        $popular = Livewire::actingAs($buyer)->test(ListingIndex::class)->viewData('popularWithYou');

        expect($popular->pluck('id'))->not->toContain($ownListing->id);
    });

    it('stays hidden below the privacy threshold', function () {
        config(['insights.min_group_size' => 5]);

        $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
        Listing::factory()->create();

        Livewire::actingAs($buyer)->test(ListingIndex::class)->assertDontSee('Popular with students like you');
    });
});
