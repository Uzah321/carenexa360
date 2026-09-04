<?php

namespace App\Modules\Medications\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['active', 'discontinued'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'name',
        'strength',
        'form',
        'dose',
        'route',
        'frequency',
        'schedule',
        'start_date',
        'end_date',
        'prescriber',
        'pharmacy',
        'instructions',
        'is_prn',
        'prn_instructions',
        'is_controlled_drug',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_prn' => 'boolean',
            'is_controlled_drug' => 'boolean',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }
}
