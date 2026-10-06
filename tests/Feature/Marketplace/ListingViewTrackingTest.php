<?php

use App\Livewire\Marketplace\ListingShow;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('records a view when a signed-in student opens a listing', function () {
    $listing = Listing::factory()->create();
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)->test(ListingShow::class, ['listing' => $listing]);

    expect(ListingView::where('listing_id', $listing->id)->where('viewer_id', $viewer->id)->count())->toBe(1);
});

it('never counts the seller viewing their own listing', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);

    Livewire::actingAs($seller)->test(ListingShow::class, ['listing' => $listing]);

    expect(ListingView::where('listing_id', $listing->id)->count())->toBe(0);
});

it('counts the same signed-in viewer only once per listing per 24 hours', function () {
    $listing = Listing::factory()->create();
    $viewer = User::factory()->create();

    Livewire::actingAs($viewer)->test(ListingShow::class, ['listing' => $listing]);
    Livewire::actingAs($viewer)->test(ListingShow::class, ['listing' => $listing]);
    Livewire::actingAs($viewer)->test(ListingShow::class, ['listing' => $listing]);

    expect(ListingView::where('listing_id', $listing->id)->where('viewer_id', $viewer->id)->count())->toBe(1);
});

it('counts the same viewer again after 24 hours have passed', function () {
    $listing = Listing::factory()->create();
    $viewer = User::factory()->create();

    ListingView::create([
        'listing_id' => $listing->id,
        'viewer_id' => $viewer->id,
        'session_hash' => 'irrelevant',
        'viewed_at' => now()->subHours(25),
    ]);

    Livewire::actingAs($viewer)->test(ListingShow::class, ['listing' => $listing]);

    expect(ListingView::where('listing_id', $listing->id)->where('viewer_id', $viewer->id)->count())->toBe(2);
});

it('records a guest view keyed by session rather than a user id, deduplicated the same way', function () {
    $listing = Listing::factory()->create();

    Livewire::test(ListingShow::class, ['listing' => $listing]);
    Livewire::test(ListingShow::class, ['listing' => $listing]);

    $views = ListingView::where('listing_id', $listing->id)->get();

    expect($views)->toHaveCount(1)
        ->and($views->first()->viewer_id)->toBeNull()
        ->and($views->first()->session_hash)->not->toBeEmpty();
});

it('counts two different signed-in viewers of the same listing separately', function () {
    $listing = Listing::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    Livewire::actingAs($first)->test(ListingShow::class, ['listing' => $listing]);
    Livewire::actingAs($second)->test(ListingShow::class, ['listing' => $listing]);

    expect(ListingView::where('listing_id', $listing->id)->count())->toBe(2);
});
