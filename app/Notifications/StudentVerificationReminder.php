<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Not queued: see StudentVerificationApproved for why.
class StudentVerificationReminder extends Notification
{
    use Queueable;

    public const STAGE_24_HOURS = '24h';

    public const STAGE_3_DAYS = '3d';

    public function __construct(
        public string $stage
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
            ->greeting('Hello '.$notifiable->name.',')
            ->line("You can browse UniMarket already, but you'll need a verified student ID before you can buy, sell or message other students.");

        if ($this->stage === self::STAGE_3_DAYS) {
            $mail->subject("Don't forget to upload your student ID");
            $mail->line("It's been a few days since you signed up and we still haven't received your student ID document.");
        } else {
            $mail->subject('One more step: upload your student ID');
            $mail->line("You confirmed your email, but haven't uploaded your student ID yet.");
        }

        return $mail
            ->line('It only takes a minute - a clear photo of the front of your card is all we need.')
            ->action('Upload my student ID', route('verification.student.form'));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_verification_reminder',
            'title' => 'Upload your student ID to start buying and selling',
            'message' => "You haven't uploaded your student ID yet - it only takes a minute.",
            'url' => route('verification.student.form'),
        ];
    }
}
