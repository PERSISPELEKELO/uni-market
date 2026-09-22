<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a seller when a buyer reserves one of their listings.
 * Not queued - see StudentVerificationApproved for why.
 */
class ListingReserved extends Notification
{
    use Queueable;

    public function __construct(
        protected Reservation $reservation
    ) {
        $this->reservation->loadMissing(['listing', 'buyer']);
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
        return (new MailMessage)
            ->subject('New reservation on your UniMarket listing')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->reservation->buyer->name.' has reserved your listing "'.$this->reservation->listing->title.'".')
            ->line('Reserving does not sell the item automatically - open your listing to see everyone interested and choose who to sell to.')
            ->action('View reservations', route('listings.show', $this->reservation->listing));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'listing_reserved',
            'title' => 'New reservation',
            'message' => $this->reservation->buyer->name.' reserved "'.$this->reservation->listing->title.'".',
            'url' => route('listings.show', $this->reservation->listing),
        ];
    }
}
