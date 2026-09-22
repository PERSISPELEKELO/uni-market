<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ListingReserved;
use App\Notifications\ReservationSelected;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A reservation just expresses interest: the listing stays public and visible,
 * showing how many people have reserved it, until the seller picks one buyer
 * to actually sell to. Selecting a buyer is what starts the existing escrow
 * transaction/handover workflow - nothing about that workflow changes here.
 */
class ReservationService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Reserve a listing for a buyer. Reserving twice is a harmless no-op:
     * the existing reservation is returned and the seller is not re-notified.
     *
     * @throws InvalidArgumentException
     */
    public function reserve(Listing $listing, User $buyer): Reservation
    {
        if ($listing->isOwnedBy($buyer)) {
            throw new InvalidArgumentException('You cannot reserve your own listing.');
        }

        if ($listing->status !== Listing::STATUS_ACTIVE) {
            throw new InvalidArgumentException('This item is no longer available to reserve.');
        }

        $existing = $listing->reservations()->active()->where('buyer_id', $buyer->id)->first();

        if ($existing) {
            return $existing;
        }

        $reservation = DB::transaction(function () use ($listing, $buyer): Reservation {
            return Reservation::create([
                'listing_id' => $listing->id,
                'buyer_id' => $buyer->id,
                'status' => Reservation::STATUS_ACTIVE,
            ]);
        });

        $this->auditLogger->recordAction(
            $buyer,
            'LISTING_RESERVED',
            'Listing',
            (string) $listing->id,
            ['reservation_id' => $reservation->id, 'buyer_id' => $buyer->id]
        );

        try {
            $listing->seller->notify(new ListingReserved($reservation));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $reservation;
    }

    /**
     * The seller picks one reservation to sell to: this starts the escrow
     * transaction, cancels every other active reservation on the listing, and
     * notifies the chosen buyer. Refuses if the reservation is no longer valid
     * or the listing already moved on, guarded by a row lock against races
     * (e.g. the seller double-clicking two different reservations at once).
     *
     * @throws InvalidArgumentException
     */
    public function selectBuyer(Reservation $reservation, User $seller): Transaction
    {
        $reservation->loadMissing('listing');

        if (! $reservation->listing || $reservation->listing->user_id !== $seller->id) {
            throw new InvalidArgumentException('You can only select a buyer for your own listing.');
        }

        $transaction = DB::transaction(function () use ($reservation, $seller): ?Transaction {
            $listing = Listing::whereKey($reservation->listing_id)->lockForUpdate()->first();
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

            if (! $listing || $listing->status !== Listing::STATUS_ACTIVE
                || ! $lockedReservation || ! $lockedReservation->isActive()) {
                return null;
            }

            $transaction = Transaction::create([
                'listing_id' => $listing->id,
                'buyer_id' => $lockedReservation->buyer_id,
                'seller_id' => $seller->id,
                'amount' => $listing->price,
                'status' => 'initiated',
            ]);

            $listing->update(['status' => Listing::STATUS_PENDING]);
            $lockedReservation->update(['status' => Reservation::STATUS_SELECTED]);

            $listing->reservations()
                ->where('id', '!=', $lockedReservation->id)
                ->active()
                ->update(['status' => Reservation::STATUS_CANCELLED]);

            return $transaction;
        });

        if (! $transaction) {
            throw new InvalidArgumentException('This reservation is no longer available. The listing or reservation may have changed.');
        }

        $this->auditLogger->recordAction(
            $seller,
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $transaction->id,
            [
                'listing_id' => $transaction->listing_id,
                'amount' => $transaction->amount,
                'buyer_id' => $transaction->buyer_id,
                'seller_id' => $transaction->seller_id,
                'reservation_id' => $reservation->id,
            ]
        );

        try {
            $transaction->buyer->notify(new ReservationSelected($transaction));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $transaction;
    }
}
