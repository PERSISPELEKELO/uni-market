<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every student who saved a listing (other than whoever bought it)
 * once it's genuinely sold - see ListingObserver.
 */
class SavedListingSold extends Notification implements ShouldQueue
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
            ->subject('A listing you saved has sold')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('"'.$this->listing->title.'", a listing you saved, has been sold to another student.')
            ->line('Browse the marketplace to find something similar.')
            ->action('Browse the marketplace', route('listings.index'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'saved_listing_sold',
            'title' => 'Sold',
            'message' => '"'.$this->listing->title.'" (saved) has been sold.',
            'url' => route('listings.index'),
        ];
    }
}
