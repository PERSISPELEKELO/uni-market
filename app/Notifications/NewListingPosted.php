<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to students who opted in via the marketplace's "Get notified when
 * items arrive" button (see CreateListing::persist()). Queued, since this
 * can go out to many subscribers at once for a single new listing.
 */
class NewListingPosted extends Notification implements ShouldQueue
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
            ->subject('New on UniMarket: '.$this->listing->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new item was just listed on UniMarket: "'.$this->listing->title.'" for K'.number_format((float) $this->listing->price, 2).'.')
            ->action('View listing', route('listings.show', $this->listing))
            ->line('You can turn these emails off any time from the marketplace page.');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_listing_posted',
            'title' => 'New listing',
            'message' => '"'.$this->listing->title.'" was just listed for K'.number_format((float) $this->listing->price, 2).'.',
            'url' => route('listings.show', $this->listing),
        ];
    }
}
