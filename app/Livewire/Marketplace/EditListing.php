<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\ManagesListingForm;
use App\Models\Listing;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EditListing extends Component
{
    use ManagesListingForm;

    public Listing $listing;

    public function mount(Listing $listing): void
    {
        $this->authorize('update', $listing);

        $this->listing = $listing;
        $this->form->fillFromListing($listing);
    }

    public function save()
    {
        $this->authorize('update', $this->listing);

        try {
            $this->form->update($this->listing);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            $this->dispatch('notify', type: 'error', message: 'We could not save your changes right now. Please try again in a moment.');

            return null;
        }

        app(AuditLoggerService::class)->log(
            'LISTING_UPDATED',
            'Listing',
            $this->listing->id,
            [
                'title' => $this->listing->title,
                'price' => $this->listing->price,
            ],
            Auth::user()
        );

        return redirect()->route('listings.show', $this->listing)
            ->with('success', 'Your changes have been saved.');
    }

    public function render()
    {
        return view('livewire.marketplace.edit-listing', [
            'categories' => $this->categories(),
        ])->layout('layouts.app', ['title' => 'Edit Listing - UniMarket']);
    }
}
