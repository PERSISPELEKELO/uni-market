<?php

namespace App\Notifications;

use App\Models\Rating;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: this app has no queue worker running in the default dev workflow,
// and the volume here does not need one. Revisit if that changes.
class ReviewModerated extends Notification
{
    use Queueable;

    public function __construct(
        protected Rating $rating,
        protected string $outcome // 'hidden' or 'restored'
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
        $line = $this->outcome === 'hidden'
            ? 'A moderator has hidden a review you left because it was found to violate platform rules.'
            : 'A moderator has restored a review you left after reviewing a report against it.';

        return (new MailMessage)
            ->subject('Your review on UniMarket has been moderated')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($line)
            ->action('View your account', route('account'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_moderated',
            'title' => 'Your review has been moderated',
            'message' => $this->outcome === 'hidden'
                ? 'A moderator hid a review you left.'
                : 'A moderator restored a review you left.',
            'url' => route('account'),
        ];
    }
}
