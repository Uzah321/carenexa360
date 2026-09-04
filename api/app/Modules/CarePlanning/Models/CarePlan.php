<?php

namespace App\Modules\CarePlanning\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarePlan extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'version',
        'status',
        'effective_from',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CarePlanSection::class);
    }
}
