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
use Illuminate\Validation\Rule;

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
        // A full set of NEWS2 vital signs recorded together — see Support\News2.
        'news2',
        // A wound assessment — value: site, length/width/depth (cm), stage,
        // appearance, exudate. Tracked per site for the wound progress report.
        'wound',
    ];

    /** Pressure ulcer category (NPIAP / EPUAP), or not a pressure ulcer. */
    public const WOUND_STAGES = ['category_1', 'category_2', 'category_3', 'category_4', 'unstageable', 'deep_tissue_injury', 'not_pressure_ulcer'];

    /** The dominant tissue in the wound bed. */
    public const WOUND_APPEARANCES = ['epithelialising', 'granulating', 'sloughy', 'necrotic', 'infected', 'healed'];

    public const WOUND_EXUDATE = ['none', 'low', 'moderate', 'high'];

    /** Validation rules for a wound's value, shared by store and update. */
    public static function woundRules(string $presence): array
    {
        return [
            'value.site' => [$presence, 'string', 'max:255'],
            'value.length_cm' => ['nullable', 'numeric', 'between:0,100'],
            'value.width_cm' => ['nullable', 'numeric', 'between:0,100'],
            'value.depth_cm' => ['nullable', 'numeric', 'between:0,50'],
            'value.stage' => ['nullable', 'string', Rule::in(self::WOUND_STAGES)],
            'value.appearance' => ['nullable', 'string', Rule::in(self::WOUND_APPEARANCES)],
            'value.exudate' => ['nullable', 'string', Rule::in(self::WOUND_EXUDATE)],
        ];
    }

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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'recorded_at' => 'datetime',
            'archived_at' => 'datetime',
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
