<?php

namespace App\Modules\CarePlanning\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanSection extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const AREAS = [
        'personal_care',
        'mobility',
        'nutrition',
        'hydration',
        'medication',
        'communication',
        'mental_wellbeing',
        'behaviour',
        'social_activities',
        'sleep',
        'continence',
        'skin_integrity',
        'pain_management',
        'respiratory_care',
        'diabetes_management',
        'falls_prevention',
        'end_of_life_care',
        'safeguarding',
        'daily_living',
        'rehabilitation',
    ];

    protected $fillable = [
        'tenant_id',
        'care_plan_id',
        'area',
        'identified_need',
        'risk',
        'goal',
        'intervention',
        'equipment',
        'frequency',
        'responsible_staff_id',
        'start_date',
        'review_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'review_date' => 'date',
        ];
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_staff_id');
    }
}
