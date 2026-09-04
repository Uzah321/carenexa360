<?php

namespace App\Modules\Incidents\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const TYPES = [
        'fall',
        'medication_error',
        'injury',
        'behavioural',
        'missing_person',
        'property_damage',
        'staff_injury',
        'infection',
        'hospital_admission',
        'other',
    ];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['reported', 'investigating', 'corrective_action', 'reviewed', 'closed'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'type',
        'severity',
        'description',
        'immediate_action',
        'status',
        'reported_by',
        'assigned_to',
        'investigation_notes',
        'corrective_actions',
        'reviewed_by',
        'reviewed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
