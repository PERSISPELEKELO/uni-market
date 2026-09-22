<?php

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ListingReserved;
use App\Notifications\ReservationSelected;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Notification;

it('reserves a listing and notifies the seller', function () {
    Notification::fake();
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);
    $buyer = User::factory()->create();

    $reservation = app(ReservationService::class)->reserve($listing, $buyer);

    expect($reservation->buyer_id)->toBe($buyer->id)
        ->and($reservation->status)->toBe(Reservation::STATUS_ACTIVE)
        ->and($listing->fresh()->status)->toBe('active');

    Notification::assertSentTo($seller, ListingReserved::class);
});

it('returns the existing reservation on a second call instead of creating a duplicate or re-notifying', function () {
    Notification::fake();
    $listing = Listing::factory()->create();
    $buyer = User::factory()->create();
    $service = app(ReservationService::class);

    $first = $service->reserve($listing, $buyer);
    $second = $service->reserve($listing, $buyer);

    expect($second->id)->toBe($first->id)
        ->and(Reservation::count())->toBe(1);

    Notification::assertSentToTimes($listing->seller, ListingReserved::class, 1);
});

it('refuses to reserve your own listing or an inactive one', function () {
    $seller = User::factory()->create();
    $ownListing = Listing::factory()->create(['user_id' => $seller->id]);
    $pendingListing = Listing::factory()->pending()->create();

    expect(fn () => app(ReservationService::class)->reserve($ownListing, $seller))
        ->toThrow(InvalidArgumentException::class, 'cannot reserve your own listing');

    expect(fn () => app(ReservationService::class)->reserve($pendingListing, User::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'no longer available');

    expect(Reservation::count())->toBe(0);
});

it('lets several buyers reserve the same listing at once', function () {
    $listing = Listing::factory()->create();
    $service = app(ReservationService::class);

    $service->reserve($listing, User::factory()->create());
    $service->reserve($listing, User::factory()->create());
    $service->reserve($listing, User::factory()->create());

    expect($listing->reservations()->active()->count())->toBe(3)
        ->and($listing->fresh()->status)->toBe('active');
});

describe('selecting a buyer', function () {
    it('starts a transaction, cancels the other reservations, and notifies the chosen buyer', function () {
        Notification::fake();
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id, 'price' => 250]);
        $chosen = Reservation::factory()->for($listing)->create();
        $others = Reservation::factory()->for($listing)->count(2)->create();

        $transaction = app(ReservationService::class)->selectBuyer($chosen, $seller);

        expect($transaction->buyer_id)->toBe($chosen->buyer_id)
            ->and($transaction->seller_id)->toBe($seller->id)
            ->and((float) $transaction->amount)->toBe(250.0)
            ->and($transaction->status)->toBe('initiated')
            ->and($listing->fresh()->status)->toBe('pending')
            ->and($chosen->fresh()->status)->toBe(Reservation::STATUS_SELECTED);

        foreach ($others as $other) {
            expect($other->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
        }

        Notification::assertSentTo($chosen->buyer, ReservationSelected::class);
        Notification::assertNotSentTo($others->first()->buyer, ReservationSelected::class);
    });

    it('refuses when the caller does not own the listing', function () {
        $listing = Listing::factory()->create();
        $reservation = Reservation::factory()->for($listing)->create();

        expect(fn () => app(ReservationService::class)->selectBuyer($reservation, User::factory()->create()))
            ->toThrow(InvalidArgumentException::class, 'your own listing');

        expect(Transaction::count())->toBe(0);
    });

    it('refuses a reservation that is not active', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $reservation = Reservation::factory()->for($listing)->create(['status' => Reservation::STATUS_CANCELLED]);

        expect(fn () => app(ReservationService::class)->selectBuyer($reservation, $seller))
            ->toThrow(InvalidArgumentException::class);

        expect(Transaction::count())->toBe(0);
    });

    it('refuses once the listing is no longer active, even for a still-active reservation', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->pending()->create(['user_id' => $seller->id]);
        $reservation = Reservation::factory()->for($listing)->create();

        expect(fn () => app(ReservationService::class)->selectBuyer($reservation, $seller))
            ->toThrow(InvalidArgumentException::class);
    });

    it('only lets one of two concurrent selections win', function () {
        $seller = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $seller->id]);
        $first = Reservation::factory()->for($listing)->create();
        $second = Reservation::factory()->for($listing)->create();

        app(ReservationService::class)->selectBuyer($first, $seller);

        expect(fn () => app(ReservationService::class)->selectBuyer($second, $seller))
            ->toThrow(InvalidArgumentException::class);

        expect(Transaction::count())->toBe(1)
            ->and($first->fresh()->status)->toBe(Reservation::STATUS_SELECTED);
    });
});
