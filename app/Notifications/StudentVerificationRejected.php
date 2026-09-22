<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: see StudentVerificationApproved for why.
class StudentVerificationRejected extends Notification
{
    use Queueable;

    public function __construct(
        protected bool $canResubmit,
        protected ?string $reason
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
        $mail = (new MailMessage)
            ->subject('An update on your UniMarket student verification')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An administrator reviewed the student ID document you submitted and it was not approved.');

        if ($this->reason) {
            $mail->line('Reason given: '.$this->reason);
        }

        return $mail
            ->line('You can upload a new document at any time.')
            ->action('Submit a new document', route('verification.student.form'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_verification_rejected',
            'title' => $this->canResubmit ? 'Please resubmit your student document' : 'Your student verification was not approved',
            'message' => $this->reason ?: 'Please review your document and try again.',
            'url' => route('verification.student.form'),
        ];
    }
}
