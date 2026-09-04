<?php

namespace App\Modules\Organization\Observers;

use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Organization\Models\Tenant;
use Spatie\Permission\Models\Role;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        foreach (DefaultRoles::TENANT_ROLES as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
