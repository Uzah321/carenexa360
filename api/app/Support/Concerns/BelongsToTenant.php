<?php

namespace App\Support\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->has()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantContext->get());
            }
        });

        static::creating(function (Model $model) {
            $tenantContext = app(TenantContext::class);

            if (is_null($model->tenant_id) && $tenantContext->has()) {
                $model->tenant_id = $tenantContext->get();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Models\Tenant::class);
    }

    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
