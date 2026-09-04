<?php

namespace App\Modules\Observations\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Observation extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const TYPES = [
        'blood_pressure',
        'pulse',
        'temperature',
        'blood_glucose',
        'oxygen_saturation',
        'respiratory_rate',
        'weight',
        'height',
        'bmi',
        'pain_score',
        'fluid_intake',
        'urine_output',
        'bowel_movement',
        'sleep',
        'mood',
    ];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'visit_id',
        'type',
        'value',
        'unit',
        'recorded_by',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ClinicalAlert::class);
    }
}
