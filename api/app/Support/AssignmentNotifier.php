<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails a person when they're assigned something. Sending is deferred
 * until after the response and never lets a mail failure (bad SendGrid
 * creds, an unverified sender, a network blip) break the action that made
 * the assignment — the record is already saved either way.
 */
class AssignmentNotifier
{
    public static function notify(User|int|null $assignee, AssignmentNotification $notification): void
    {
        if ($assignee === null) {
            return;
        }

        // Registered directly rather than via dispatch()->afterResponse(),
        // which wraps the closure in a serialisable job and loses the
        // by-reference flag. Terminating callbacks are never cleared from the
        // app, so anything handling several requests in one process (tests,
        // Octane) would re-run earlier ones on each later request — the flag
        // makes each send happen at most once.
        $sent = false;

        app()->terminating(function () use ($assignee, $notification, &$sent) {
            if ($sent) {
                return;
            }
            $sent = true;

            $user = $assignee instanceof User ? $assignee : User::find($assignee);

            if (! $user?->email) {
                return;
            }

            try {
                Notification::send($user, $notification);
            } catch (\Throwable $e) {
                Log::warning('Failed to send assignment email.', [
                    'kind' => $notification->kind,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Notifies only when the assignee is new: set, and different from who
     * held it before. Unassigning, or an edit that leaves it untouched, has
     * no one new to tell.
     */
    public static function notifyIfChanged(mixed $previous, mixed $current, AssignmentNotification $notification): void
    {
        // IDs arrive as ints from JSON but strings from form posts.
        $previous = $previous === null || $previous === '' ? null : (int) $previous;
        $current = $current === null || $current === '' ? null : (int) $current;

        if ($current !== null && $current !== $previous) {
            self::notify($current, $notification);
        }
    }
}
