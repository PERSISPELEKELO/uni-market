<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('trending this week', function () {
    it('shows an active listing that has real views in the last 24 hours', function () {
        $listing = Listing::factory()->create(['title' => 'Viewed Textbook']);
        ListingEvent::create(['listing_id' => $listing->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHours(2)]);

        Livewire::test(ListingIndex::class)->assertSee('Trending this week')->assertSee('Viewed Textbook');
    });

    it('never shows an active listing with zero recent views', function () {
        Listing::factory()->create(['title' => 'Never Viewed Item']);

        Livewire::test(ListingIndex::class)->assertDontSee('Trending this week');
    });

    it('ignores views older than 24 hours', function () {
        $listing = Listing::factory()->create(['title' => 'Old View Item']);
        ListingEvent::create(['listing_id' => $listing->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHours(30)]);

        Livewire::test(ListingIndex::class)->assertDontSee('Trending this week');
    });

    it('hides the carousel once a filter is active', function () {
        $listing = Listing::factory()->create(['title' => 'Viewed Textbook']);
        ListingEvent::create(['listing_id' => $listing->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHours(2)]);

        Livewire::test(ListingIndex::class)->set('search', 'something')->assertDontSee('Trending this week');
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
