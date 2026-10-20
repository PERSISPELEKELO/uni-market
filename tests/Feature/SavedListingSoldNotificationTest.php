<?php

use App\Livewire\Marketplace\MyListings;
use App\Models\Listing;
use App\Models\SavedListing;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SavedListingSold;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('notifies other savers, but never the buyer, once the buyer confirms item acceptance', function () {
    Notification::fake();
    $listing = Listing::factory()->pending()->create();
    $buyer = User::factory()->create();
    $otherSaver = User::factory()->create();
    SavedListing::create(['user_id' => $buyer->id, 'listing_id' => $listing->id, 'price_at_save' => $listing->price, 'created_at' => now()]);
    SavedListing::create(['user_id' => $otherSaver->id, 'listing_id' => $listing->id, 'price_at_save' => $listing->price, 'created_at' => now()]);
    $transaction = Transaction::factory()->create([
        'listing_id' => $listing->id,
        'buyer_id' => $buyer->id,
        'status' => 'ITEM_INSPECTION',
    ]);

    app(InspectionService::class)->confirmItemAcceptance($transaction, $buyer);

    expect($listing->fresh()->status)->toBe(Listing::STATUS_SOLD);
    Notification::assertSentTo($otherSaver, SavedListingSold::class);
    Notification::assertNotSentTo($buyer, SavedListingSold::class);
});

it('also fires for a seller marking a listing sold manually, the same as the escrow flow', function () {
    Notification::fake();
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);
    $saver = User::factory()->create();
    SavedListing::create(['user_id' => $saver->id, 'listing_id' => $listing->id, 'price_at_save' => $listing->price, 'created_at' => now()]);

    Livewire::actingAs($seller)->test(MyListings::class)->call('markSoldManually', $listing->id);

    Notification::assertSentTo($saver, SavedListingSold::class);
});

it('does not fire the saved-listing-sold notification for an unrelated status change', function () {
    Notification::fake();
    $listing = Listing::factory()->create();
    $saver = User::factory()->create();
    SavedListing::create(['user_id' => $saver->id, 'listing_id' => $listing->id, 'price_at_save' => $listing->price, 'created_at' => now()]);

    $listing->update(['status' => Listing::STATUS_PENDING]);

    Notification::assertNothingSent();
});

it('does nothing when no one saved the sold listing', function () {
    Notification::fake();
    $listing = Listing::factory()->pending()->create();
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'listing_id' => $listing->id,
        'buyer_id' => $buyer->id,
        'status' => 'ITEM_INSPECTION',
    ]);

    app(InspectionService::class)->confirmItemAcceptance($transaction, $buyer);

    Notification::assertNotSentTo($buyer, SavedListingSold::class);
});
