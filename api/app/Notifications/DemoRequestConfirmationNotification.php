<?php

namespace App\Notifications;

use App\Modules\Marketing\Models\DemoRequest;
use App\Modules\Marketing\Support\DemoTenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously (no ShouldQueue) — this app has no queue worker
 * deployed, so a queued notification would just sit in the jobs table
 * forever and never actually send.
 */
class DemoRequestConfirmationNotification extends Notification
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
        return (new MailMessage)
            ->subject('Your CareNexa360 demo request')
            ->greeting("Hi {$this->demoRequest->name},")
            ->line("Thanks for your interest in CareNexa360 for {$this->demoRequest->organization_name} — we've got your request and someone from our team will be in touch shortly.")
            ->line('In the meantime, you don\'t have to wait — log in to a live demo environment pre-loaded with realistic sample data (staff, service users, schedules, care plans, medications, and more) and explore it yourself.')
            ->line('**Email:** '.DemoTenant::LOGIN_EMAIL)
            ->line('**Password:** '.DemoTenant::LOGIN_PASSWORD)
            ->action('Log In to the Demo', DemoTenant::LOGIN_URL)
            ->line('This is a separate sandbox environment kept apart from real customer data, shared with other prospective customers exploring it too — so please don\'t enter any real personal or client data.');
    }
}
