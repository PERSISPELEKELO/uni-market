<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\ManagesListingForm;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\NewListingPosted;
use App\Services\AuditLoggerService;
use App\Services\Recommendations\ContentSimilarityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CreateListing extends Component
{
    use ManagesListingForm;

    public function mount(): void
    {
        $this->authorize('create', Listing::class);
    }

    public function save()
    {
        return $this->persist(Listing::STATUS_ACTIVE);
    }

    public function saveAsDraft()
    {
        return $this->persist(Listing::STATUS_DRAFT);
    }

    private function persist(string $status)
    {
        $this->authorize('create', Listing::class);

        try {
            $listing = $this->form->store(Auth::user(), $status);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            $this->dispatch('notify', type: 'error', message: 'We could not save your listing right now. Please try again in a moment.');

            return null;
        }

        app(AuditLoggerService::class)->log(
            $status === Listing::STATUS_DRAFT ? 'LISTING_DRAFTED' : 'LISTING_CREATED',
            'Listing',
            $listing->id,
            [
                'title' => $listing->title,
                'price' => $listing->price,
                'category_id' => $listing->category_id,
            ],
            Auth::user()
        );

        if ($status === Listing::STATUS_DRAFT) {
            return redirect()->route('listings.mine')
                ->with('success', 'Saved as a draft - publish it from My Listings whenever you are ready.');
        }

        app(ContentSimilarityService::class)->refreshFor($listing);
        $this->notifySubscribedStudents($listing);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Your listing is live on the campus marketplace!');
    }

    /**
     * Students who opted in via the marketplace's "Get notified when items
     * arrive" button - never the poster themselves.
     */
    private function notifySubscribedStudents(Listing $listing): void
    {
        $subscribers = User::where('notify_new_listings', true)
            ->where('id', '!=', Auth::id())
            ->get();

        if ($subscribers->isNotEmpty()) {
            Notification::send($subscribers, new NewListingPosted($listing));
        }
    }

    public function render()
    {
        return view('livewire.marketplace.create-listing', [
            'categories' => $this->categories(),
        ])->layout('layouts.app', ['title' => 'Sell an Item - UniMarket']);
    }
}
