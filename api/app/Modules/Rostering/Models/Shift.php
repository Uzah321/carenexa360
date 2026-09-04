<?php

namespace App\Modules\Rostering\Models;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const TYPES = ['day', 'night', 'split'];

    public const STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'shift_date',
        'start_time',
        'end_time',
        'shift_type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
