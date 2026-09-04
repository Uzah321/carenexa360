<?php

namespace App\Modules\Safeguarding\Models;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SafeguardingCase extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['reported', 'investigating', 'actions_taken', 'closed'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'victim_name',
        'alleged_perpetrator',
        'concern_type',
        'immediate_risk',
        'external_agencies_notified',
        'investigation_notes',
        'actions_taken',
        'outcome',
        'status',
        'reported_by',
        'confidential_notes',
    ];

    protected function casts(): array
    {
        return [
            'immediate_risk' => 'boolean',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
