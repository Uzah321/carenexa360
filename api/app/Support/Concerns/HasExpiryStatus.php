<?php

namespace App\Support\Concerns;

use Carbon\Carbon;

trait HasExpiryStatus
{
    /**
     * valid / expiring_soon / expired / no_expiry, computed from a date
     * against "now" rather than stored — a record's freshness should never
     * reflect a stale snapshot from when it was last saved.
     *
     * Carbon 3 (shipped with Laravel 11) changed diffInDays()'s default
     * $absolute argument from true to false, so a far-future date's diff
     * comes back negative unless absolute:true is passed explicitly — that
     * bug (see TrainingRecord's original standalone implementation) is why
     * this lives in one shared place instead of being copy-pasted per model.
     */
    protected function computeExpiryStatus(?Carbon $date, int $expiringSoonWindowDays = 30): string
    {
        if (! $date) {
            return 'no_expiry';
        }

        if ($date->isPast()) {
            return 'expired';
        }

        if ($date->diffInDays(now(), absolute: true) <= $expiringSoonWindowDays) {
            return 'expiring_soon';
        }

        return 'valid';
    }
}
