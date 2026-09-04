<?php

namespace App\Notifications;

use App\Modules\Visits\Models\Visit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously (no ShouldQueue) — this app has no queue worker
 * deployed, so a queued notification would just sit in the jobs table
 * forever and never actually send.
 */
class VisitAssignedNotification extends Notification
{
    /** @param int $additionalOccurrences Other dates in the same recurring series, already assigned to this carer — folded into one email rather than sending one per occurrence. */
    public function __construct(
        private readonly Visit $visit,
        private readonly int $additionalOccurrences = 0,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visit = $this->visit->loadMissing('serviceUser');
        $serviceUserName = trim("{$visit->serviceUser?->first_name} {$visit->serviceUser?->last_name}") ?: 'a service user';
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $mail = (new MailMessage)
            ->subject($this->additionalOccurrences > 0 ? 'New recurring visits assigned' : 'New visit assigned')
            ->greeting("Hi {$notifiable->name},")
            ->line("You've been assigned to care for {$serviceUserName}.")
            ->line("Date: {$visit->visit_date->toDateString()}")
            ->line("Time: {$visit->start_time}–{$visit->end_time}");

        if ($this->additionalOccurrences > 0) {
            $mail->line("Plus {$this->additionalOccurrences} more occurrence".($this->additionalOccurrences === 1 ? '' : 's').' in this recurring series — check the schedule for the full list.');
        }

        if (! empty($visit->care_tasks)) {
            $mail->line('Care tasks: '.implode(', ', $visit->care_tasks));
        }

        return $mail
            ->action('View Schedule', $frontendUrl.'/schedule')
            ->line('If anything about this visit looks wrong, contact your care coordinator.');
    }
}
