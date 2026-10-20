<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every other student who saved a listing the moment someone else
 * reserves it - see ReservationService::reserve(). A nudge to act before
 * the seller picks a buyer, not a guarantee the item is already gone.
 */
class SavedListingAboutToBeReserved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Listing $listing
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A listing you saved is getting interest')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Someone just reserved "'.$this->listing->title.'", a listing you saved. The seller hasn\'t chosen a buyer yet, but you may want to reserve it too if you\'re still interested.')
            ->action('View listing', route('listings.show', $this->listing));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'saved_listing_about_to_be_reserved',
            'title' => 'Getting interest',
            'message' => '"'.$this->listing->title.'" (saved) just got a reservation from another student.',
            'url' => route('listings.show', $this->listing),
        ];
    }
}
