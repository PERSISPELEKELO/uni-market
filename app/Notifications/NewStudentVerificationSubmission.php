<?php

namespace App\Notifications;

use App\Models\StudentVerificationDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admin/governance staff when a submission is waiting and no one
 * has opened the review queue recently - see
 * StudentVerification::notifyAdminsIfQueueUnattended() for the throttling.
 * Not queued: see StudentVerificationApproved for why.
 */
class NewStudentVerificationSubmission extends Notification
{
    use Queueable;

    public function __construct(
        protected StudentVerificationDocument $document
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A student verification is waiting for review')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A student has submitted an ID document for verification and the review queue has not been opened in a while.')
            ->action('Open the review queue', route('filament.admin.resources.student-verification-documents.index'));
    }
}
