<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class ListingShow extends Component
{
    public Listing $listing;

    public int $activeImageIndex = 0;

    public function mount(Listing $listing): void
    {
        abort_unless(Gate::allows('view', $listing), 404);

        $this->listing = $listing->load(['seller', 'category']);
    }

    public function setActiveImage(int $index): void
    {
        $this->activeImageIndex = max(0, min($index, count($this->listing->image_urls) - 1));
    }

    public function reserve(ReservationService $reservations)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to reserve this item.');
        }

        try {
            $reservations->reserve($this->listing, Auth::user());
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());

            return null;
        }

        $this->listing->refresh();
        $this->dispatch('notify', type: 'success', message: "You're on the interest list! The seller will reach out if they choose you.");

        return null;
    }

    /**
     * Seller action: choose one of the people who reserved this listing to sell to.
     */
    public function selectBuyer(int $reservationId, ReservationService $reservations)
    {
        if (! $this->listing->isOwnedBy(Auth::user())) {
            abort(403);
        }

        $reservation = Reservation::findOrFail($reservationId);

        try {
            $transaction = $reservations->selectBuyer($reservation, Auth::user());
        } catch (InvalidArgumentException $exception) {
            $this->listing->refresh();
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());

            return null;
        }

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Buyer selected! Arrange a meet-up and confirm the handover code once you receive it.');
    }

    public function contactSeller()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to message the seller.');
        }

        if ($this->listing->isOwnedBy(Auth::user())) {
            return null;
        }

        return redirect()->route('chat.thread', [
            'receiver' => $this->listing->user_id,
            'listing' => $this->listing->id,
        ]);
    }

    public function render()
    {
        $activeReservations = $this->listing->isOwnedBy(Auth::user())
            ? $this->listing->activeReservations()->get()
            : collect();

        return view('livewire.marketplace.listing-show', [
            'reservationCount' => $this->listing->reservations()->active()->count(),
            'hasReserved' => $this->listing->hasActiveReservationFrom(Auth::user()),
            'activeReservations' => $activeReservations,
        ])->layout('layouts.app', ['title' => $this->listing->title.' - UniMarket']);
    }
}
