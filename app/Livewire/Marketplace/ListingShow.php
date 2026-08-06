<?php

namespace App\Livewire\Marketplace;

use Livewire\Component;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class ListingShow extends Component
{
    public Listing $listing;
    public int $activeImageIndex = 0;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing->load(['seller', 'category']);
    }

    public function setActiveImage(int $index): void
    {
        $this->activeImageIndex = $index;
    }

    public function initiatePurchase()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to initiate a purchase.');
        }

        if ($this->listing->user_id === Auth::id()) {
            session()->flash('error', 'You cannot purchase your own listing.');
            return;
        }

        if ($this->listing->status !== 'active') {
            session()->flash('error', 'This item is no longer available.');
            return;
        }

        // Create transaction
        $transaction = Transaction::create([
            'listing_id' => $this->listing->id,
            'buyer_id' => Auth::id(),
            'seller_id' => $this->listing->user_id,
            'amount' => $this->listing->price,
            'status' => 'initiated',
        ]);

        // Mark listing as pending
        $this->listing->update(['status' => 'pending']);

        // Log Audit Trail
        app(\App\Services\AuditLoggerService::class)->recordAction(
            Auth::user(),
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $transaction->id,
            [
                'listing_id' => $this->listing->id,
                'amount' => $this->listing->price,
                'buyer_id' => Auth::id(),
                'seller_id' => $this->listing->user_id,
            ]
        );

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Transaction initiated successfully! You can now arrange meeting details with the seller.');
    }

    public function contactSeller()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return redirect()->route('chat.thread', [
            'receiver' => $this->listing->user_id,
            'listing' => $this->listing->id,
        ]);
    }

    public function render()
    {
        return view('livewire.marketplace.listing-show')
            ->layout('layouts.app', ['title' => $this->listing->title . ' - UniMarket']);
    }
}
