<?php

namespace App\Modules\Identity\Support;

class DefaultRoles
{
    /**
     * spatie/laravel-permission's "teams" feature requires a non-null team id
     * in its pivot tables' composite primary keys, so platform-wide roles
     * (not scoped to any real tenant) use this reserved sentinel instead of null.
     */
    public const PLATFORM_TEAM_ID = 0;

    public const PLATFORM_SUPER_ADMIN = 'Platform Super Admin';

    /**
     * Referenced directly (not just via TENANT_ROLES) since Phase 6's
     * StaffOnly middleware and portal-access grant both key off this exact
     * role name rather than a role list.
     */
    public const FAMILY_MEMBER = 'Family Member';

    public const TENANT_ROLES = [
        'Organization Owner',
        'Organization Admin',
        'Branch Manager',
        'Care Manager',
        'Care Coordinator',
        'Nurse',
        'Senior Carer',
        'Carer / Support Worker',
        'Doctor',
        'Therapist',
        'Pharmacist',
        'Finance Officer',
        'HR Officer',
        'Compliance Officer',
        'Receptionist',
        'Family Member',
        'Service User / Patient',
        'Auditor',
    ];
}
