<?php

namespace App\Modules\Organization\Models;

use App\Models\User;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasAuditLog, HasFactory;

    public const STATUSES = ['trial', 'active', 'suspended'];

    protected $fillable = [
        'name',
        'slug',
        'country',
        'timezone',
        'currency',
        'locale',
        'plan',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * A configurable business rule stored in the settings jsonb blob, e.g.
     * $tenant->setting('geofence_radius_meters', 100). Centralizing the
     * lookup here (rather than `$tenant->settings['x'] ?? default` at each
     * call site) means every consumer falls back the same way if the key
     * was never set.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
