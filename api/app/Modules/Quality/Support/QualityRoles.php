<?php

namespace App\Modules\Quality\Support;

/**
 * Who can see and manage complaints and spot checks. Complaints carry
 * personal details about clients, families and staff, so this is the
 * management tier rather than every carer. Mirrored in web QUALITY_ROLES.
 */
class QualityRoles
{
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Care Coordinator',
        'Compliance Officer',
        'Auditor',
    ];
}
