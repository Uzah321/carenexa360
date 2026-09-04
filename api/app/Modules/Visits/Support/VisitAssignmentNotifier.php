<?php

namespace App\Modules\Visits\Support;

use App\Models\User;
use App\Modules\Visits\Models\Visit;
use App\Notifications\VisitAssignedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VisitAssignmentNotifier
{
    /**
     * Emails the assigned carer. Never lets a mail failure (bad SendGrid
     * creds, an unverified sender, a network blip) break the scheduling
     * action that triggered it — the visit is already saved either way.
     */
    public static function notify(Visit $visit, int $additionalOccurrences = 0): void
    {
        /** @var User|null $carer */
        $carer = $visit->relationLoaded('carer') ? $visit->carer : $visit->carer()->first();

        if (! $carer) {
            return;
        }

        try {
            Notification::send($carer, new VisitAssignedNotification($visit, $additionalOccurrences));
        } catch (\Throwable $e) {
            Log::warning('Failed to send visit-assignment email.', [
                'visit_id' => $visit->id,
                'carer_id' => $carer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A recurring booking creates one Visit row per occurrence — group by
     * carer so each carer gets a single summary email instead of one per
     * date, and notify from the earliest occurrence assigned to them.
     */
    public static function notifyBatch(iterable $visits): void
    {
        $byCarer = collect($visits)
            ->filter(fn (Visit $visit) => $visit->carer_id !== null)
            ->groupBy('carer_id');

        foreach ($byCarer as $carerVisits) {
            $sorted = $carerVisits->sortBy('visit_date')->values();
            self::notify($sorted->first(), $sorted->count() - 1);
        }
    }
}
