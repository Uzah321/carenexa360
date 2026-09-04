<?php

namespace App\Modules\Safeguarding\Support;

class SafeguardingRoles
{
    /**
     * Safeguarding is deliberately kept separate from ordinary incidents with
     * stricter access — only these roles may view or manage safeguarding
     * cases, regardless of tenant membership. This is the app's first real
     * *role*-based authorization check, layered on top of (never replacing)
     * the existing tenant-scoping checks.
     */
    public const ALLOWED = [
        'Organization Owner',
        'Organization Admin',
        'Care Manager',
        'Compliance Officer',
        'Auditor',
    ];
}
