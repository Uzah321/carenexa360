<?php

namespace App\Modules\Visits\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'missed', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'carer_id',
        'visit_date',
        'start_time',
        'end_time',
        'care_tasks',
        'completed_care_tasks',
        'medication_tasks',
        'medication_tasks_completed',
        'required_skills',
        'priority',
        'status',
        'notes',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy',
        'check_in_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_accuracy',
        'check_out_at',
        'override_reason',
        'overridden_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'care_tasks' => 'array',
            'completed_care_tasks' => 'array',
            'required_skills' => 'array',
            'medication_tasks' => 'boolean',
            'medication_tasks_completed' => 'boolean',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function carer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carer_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
