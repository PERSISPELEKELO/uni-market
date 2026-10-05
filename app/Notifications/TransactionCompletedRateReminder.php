<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: this app has no queue worker running in the default dev workflow,
// and the volume here does not need one. Revisit if that changes.
class TransactionCompletedRateReminder extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
        protected User $otherParty
    ) {
        $this->transaction->loadMissing('listing');
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
        $item = $this->transaction->listing->title ?? 'your item';

        return (new MailMessage)
            ->subject('Your UniMarket transaction is complete - please rate your experience')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your transaction for "'.$item.'" with '.$this->otherParty->name.' is now complete.')
            ->line('Let other students know how it went by rating your experience.')
            ->action('Rate your experience', route('transactions.tracker', $this->transaction));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transaction_completed_rate_reminder',
            'title' => 'Transaction completed',
            'message' => 'Your transaction is complete. Rate your experience with '.$this->otherParty->name.'.',
            'url' => route('transactions.tracker', $this->transaction),
        ];
    }
}
