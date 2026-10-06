<?php

namespace App\Modules\Quality\Models;

use App\Models\User;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An unannounced observation of a staff member at work. Each area is
 * marked pass / fail / na; the outcome follows from the fails.
 */
class SpotCheck extends Model
{
    use BelongsToTenant, HasAuditLog;

    public const AREAS = [
        'punctuality',
        'id_and_uniform',
        'infection_control',
        'dignity_and_privacy',
        'care_delivery',
        'moving_and_handling',
        'medication',
        'communication',
        'record_keeping',
        'safeguarding_awareness',
    ];

    public const RESULTS = ['pass', 'fail', 'na'];

    public const OUTCOMES = ['pass', 'needs_improvement', 'fail'];

    protected $fillable = [
        'tenant_id',
        'staff_user_id',
        'checked_by',
        'service_user_id',
        'visit_id',
        'check_date',
        'results',
        'outcome',
        'notes',
        'actions_required',
        'follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'check_date' => 'date',
            'follow_up_date' => 'date',
            'results' => 'array',
        ];
    }

    /** No fails is a pass, one fail needs improvement, more is a fail. */
    public static function outcomeFor(array $results): string
    {
        $fails = count(array_filter($results, fn ($r) => $r === 'fail'));

        return match (true) {
            $fails === 0 => 'pass',
            $fails === 1 => 'needs_improvement',
            default => 'fail',
        };
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
