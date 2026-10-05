<?php

namespace App\Notifications;

use App\Models\Rating;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: this app has no queue worker running in the default dev workflow,
// and the volume here does not need one. Revisit if that changes.
class NewRatingReceived extends Notification
{
    use Queueable;

    public function __construct(
        protected Rating $rating
    ) {
        $this->rating->loadMissing('rater');
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
            ->subject('You received a new rating on UniMarket')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->rating->rater->name.' rated you '.$this->rating->stars.' out of 5 for a completed transaction.')
            ->when($this->rating->comment, fn (MailMessage $mail) => $mail->line('"'.$this->rating->comment.'"'))
            ->action('View your profile', route('profiles.show', $notifiable));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_rating_received',
            'title' => 'New rating received',
            'message' => $this->rating->rater->name.' rated you '.$this->rating->stars.' out of 5.',
            'url' => route('profiles.show', $notifiable),
        ];
    }
}
