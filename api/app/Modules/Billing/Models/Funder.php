<?php

namespace App\Modules\Billing\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funder extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const TYPES = ['local_authority', 'nhs', 'private', 'insurance', 'self_funded'];

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'contact_name',
        'phone',
        'email',
        'address',
        'default_hourly_rate',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
