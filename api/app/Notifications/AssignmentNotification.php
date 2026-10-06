<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email someone gets when they're assigned something — a shift, an
 * incident, a compliance requirement, a client, care plan responsibilities,
 * a role. Visits keep their own VisitAssignedNotification. Built through
 * App\Support\AssignmentNotifier, which is what callers should use.
 *
 * Sent synchronously (no ShouldQueue) — this app has no queue worker
 * deployed, so a queued notification would never actually send.
 */
class AssignmentNotification extends Notification
{
    /**
     * @param  string  $kind  Short machine name for what was assigned (e.g. "shift") — lets tests and logs tell emails apart.
     * @param  list<string>  $lines  Body lines, in order.
     * @param  string  $actionPath  Frontend path the button opens, e.g. "/incidents".
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $subject,
        public readonly array $lines,
        public readonly string $actionText,
        public readonly string $actionPath,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hi {$notifiable->name},");

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($this->actionText, rtrim((string) config('app.frontend_url'), '/').$this->actionPath)
            ->line('If this looks wrong, contact your manager.');
    }
}
