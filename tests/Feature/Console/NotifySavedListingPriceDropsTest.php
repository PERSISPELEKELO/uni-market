<?php

use App\Models\Listing;
use App\Models\SavedListing;
use App\Models\User;
use App\Notifications\SavedListingPriceDropped;
use Illuminate\Support\Facades\Notification;

it('notifies a student once when a saved listing genuinely drops in price', function () {
    Notification::fake();
    $student = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 80]);
    $saved = SavedListing::create(['user_id' => $student->id, 'listing_id' => $listing->id, 'price_at_save' => 100, 'created_at' => now()]);

    $this->artisan('saved-listings:notify-price-drops')->assertSuccessful();

    Notification::assertSentTo($student, SavedListingPriceDropped::class);
    expect($saved->fresh()->price_drop_notified_at)->not->toBeNull();
});

it('does not notify when the price has not dropped', function () {
    Notification::fake();
    $student = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 100]);
    SavedListing::create(['user_id' => $student->id, 'listing_id' => $listing->id, 'price_at_save' => 100, 'created_at' => now()]);

    $this->artisan('saved-listings:notify-price-drops');

    Notification::assertNothingSent();
});

it('never notifies twice for the same drop', function () {
    Notification::fake();
    $student = User::factory()->create();
    $listing = Listing::factory()->create(['price' => 80]);
    SavedListing::create(['user_id' => $student->id, 'listing_id' => $listing->id, 'price_at_save' => 100, 'created_at' => now()]);

    $this->artisan('saved-listings:notify-price-drops');
    $this->artisan('saved-listings:notify-price-drops');

    Notification::assertSentToTimes($student, SavedListingPriceDropped::class, 1);
});

it('ignores a saved listing whose item is no longer active', function () {
    Notification::fake();
    $student = User::factory()->create();
    $listing = Listing::factory()->sold()->create(['price' => 80]);
    SavedListing::create(['user_id' => $student->id, 'listing_id' => $listing->id, 'price_at_save' => 100, 'created_at' => now()]);

    $this->artisan('saved-listings:notify-price-drops');

    Notification::assertNothingSent();
});
