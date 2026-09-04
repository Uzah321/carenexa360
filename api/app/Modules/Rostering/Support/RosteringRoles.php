<?php

namespace App\Modules\Rostering\Support;

class RosteringRoles
{
    /**
     * Who can view and manage staff shifts. Care Coordinator is included
     * deliberately — scheduling shifts is that role's core job in the demo
     * data (see DemoDataSeeder), unlike other Care-Management-only roles.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Care Coordinator',
    ];
}
