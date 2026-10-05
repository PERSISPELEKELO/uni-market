<?php

namespace App\Notifications;

use App\Models\Rating;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: this app has no queue worker running in the default dev workflow,
// and the volume here does not need one. Revisit if that changes.
class ReviewReported extends Notification
{
    use Queueable;

    public function __construct(
        protected Rating $rating
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
            ->subject('Your review on UniMarket has been reported')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A review you left has been reported and will be checked by a moderator.')
            ->line('No action has been taken against you yet - this is just to let you know a review is under review.')
            ->action('View your account', route('account'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_reported',
            'title' => 'Your review has been reported',
            'message' => 'A moderator will check a review you left before any action is taken.',
            'url' => route('account'),
        ];
    }
}
