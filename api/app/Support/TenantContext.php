<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;

        DB::statement('SELECT set_config(?, ?, false)', [
            'app.current_tenant_id',
            $tenantId === null ? '' : (string) $tenantId,
        ]);
    }

    public function get(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }
}
