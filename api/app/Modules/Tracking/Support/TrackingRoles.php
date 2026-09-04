<?php

namespace App\Modules\Tracking\Support;

class TrackingRoles
{
    /**
     * Who can view the live carer-tracking map. Deliberately narrower than
     * AnalyticsRoles — Finance Officer has no operational reason to see
     * carer GPS positions, so it's excluded here even though it can see
     * Today's stats.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
    ];
}
