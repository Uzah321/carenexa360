<?php

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutyPeriod extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'started_at',
        'start_lat',
        'start_lng',
        'start_accuracy',
        'ended_at',
        'end_lat',
        'end_lng',
        'end_accuracy',
        'close_reason',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function carer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Set only when a manager closed the shift on the carer's behalf. */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Whether this user is on duty right now. Visit check-in depends on it, so
     * it lives on the model rather than being re-queried at each call site.
     */
    public static function isOnDuty(int $userId): bool
    {
        return static::where('user_id', $userId)->whereNull('ended_at')->exists();
    }
}
