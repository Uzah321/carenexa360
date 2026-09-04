<?php

namespace App\Modules\Assessments\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentTemplate extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'select',
        'checkbox',
        'score',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'description',
        'fields',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }
}
