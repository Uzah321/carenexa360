<?php

namespace App\Modules\Training\Models;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use App\Support\Concerns\HasExpiryStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TrainingRecord extends Model
{
    use BelongsToTenant, HasAuditLog, HasExpiryStatus, HasFactory;

    public const STATUSES = ['valid', 'expiring_soon', 'expired', 'no_expiry'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'training_course_id',
        'completed_date',
        'expiry_date',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Computed, not stored — a record's freshness always reflects "now"
     * rather than a snapshot taken when it was last saved.
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn () => $this->computeExpiryStatus($this->expiry_date));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainingCourse(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
