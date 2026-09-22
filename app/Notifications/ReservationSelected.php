<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer a seller picks out of everyone who reserved a listing.
 * Not queued - see StudentVerificationApproved for why.
 */
class ReservationSelected extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction
    ) {
        $this->transaction->loadMissing(['listing', 'seller']);
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
            ->subject('You were chosen for a UniMarket item!')
            ->greeting('Good news, '.$notifiable->name.'!')
            ->line($this->transaction->seller->name.' chose you to buy "'.$this->transaction->listing->title.'".')
            ->line('Arrange a meet-up, then share your 6-digit handover code with the seller once you have the item.')
            ->action('Go to your transaction', route('transactions.tracker', $this->transaction));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reservation_selected',
            'title' => 'You were chosen!',
            'message' => $this->transaction->seller->name.' picked you for "'.$this->transaction->listing->title.'". Arrange your meet-up.',
            'url' => route('transactions.tracker', $this->transaction),
        ];
    }
}
