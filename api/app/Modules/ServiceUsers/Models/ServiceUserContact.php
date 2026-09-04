<?php

namespace App\Modules\ServiceUsers\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUserContact extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const TYPES = [
        'emergency_contact',
        'next_of_kin',
        'gp',
        'pharmacy',
        'legal_representative',
        'family',
    ];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'user_id',
        'type',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
        'notes',
    ];

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
