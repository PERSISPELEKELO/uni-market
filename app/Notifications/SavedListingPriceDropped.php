<?php

namespace App\Notifications;

use App\Models\SavedListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once to a student when a listing they saved genuinely drops in price
 * below what it was when they saved it - see
 * SendSavedListingPriceDropAlerts (routes/console.php) for how "once" is
 * enforced.
 */
class SavedListingPriceDropped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SavedListing $savedListing
    ) {
        $this->savedListing->loadMissing('listing');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $listing = $this->savedListing->listing;

        return (new MailMessage)
            ->subject('Price drop on a listing you saved')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('"'.$listing->title.'" just dropped from K'.number_format((float) $this->savedListing->price_at_save, 2).' to K'.number_format((float) $listing->price, 2).'.')
            ->action('View listing', route('listings.show', $listing));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $listing = $this->savedListing->listing;

        return [
            'type' => 'saved_listing_price_dropped',
            'title' => 'Price drop',
            'message' => '"'.$listing->title.'" dropped to K'.number_format((float) $listing->price, 2).'.',
            'url' => route('listings.show', $listing),
        ];
    }
}
