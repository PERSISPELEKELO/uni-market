<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SavedListing;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * A single bookmark button, embedded once per listing card wherever one
 * appears (the marketplace grid, carousels, the buying page). Kept as its
 * own tiny component - like notifications.bell or ratings.report-review -
 * so every page that shows a listing card gets save/unsave for free
 * without each one re-implementing the toggle.
 */
class SaveToggle extends Component
{
    public Listing $listing;

    public bool $saved = false;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing;
        $this->saved = $this->listing->isSavedBy(Auth::user());
    }

    public function toggle()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to save items.');
        }

        if ($this->listing->isOwnedBy(Auth::user())) {
            return null;
        }

        $user = Auth::user();

        $existing = SavedListing::where('user_id', $user->id)
            ->where('listing_id', $this->listing->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->saved = false;

            return null;
        }

        SavedListing::create([
            'user_id' => $user->id,
            'listing_id' => $this->listing->id,
            'price_at_save' => $this->listing->price,
            'created_at' => now(),
        ]);

        ListingEvent::create([
            'listing_id' => $this->listing->id,
            'user_id' => $user->id,
            'type' => ListingEvent::TYPE_SAVE,
            'created_at' => now(),
        ]);

        $this->saved = true;

        return null;
    }

    public function render()
    {
        return view('livewire.marketplace.save-toggle');
    }
}
