<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: this app has no queue worker running in the default dev workflow,
// and the volume here does not need one. Revisit if that changes.
class StudentVerificationApproved extends Notification
{
    use Queueable;

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
            ->subject('You are now a Verified Student on UniMarket')
            ->greeting('Congratulations, '.$notifiable->name.'!')
            ->line('An administrator has reviewed your student ID document and approved your student verification.')
            ->line('Your profile now shows the Verified Student badge, which other students see when you buy, sell or message on UniMarket.')
            ->action('View your account', route('account'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_verification_approved',
            'title' => 'You are a Verified Student',
            'message' => 'Your student ID document was approved. Your Verified Student badge is now active.',
            'url' => route('account'),
        ];
    }
}
