<?php

namespace App\Modules\Organization\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'tenant_id',
        'name',
        'country',
        'region',
        'address',
        'status',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
