<?php

namespace App\Notifications;

use App\Modules\Marketing\Models\DemoRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously (no ShouldQueue) — this app has no queue worker
 * deployed, so a queued notification would just sit in the jobs table
 * forever and never actually send.
 */
class DemoRequestReceivedNotification extends Notification
{
    public function __construct(private readonly DemoRequest $demoRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("New demo request — {$this->demoRequest->organization_name}")
            ->greeting('New demo request')
            ->line("Organization: {$this->demoRequest->organization_name}")
            ->line("Contact: {$this->demoRequest->name}")
            ->line("Email: {$this->demoRequest->email}");

        if ($this->demoRequest->phone) {
            $mail->line("Phone: {$this->demoRequest->phone}");
        }

        if ($this->demoRequest->message) {
            $mail->line("Message: {$this->demoRequest->message}");
        }

        return $mail->action('Reply', 'mailto:'.$this->demoRequest->email);
    }
}
