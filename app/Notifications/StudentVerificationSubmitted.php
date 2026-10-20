<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: see StudentVerificationApproved for why.
class StudentVerificationSubmitted extends Notification
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
            ->subject('We received your student ID document')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Thanks for submitting your student ID document. An administrator will check it against your registered name and student number.')
            ->line("We'll email you as soon as a decision is made, usually within 24 hours.")
            ->action('View your verification status', route('verification.student.form'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_verification_submitted',
            'title' => 'Document received - under review',
            'message' => 'An administrator will review your student ID document shortly.',
            'url' => route('verification.student.form'),
        ];
    }
}
