<?php

namespace App\Modules\Reports\Support;

class ReportRoles
{
    /**
     * Broader than AnalyticsRoles/TrackingRoles — Compliance Officer and
     * HR Officer both have a genuine reason to pull these reports even
     * though they don't see the live Today dashboard or carer GPS map.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Compliance Officer',
        'HR Officer',
    ];
}
