<?php

namespace App\Modules\Staff\Models;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Organization\Models\Branch;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StaffProfile extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'branch_id',
        'employee_number',
        'job_title',
        'employment_start_date',
        'skills',
        'employment_status',
        'hourly_rate',
    ];

    protected function casts(): array
    {
        return [
            'employment_start_date' => 'date',
            'skills' => 'array',
            'hourly_rate' => 'decimal:2',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
