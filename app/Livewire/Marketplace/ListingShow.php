<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function initiatePurchase()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to reserve this item.');
        }

        if ($this->listing->isOwnedBy(Auth::user())) {
            $this->dispatch('notify', type: 'error', message: 'You cannot purchase your own listing.');

            return null;
        }

        $transaction = DB::transaction(function (): ?Transaction {
            $listing = Listing::whereKey($this->listing->id)->lockForUpdate()->first();

            if (! $listing || $listing->status !== Listing::STATUS_ACTIVE) {
                return null;
            }

            $transaction = Transaction::create([
                'listing_id' => $listing->id,
                'buyer_id' => Auth::id(),
                'seller_id' => $listing->user_id,
                'amount' => $listing->price,
                'status' => 'initiated',
            ]);

            $listing->update(['status' => Listing::STATUS_PENDING]);

            return $transaction;
        });

        if (! $transaction) {
            $this->listing->refresh();
            $this->dispatch('notify', type: 'error', message: 'Sorry, this item is no longer available.');

            return null;
        }

        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $transaction->id,
            [
                'listing_id' => $transaction->listing_id,
                'amount' => $transaction->amount,
                'buyer_id' => $transaction->buyer_id,
                'seller_id' => $transaction->seller_id,
            ]
        );

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Item reserved! Arrange a meet-up with the seller and share your handover code once you have the item.');
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
        return view('livewire.marketplace.listing-show')
            ->layout('layouts.app', ['title' => $this->listing->title.' - UniMarket']);
    }
}
