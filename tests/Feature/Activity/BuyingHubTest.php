<?php

use App\Livewire\Activity\BuyingHub;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SavedListing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('needs a login', function () {
    $this->get(route('activity.buying'))->assertRedirect(route('login'));
});

it('lists recently viewed listings, most recent first, isolated per student', function () {
    $viewer = User::factory()->create();
    $seen = Listing::factory()->create(['title' => 'Seen earlier']);
    $seenLater = Listing::factory()->create(['title' => 'Seen most recently']);
    $neverSeen = Listing::factory()->create(['title' => 'Never opened']);

    ListingEvent::create(['listing_id' => $seen->id, 'user_id' => $viewer->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHour()]);
    ListingEvent::create(['listing_id' => $seenLater->id, 'user_id' => $viewer->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);
    ListingEvent::create(['listing_id' => $neverSeen->id, 'user_id' => User::factory()->create()->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);

    Livewire::actingAs($viewer)->test(BuyingHub::class)
        ->assertSeeInOrder(['Seen most recently', 'Seen earlier'])
        ->assertDontSee('Never opened');
});

it('shows saved listings with their own save-toggle button, isolated per student', function () {
    $student = User::factory()->create();
    $other = User::factory()->create();
    $listing = Listing::factory()->create(['title' => 'My saved item']);
    $othersListing = Listing::factory()->create(['title' => 'Someone else\'s saved item']);

    SavedListing::create(['user_id' => $student->id, 'listing_id' => $listing->id, 'price_at_save' => $listing->price, 'created_at' => now()]);
    SavedListing::create(['user_id' => $other->id, 'listing_id' => $othersListing->id, 'price_at_save' => $othersListing->price, 'created_at' => now()]);

    Livewire::actingAs($student)->test(BuyingHub::class)
        ->assertSee('My saved item')
        ->assertDontSee('Someone else\'s saved item');
});

it('alerts only on a saved listing whose price has genuinely dropped since saving', function () {
    $student = User::factory()->create();
    $dropped = Listing::factory()->create(['title' => 'Price dropped item', 'price' => 80]);
    $unchanged = Listing::factory()->create(['title' => 'Same price item', 'price' => 100]);

    SavedListing::create(['user_id' => $student->id, 'listing_id' => $dropped->id, 'price_at_save' => 120, 'created_at' => now()]);
    SavedListing::create(['user_id' => $student->id, 'listing_id' => $unchanged->id, 'price_at_save' => 100, 'created_at' => now()]);

    $response = Livewire::actingAs($student)->test(BuyingHub::class);

    $response->assertViewHas('alerts', fn ($alerts) => $alerts->count() === 1 && $alerts->first()->listing->title === 'Price dropped item');
});

it('lists sellers this student has actually completed a purchase from, and no one else', function () {
    $student = User::factory()->create();
    $pastSeller = User::factory()->create(['name' => 'Past Seller']);
    $strangerSeller = User::factory()->create(['name' => 'Stranger Seller']);

    Transaction::factory()->create(['buyer_id' => $student->id, 'seller_id' => $pastSeller->id, 'status' => 'COMPLETED']);
    Transaction::factory()->create(['buyer_id' => User::factory()->create()->id, 'seller_id' => $strangerSeller->id, 'status' => 'COMPLETED']);

    Livewire::actingAs($student)->test(BuyingHub::class)
        ->assertSee('Past Seller')
        ->assertDontSee('Stranger Seller');
});

it('does not count an incomplete transaction as "following" that seller', function () {
    $student = User::factory()->create();
    $seller = User::factory()->create(['name' => 'Pending Seller']);

    Transaction::factory()->create(['buyer_id' => $student->id, 'seller_id' => $seller->id, 'status' => 'RESERVED']);

    Livewire::actingAs($student)->test(BuyingHub::class)->assertDontSee('Pending Seller');
});

it('shows the student\'s own active listing count on the profile card', function () {
    $student = User::factory()->create();
    Listing::factory()->count(2)->create(['user_id' => $student->id]);
    Listing::factory()->sold()->create(['user_id' => $student->id]);

    Livewire::actingAs($student)->test(BuyingHub::class)
        ->assertSee('2 active listings');
});
