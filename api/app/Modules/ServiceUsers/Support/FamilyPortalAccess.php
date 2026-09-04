<?php

namespace App\Modules\ServiceUsers\Support;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUserContact;

class FamilyPortalAccess
{
    /**
     * The service users a portal login is allowed to see — one login can be
     * linked to more than one relative via separate ServiceUserContact rows.
     *
     * @return array<int, int>
     */
    public static function serviceUserIdsFor(User $user): array
    {
        return ServiceUserContact::where('user_id', $user->id)
            ->pluck('service_user_id')
            ->unique()
            ->values()
            ->all();
    }
}
