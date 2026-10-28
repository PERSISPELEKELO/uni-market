<?php

use App\Models\Listing;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders the gallery with every photo embedded and wire:ignore so Livewire never resets it', function () {
    $listing = Listing::factory()->create(['images' => ['listings/a.jpg', 'listings/b.jpg', 'listings/c.jpg']]);

    $response = $this->get(route('listings.show', $listing));

    // The image URLs only ever appear JSON-encoded inside the x-data
    // attribute (@js()), which escapes forward slashes - matching on the
    // filename alone sidesteps that rather than asserting on the exact
    // escaped form.
    $response->assertOk()
        ->assertSee('wire:ignore', false)
        ->assertSee('listingGallery', false)
        ->assertSee('a.jpg', false)
        ->assertSee('b.jpg', false)
        ->assertSee('c.jpg', false);
});

it('shows a no-photo placeholder instead of erroring when a listing has no photos', function () {
    $listing = Listing::factory()->create(['images' => []]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('No photo');
});

it('includes the desktop viewer details panel with the listing\'s title, price, condition and seller', function () {
    $seller = User::factory()->create(['name' => 'Chanda Mumba']);
    $listing = Listing::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Graphing Calculator',
        'price' => 450,
        'condition' => 'like_new',
        'images' => ['listings/calc.jpg'],
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSeeInOrder(['Graphing Calculator', 'K450.00', 'Like new', 'Chanda Mumba']);
});

it('never shows the sold/suspended status badge for an active listing', function () {
    $listing = Listing::factory()->create(['status' => 'active']);

    $this->get(route('listings.show', $listing))->assertOk()->assertDontSee('Sold', false);
});

it('shows the status badge for a listing the seller is viewing while it is sold', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->sold()->create(['user_id' => $seller->id]);

    $this->actingAs($seller)->get(route('listings.show', $listing))->assertOk()->assertSee('Sold');
});
