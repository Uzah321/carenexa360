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
    ];

    protected $fillable = [
        'tenant_id',
        'medication_id',
        'visit_id',
        'status',
        'administered_at',
        'administered_by',
        'witness_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'datetime',
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
