<?php

namespace App\Modules\Medications\Models;

use App\Models\User;
use App\Modules\Organization\Support\TenantSettings;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use App\Support\Time\TenantClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory;

    public const STATUSES = ['active', 'discontinued'];

    protected $fillable = [
        'tenant_id',
        'service_user_id',
        'name',
        'strength',
        'form',
        'dose',
        'route',
        'frequency',
        'schedule',
        'start_date',
        'end_date',
        'prescriber',
        'pharmacy',
        'instructions',
        'is_prn',
        'prn_instructions',
        'is_controlled_drug',
        'stock_on_hand',
        'reorder_level',
        'units_per_dose',
        'status',
        'created_by',
        'archived_at',
    ];

    /** Matches the column default, so a medication just created already knows it. */
    protected $attributes = [
        'units_per_dose' => 1,
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_prn' => 'boolean',
            'is_controlled_drug' => 'boolean',
            'stock_on_hand' => 'float',
            'reorder_level' => 'float',
            'units_per_dose' => 'float',
            'archived_at' => 'datetime',
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

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    /** Whether stock is being counted for this medication at all. */
    public function tracksStock(): bool
    {
        return $this->stock_on_hand !== null;
    }

    /** Doses a day from the schedule; null for PRN or unscheduled medication. */
    public function dosesPerDay(): ?int
    {
        return $this->is_prn || empty($this->schedule) ? null : count($this->schedule);
    }

    /** How many days the current stock lasts at the scheduled rate. */
    public function daysOfStockLeft(): ?float
    {
        $perDay = $this->dosesPerDay();
        if (! $this->tracksStock() || ! $perDay || ! $this->units_per_dose) {
            return null;
        }

        return round($this->stock_on_hand / ($perDay * $this->units_per_dose), 1);
    }

    public function needsReorder(): bool
    {
        if (! $this->tracksStock()) {
            return false;
        }
        $days = $this->daysOfStockLeft();

        return ($this->reorder_level !== null && $this->stock_on_hand <= $this->reorder_level)
            || ($days !== null && $days <= (int) TenantSettings::for($this->tenant_id, 'stock_reorder_days'));
    }

    /** Today's records — what the day's medication round is checked against. */
    public function todayAdministrations(): HasMany
    {
        // When eager loading, this runs on a blank model with no tenant yet —
        // the signed-in user's organisation is the same one.
        return $this->administrations()->whereBetween(
            'administered_at',
            TenantClock::dayBoundsUtc($this->tenant_id ?? auth()->user()?->tenant_id),
        );
    }
}
