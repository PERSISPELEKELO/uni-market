<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Services\AuditLoggerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyListings extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function delete(int $listingId): void
    {
        $listing = Listing::where('user_id', Auth::id())->findOrFail($listingId);

        $this->authorize('delete', $listing);

        $listing->delete();

        app(AuditLoggerService::class)->log(
            'LISTING_DELETED',
            'Listing',
            $listing->id,
            ['title' => $listing->title],
            Auth::user()
        );

        $this->dispatch('notify', type: 'success', message: 'Your listing has been removed.');
    }

    public function render()
    {
        $listings = Listing::where('user_id', Auth::id())
            ->with('category')
            ->latest()
            ->paginate(8);

        return view('livewire.marketplace.my-listings', [
            'listings' => $listings,
        ])->layout('layouts.app', ['title' => 'My Listings - UniMarket']);
    }
}
