<?php

use App\Livewire\Marketplace\SaveToggle;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SavedListing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('saves a listing for the signed-in student', function () {
    $listing = Listing::factory()->create(['price' => 150]);
    $student = User::factory()->create();

    Livewire::actingAs($student)->test(SaveToggle::class, ['listing' => $listing])
        ->call('toggle')
        ->assertSet('saved', true);

    expect(SavedListing::where('user_id', $student->id)->where('listing_id', $listing->id)->first())
        ->price_at_save->toEqual('150.00');
});

it('also writes a unified listing_events row when saving, but not when unsaving', function () {
    $listing = Listing::factory()->create();
    $student = User::factory()->create();

    $component = Livewire::actingAs($student)->test(SaveToggle::class, ['listing' => $listing]);

    $component->call('toggle');
    expect(ListingEvent::where('listing_id', $listing->id)->where('type', ListingEvent::TYPE_SAVE)->count())->toBe(1);

    $component->call('toggle');
    expect(ListingEvent::where('listing_id', $listing->id)->where('type', ListingEvent::TYPE_SAVE)->count())->toBe(1)
        ->and(SavedListing::where('user_id', $student->id)->where('listing_id', $listing->id)->exists())->toBeFalse();
});

it('keeps saved listings isolated per student', function () {
    $listing = Listing::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    Livewire::actingAs($first)->test(SaveToggle::class, ['listing' => $listing])->call('toggle');

    Livewire::actingAs($second)->test(SaveToggle::class, ['listing' => $listing])
        ->assertSet('saved', false);

    expect(SavedListing::where('user_id', $second->id)->count())->toBe(0)
        ->and(SavedListing::where('user_id', $first->id)->count())->toBe(1);
});

it('does not let a seller save their own listing', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);

    Livewire::actingAs($seller)->test(SaveToggle::class, ['listing' => $listing])
        ->call('toggle')
        ->assertSet('saved', false);

    expect(SavedListing::where('listing_id', $listing->id)->count())->toBe(0);
});

it('redirects a guest to log in instead of saving', function () {
    $listing = Listing::factory()->create();

    Livewire::test(SaveToggle::class, ['listing' => $listing])
        ->call('toggle')
        ->assertRedirect(route('login'));

    expect(SavedListing::count())->toBe(0);
});
