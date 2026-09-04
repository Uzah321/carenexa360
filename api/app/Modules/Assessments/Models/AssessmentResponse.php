<?php

namespace App\Modules\Assessments\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResponse extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    protected $fillable = [
        'tenant_id',
        'assessment_template_id',
        'service_user_id',
        'answers',
        'completed_by',
        'completed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class, 'assessment_template_id');
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
