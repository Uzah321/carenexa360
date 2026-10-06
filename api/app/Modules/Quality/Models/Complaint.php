<?php

namespace App\Modules\Quality\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use BelongsToTenant, HasAuditLog;

    public const CHANNELS = ['phone', 'email', 'letter', 'in_person', 'online', 'other'];

    public const CATEGORIES = [
        'quality_of_care',
        'staff_conduct',
        'timekeeping',
        'missed_visit',
        'communication',
        'medication',
        'dignity_and_respect',
        'billing',
        'other',
    ];

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['received', 'investigating', 'resolved', 'closed', 'withdrawn'];

    public const OUTCOMES = ['upheld', 'partially_upheld', 'not_upheld'];

    /** Statuses that mean the complaint is still being dealt with. */
    public const OPEN_STATUSES = ['received', 'investigating'];

    /** A full response is due this many days after receipt unless set otherwise (UK practice: 20 working days). */
    public const RESPONSE_DAYS = 28;

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'received_date',
        'complainant_name',
        'complainant_relationship',
        'channel',
        'category',
        'severity',
        'description',
        'status',
        'assigned_to',
        'acknowledged_date',
        'response_due_date',
        'outcome',
        'findings',
        'actions_taken',
        'resolved_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'acknowledged_date' => 'date',
            'response_due_date' => 'date',
            'resolved_date' => 'date',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->response_due_date !== null && $this->response_due_date->isPast() && ! $this->response_due_date->isToday();
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
