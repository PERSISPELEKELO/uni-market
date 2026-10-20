<?php

namespace App\Livewire\Concerns;

use App\Livewire\Forms\ListingForm;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * Behaviour shared by the create and edit listing screens.
 *
 * @property ListingForm $form
 */
trait ManagesListingForm
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ListingForm $form;

    /**
     * Validate photos as soon as they are chosen so bad files are rejected before submitting.
     */
    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'form.images')) {
            return;
        }

        try {
            $this->form->validateOnly('images');
            $this->form->validateOnly('images.*');
        } catch (ValidationException $exception) {
            $this->form->images = [];
            $this->form->syncPhotoOrder();

            throw $exception;
        }

        // A fresh batch of photos was just selected (or replaced) - bring
        // photoOrder up to date so any newly added photos show up (at the
        // end, per the brief) without disturbing an order already chosen.
        $this->form->syncPhotoOrder();
    }

    public function removeNewPhoto(int $index): void
    {
        $this->form->removeNewImage($index);
    }

    public function removeExistingPhoto(int $index): void
    {
        $this->form->removeExistingImage($index);
    }

    /**
     * @param  array<int, string>  $order
     */
    public function reorderPhotos(array $order): void
    {
        $this->form->reorderPhotos($order);
    }

    public function setMainPhoto(string $token): void
    {
        $this->form->setMainPhoto($token);
    }

    public function movePhotoLeft(string $token): void
    {
        $this->form->movePhotoLeft($token);
    }

    public function movePhotoRight(string $token): void
    {
        $this->form->movePhotoRight($token);
    }

    /**
     * @return Collection<int, Category>
     */
    protected function categories()
    {
        return Category::orderBy('name')->get();
    }
}
