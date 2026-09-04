<?php

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarerLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'visit_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function carer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
