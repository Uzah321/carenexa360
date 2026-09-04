<?php

namespace App\Support\Concerns;

use App\Modules\Audit\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditLog
{
    protected static array $auditExcept = ['password', 'remember_token', 'mfa_secret', 'updated_at'];

    public static function bootHasAuditLog(): void
    {
        static::created(function (Model $model) {
            static::recordAudit('created', $model, null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            static::recordAudit('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            static::recordAudit('deleted', $model, $model->getOriginal(), null);
        });
    }

    protected static function recordAudit(string $action, Model $model, ?array $old, ?array $new): void
    {
        $except = $model->auditExcept ?? static::$auditExcept;

        $old = $old ? Arr::except($old, $except) : null;
        $new = $new ? Arr::except($new, $except) : null;

        if ($action === 'updated' && empty($new)) {
            return;
        }

        AuditLog::create([
            'tenant_id' => app(TenantContext::class)->get() ?? ($model->tenant_id ?? null),
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device' => null,
        ]);
    }
}
