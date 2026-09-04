<?php

namespace App\Models;

use App\Modules\Organization\Models\Tenant;
use App\Modules\Staff\Models\StaffProfile;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasApiTokens, HasAuditLog, HasFactory, HasRoles, Notifiable, SoftDeletes {
        // hasAnyRole() is a trait method, not an inherited one — `parent::`
        // can't reach it (it would hit Eloquent's __call and throw
        // BadMethodCallException for every non-platform-admin user). Alias
        // the trait's original under another name so the override below can
        // actually call it.
        HasRoles::hasAnyRole as protected baseHasAnyRole;
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return is_null($this->tenant_id);
    }

    /**
     * Every authorization check in this codebase that gates on tenant
     * ownership should go through this rather than comparing tenant_id
     * directly — a platform admin's own tenant_id is null, so a bare
     * `$user->tenant_id === $resource->tenant_id` always excludes them.
     */
    public function ownsTenant(?int $tenantId): bool
    {
        return $this->isPlatformAdmin() || $this->tenant_id === $tenantId;
    }

    /**
     * A platform admin has no restrictions anywhere in the app. Every
     * hasAnyRole() call in this codebase is a pure permission gate (never
     * used to exclude a role), so short-circuiting here is the one place
     * that grants full access instead of scattering isPlatformAdmin()
     * escapes across every controller and form request.
     */
    public function hasAnyRole(...$roles): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->baseHasAnyRole(...$roles);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
