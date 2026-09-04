<?php

namespace App\Modules\Staff\Support;

class StaffRoles
{
    /**
     * Who can manage staff records — create/edit profiles, assign roles,
     * change employment status. The staff *list* itself stays readable by
     * everyone (it backs carer-assignment and witness-selection dropdowns
     * across the app), but these are the only roles that can mutate it or
     * see HR-sensitive fields like hourly_rate.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'HR Officer',
    ];
}
