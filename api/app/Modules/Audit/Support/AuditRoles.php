<?php

namespace App\Modules\Audit\Support;

class AuditRoles
{
    /**
     * Who can view the tenant-wide audit trail. The Auditor role exists
     * specifically for this, so it's included even though it's excluded
     * from most other admin-facing modules.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Compliance Officer',
        'Auditor',
    ];
}
