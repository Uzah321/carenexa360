<?php

namespace App\Modules\MedicalHistory\Models;

use App\Models\User;
use App\Modules\MedicalHistory\Support\MedicalSummary;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalHistoryEntry extends Model
{
    use BelongsToTenant, HasAuditLog;

    public const CATEGORIES = [
        'condition',
        'mental_health',
        'disability',
        'allergy',
        'procedure',
        'admission',
        'immunisation',
        'family_history',
    ];

    public const STATUSES = ['active', 'in_remission', 'resolved'];

    public const SEVERITIES = ['mild', 'moderate', 'severe', 'life_threatening'];

    public const ALLERGY_TYPES = ['medication', 'food', 'environmental', 'other'];

    /**
     * The optional fields each category records — name and notes apply to
     * all of them. Anything not listed is cleared on save, so changing an
     * entry's category can't leave stale values behind.
     *
     * Mirrored in web/src/modules/medical-history/categories.ts.
     */
    public const FIELDS = [
        'condition' => ['status', 'started_on', 'ended_on', 'provider'],
        'mental_health' => ['status', 'started_on', 'ended_on', 'provider'],
        'disability' => ['started_on'],
        'allergy' => ['status', 'severity', 'allergy_type', 'reaction', 'started_on'],
        'procedure' => ['started_on', 'provider'],
        'admission' => ['started_on', 'ended_on', 'provider'],
        'immunisation' => ['started_on', 'provider'],
        'family_history' => ['relationship'],
    ];

    private const OPTIONAL_FIELDS = [
        'status', 'severity', 'allergy_type', 'reaction', 'relationship', 'started_on', 'ended_on', 'provider',
    ];

    /** What a user can set — the rest is filled in by the server. */
    public const EDITABLE = ['category', 'name', 'notes', ...self::OPTIONAL_FIELDS];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'category',
        'name',
        'status',
        'severity',
        'allergy_type',
        'reaction',
        'relationship',
        'started_on',
        'ended_on',
        'provider',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The client's at-a-glance allergy and condition lists are built
        // from these entries — rebuild them whenever one changes.
        $sync = function (self $entry) {
            if ($entry->serviceUser) {
                MedicalSummary::sync($entry->serviceUser);
            }
        };

        static::saved($sync);
        static::deleted($sync);
    }

    /**
     * Clears the fields this entry's category doesn't record, and starts a
     * category that has a status off as active.
     */
    public static function forCategory(array $attributes): array
    {
        $fields = self::FIELDS[$attributes['category']];

        foreach (array_diff(self::OPTIONAL_FIELDS, $fields) as $field) {
            $attributes[$field] = null;
        }

        if (in_array('status', $fields, true)) {
            $attributes['status'] ??= 'active';
        }

        return $attributes;
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(ServiceUser::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
