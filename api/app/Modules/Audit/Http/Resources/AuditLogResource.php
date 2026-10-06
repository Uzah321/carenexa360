<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Support\AuditDescriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    private bool $detail = false;

    /** Include the field-by-field changes and the actor's details (the detail view). */
    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $changedFields = array_keys(array_diff_key(
            $this->action === 'deleted' ? ($this->old_values ?? []) : ($this->new_values ?? []),
            array_flip(['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at']),
        ));

        $base = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            ...AuditDescriber::subject($this->resource),
            'changed_fields' => $changedFields,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device' => AuditDescriber::device($this->user_agent),
            'created_at' => $this->created_at,
        ];

        if (! $this->detail) {
            return $base;
        }

        return [
            ...$base,
            'user_email' => $this->user?->email,
            'user_roles' => $this->user?->roles->pluck('name')->values() ?? [],
            'changes' => AuditDescriber::changes($this->resource),
        ];
    }
}
