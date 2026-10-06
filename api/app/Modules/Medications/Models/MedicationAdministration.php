<?php

namespace App\Modules\Medications\Models;

use App\Models\User;
use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = [
        'administered',
        'refused',
        'missed',
        'not_available',
        'hospitalized',
        'self_administered',
        'prn',
        'not_given',
    ];

    /**
     * Why a scheduled dose wasn't given — required when status is not_given.
     * (refused, not_available, hospitalized and self_administered also exist
     * as statuses from before not_given did; older records keep them.)
     */
    public const NOT_GIVEN_REASONS = [
        'refused',
        'unwell',
        'hospitalised',
        'social_leave',
        'medication_not_available',
        'client_cancelled',
        'self_administered',
        'administered_by_family',
        'prn_not_required',
        'given_by_other_carer',
    ];

    protected $fillable = [
        'tenant_id',
        'medication_id',
        'visit_id',
        'status',
        'scheduled_time',
        'not_given_reason',
        'stock_checked',
        'administered_at',
        'administered_by',
        'witness_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'datetime',
            'stock_checked' => 'boolean',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_id');
    }
}
