<?php

namespace App\Modules\Compliance\Models;

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
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceRequirement extends Model
{
    use BelongsToTenant, HasAuditLog, HasExpiryStatus, HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'compliant', 'non_compliant'];

    public const EXPIRY_STATUSES = ['valid', 'expiring_soon', 'expired', 'no_expiry'];

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'jurisdiction',
        'status',
        'issued_date',
        'renewal_date',
        'reference_number',
        'responsible_user_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'renewal_date' => 'date',
        ];
    }

    protected function expiryStatus(): Attribute
    {
        return Attribute::make(get: fn () => $this->computeExpiryStatus($this->renewal_date));
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
