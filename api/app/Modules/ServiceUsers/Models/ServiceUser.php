<?php

namespace App\Modules\ServiceUsers\Models;

use App\Models\User;
use App\Modules\Assessments\Models\AssessmentResponse;
use App\Modules\Billing\Models\Funder;
use App\Modules\CareNotes\Models\CareNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Documents\Models\Document;
use App\Modules\Incidents\Models\Incident;
use App\Modules\MedicalHistory\Models\MedicalHistoryEntry;
use App\Modules\Medications\Models\Medication;
use App\Modules\Observations\Models\Observation;
use App\Modules\Organization\Models\Branch;
use App\Modules\Visits\Models\Visit;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceUser extends Model
{
    use BelongsToTenant, HasAuditLog, HasFactory, SoftDeletes;

    public const SMOKING_STATUSES = ['never', 'former', 'current'];

    public const RESUSCITATION_STATUSES = ['for_resuscitation', 'dnacpr'];

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'care_manager_id',
        'first_name',
        'last_name',
        'preferred_name',
        'date_of_birth',
        'nhs_number',
        'gender',
        'language',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'funding_source',
        'funder_id',
        'status',
        // Kept in step with the medical history by MedicalSummary — not
        // edited directly.
        'allergies',
        'diagnoses',
        'medical_conditions',
        'disabilities',
        'no_known_allergies',
        'smoking_status',
        'alcohol_intake',
        'resuscitation_status',
        'resuscitation_notes',
        'medical_history_summary',
        'medical_history_reviewed_at',
        'medical_history_reviewed_by',
        'mobility_notes',
        'communication_needs',
        'dietary_needs',
        'cultural_preferences',
        'religious_requirements',
        'behavioural_considerations',
        'preferred_routines',
        'capacity_consent_notes',
        'referring_hospital',
        'hospital_record_number',
        'discharge_date',
        'discharge_summary',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'allergies' => 'array',
            'diagnoses' => 'array',
            'medical_conditions' => 'array',
            'disabilities' => 'array',
            'no_known_allergies' => 'boolean',
            'medical_history_reviewed_at' => 'datetime',
            'discharge_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function careManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'care_manager_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ServiceUserContact::class);
    }

    public function carers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_user_carer');
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function careNotes(): HasMany
    {
        return $this->hasMany(CareNote::class);
    }

    public function assessmentResponses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function medicalHistory(): HasMany
    {
        return $this->hasMany(MedicalHistoryEntry::class);
    }

    public function medicalHistoryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medical_history_reviewed_by');
    }

    public function currentAllergies(): HasMany
    {
        return $this->medicalHistory()->where('category', 'allergy')->where('status', '!=', 'resolved');
    }

    /**
     * Either a current allergy is on record or the client has been confirmed
     * as having none — anything else means nobody has asked yet.
     */
    public function allergyStatusKnown(): bool
    {
        return $this->no_known_allergies || $this->currentAllergies()->exists();
    }

    /** Clients whose allergy status hasn't been recorded either way. */
    public function scopeAllergyStatusUnknown(Builder $query): Builder
    {
        return $query->where('no_known_allergies', false)->whereDoesntHave('currentAllergies');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
