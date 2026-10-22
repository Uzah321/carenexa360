<?php

namespace App\Modules\MedicalHistory\Support;

class MedicalHistoryRoles
{
    /**
     * Who can sign a client's medical history off as reviewed, and remove
     * an entry someone else recorded — anyone can record entries and remove
     * their own. The care-note moderators plus the clinicians on staff.
     *
     * Mirrored in web/src/lib/types.ts as MEDICAL_HISTORY_LEAD_ROLES.
     */
    public const CLINICAL_LEADS = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Nurse',
        'Doctor',
    ];
}
