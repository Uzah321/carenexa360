<?php

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\Funder;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Medications\Models\Medication;
use App\Modules\Organization\Models\Branch;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns a raw audit entry ("App\…\Medication #12, new_values: {dose: 2}")
 * into something a manager can read: what kind of record, which one by
 * name, where to find it, and each field that changed with ids resolved to
 * names. Works for deleted records too, from the values captured at the time.
 */
class AuditDescriber
{
    /** Fields that are noise in a change list. */
    private const HIDDEN_FIELDS = ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'mfa_secret', 'path'];

    /** Foreign keys that point at a person (users table). */
    private const USER_FIELDS = [
        'user_id', 'carer_id', 'care_manager_id', 'assigned_to', 'administered_by', 'witness_id', 'responsible_staff_id',
        'action_owner_id', 'created_by', 'recorded_by', 'reported_by', 'reviewed_by', 'responsible_user_id', 'staff_user_id',
        'checked_by', 'approved_by', 'acknowledged_by', 'author_id', 'uploaded_by', 'posted_by', 'completed_by',
        'closed_by', 'overridden_by',
    ];

    /** Kinds of record: label, how to name one, where it lives in the app. */
    private static function kinds(): array
    {
        $client = fn (?int $id) => $id ? self::clientName($id) : null;
        $clientLink = fn (?int $id) => $id ? "/service-users/{$id}" : null;
        $person = fn (?int $id) => $id ? User::withTrashed()->find($id)?->name : null;

        return [
            'ServiceUser' => ['Client', fn ($a) => trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? '')), fn ($a, $id) => "/service-users/{$id}"],
            'ServiceUserContact' => ['Client contact', fn ($a) => self::join($a['name'] ?? null, $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'Medication' => ['Medication', fn ($a) => self::join(trim(($a['name'] ?? '').' '.($a['strength'] ?? '')), $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'MedicationAdministration' => ['Medication record', function ($a) use ($client) {
                $med = isset($a['medication_id']) ? Medication::find($a['medication_id']) : null;

                return self::join($med?->name, self::label($a['status'] ?? null), $client($med?->service_user_id));
            }, fn ($a) => ($med = isset($a['medication_id']) ? Medication::find($a['medication_id']) : null) ? "/service-users/{$med->service_user_id}" : null],
            'Observation' => ['Observation', fn ($a) => self::join(self::label($a['type'] ?? null), $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'ClinicalAlert' => ['Clinical alert', fn ($a) => self::join(Str::limit((string) ($a['message'] ?? ''), 60), $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'Visit' => ['Visit', fn ($a) => self::join(self::dateOnly($a['visit_date'] ?? null).(isset($a['start_time']) ? ' '.substr($a['start_time'], 0, 5) : ''), $client($a['service_user_id'] ?? null)), fn ($a, $id) => "/visits/{$id}"],
            'CarePlan' => ['Care plan', fn ($a) => self::join(isset($a['version']) ? "Version {$a['version']}" : null, $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'CarePlanSection' => ['Care plan section', fn ($a) => self::join(self::label($a['area'] ?? null), $client(self::planClient($a['care_plan_id'] ?? null))), fn ($a) => $clientLink(self::planClient($a['care_plan_id'] ?? null))],
            'CarePlanRiskAssessment' => ['Risk assessment', fn ($a) => self::join($a['hazard'] ?? null, $client(self::planClient($a['care_plan_id'] ?? null))), fn ($a) => $clientLink(self::planClient($a['care_plan_id'] ?? null))],
            'AssessmentResponse' => ['Assessment', fn ($a) => self::join(self::label($a['status'] ?? null), $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'AssessmentTemplate' => ['Assessment template', fn ($a) => $a['name'] ?? null, fn () => '/assessment-templates'],
            'CareNote' => ['Care note', fn ($a) => self::join(Str::limit((string) ($a['caption'] ?? ''), 60) ?: null, $client($a['service_user_id'] ?? null)), fn ($a) => $clientLink($a['service_user_id'] ?? null)],
            'Incident' => ['Incident', fn ($a) => self::join(self::label($a['type'] ?? null), $client($a['service_user_id'] ?? null)), fn () => '/incidents'],
            'SafeguardingCase' => ['Safeguarding case', fn ($a) => self::join(self::label($a['concern_type'] ?? null), $client($a['service_user_id'] ?? null)), fn () => '/safeguarding'],
            'Complaint' => ['Complaint', fn ($a) => self::join(isset($a['complainant_name']) ? "From {$a['complainant_name']}" : null, self::label($a['category'] ?? null)), fn () => '/complaints'],
            'SpotCheck' => ['Spot check', fn ($a) => self::join($person($a['staff_user_id'] ?? null), self::dateOnly($a['check_date'] ?? null)), fn () => '/spot-checks'],
            'StaffProfile' => ['Staff profile', fn ($a) => $person($a['user_id'] ?? null), fn () => '/staff'],
            'User' => ['User account', fn ($a) => self::join($a['name'] ?? null, $a['email'] ?? null), fn () => '/roles-permissions'],
            'Shift' => ['Shift', fn ($a) => self::join(self::dateOnly($a['shift_date'] ?? null), $person($a['user_id'] ?? null)), fn () => '/rostering'],
            'DutyPeriod' => ['Clock-in', fn ($a) => $person($a['user_id'] ?? null), fn () => '/live-map'],
            'LeaveRequest' => ['Leave request', fn ($a) => self::join(self::label($a['type'] ?? null), $person($a['user_id'] ?? null), self::dateOnly($a['start_date'] ?? null)), fn () => '/leave'],
            'TrainingCourse' => ['Training course', fn ($a) => $a['name'] ?? null, fn () => '/training'],
            'TrainingRecord' => ['Training record', fn ($a) => self::join($person($a['user_id'] ?? null), self::dateOnly($a['completed_date'] ?? null)), fn () => '/training'],
            'ComplianceRequirement' => ['Compliance requirement', fn ($a) => $a['name'] ?? null, fn () => '/compliance'],
            'Document' => ['Document', fn ($a) => $a['original_filename'] ?? null, fn ($a) => ($a['documentable_type'] ?? null) === 'service_user' ? $clientLink($a['documentable_id'] ?? null) : null],
            'Invoice' => ['Invoice', fn ($a) => self::join($a['invoice_number'] ?? null, $client($a['service_user_id'] ?? null)), fn () => '/billing'],
            'InvoiceLineItem' => ['Invoice line', fn ($a) => $a['description'] ?? null, fn () => '/billing'],
            'Funder' => ['Funder', fn ($a) => $a['name'] ?? null, fn () => '/billing'],
            'PayPeriod' => ['Pay period', fn ($a) => self::join(self::dateOnly($a['start_date'] ?? null), self::dateOnly($a['end_date'] ?? null)), fn () => '/payroll'],
            'Payslip' => ['Payslip', fn ($a) => $person($a['user_id'] ?? null), fn () => '/payroll'],
            'Announcement' => ['Announcement', fn ($a) => $a['title'] ?? null, fn () => '/announcements'],
            'Branch' => ['Location', fn ($a) => $a['name'] ?? null, fn () => '/settings'],
            'Tenant' => ['Organisation settings', fn ($a) => $a['name'] ?? null, fn () => '/settings'],
        ];
    }

    /** Every kind of record the log can show, for the filter dropdown. */
    public static function recordTypes(): array
    {
        return collect(self::kinds())->map(fn ($kind, $basename) => ['value' => $basename, 'label' => $kind[0]])
            ->sortBy('label')->values()->all();
    }

    /** What the entry was done to: label, name and link. */
    public static function subject(AuditLog $log): array
    {
        $basename = class_basename($log->auditable_type);
        [$label, $name, $link] = self::kinds()[$basename] ?? [Str::headline($basename), fn ($a) => $a['name'] ?? $a['title'] ?? null, fn () => null];

        // Current values over the ones captured at the time — a deleted
        // record still has a name from what was logged.
        $attributes = array_merge($log->old_values ?? [], $log->new_values ?? [], self::liveAttributes($log) ?? []);

        try {
            $recordName = $name($attributes);
            $recordLink = $log->action === 'deleted' ? null : $link($attributes, $log->auditable_id);
        } catch (\Throwable) {
            $recordName = null;
            $recordLink = null;
        }

        return [
            'record_label' => $label,
            'record_name' => $recordName ?: "#{$log->auditable_id}",
            'record_link' => $recordLink,
            'record_exists' => self::liveAttributes($log) !== null,
        ];
    }

    /** Each field that changed, with before and after, and ids shown as names. */
    public static function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        $fields = match ($log->action) {
            'created' => array_keys($new),
            'deleted' => array_keys($old),
            default => array_keys($new),
        };

        $changes = [];
        foreach ($fields as $field) {
            if (in_array($field, self::HIDDEN_FIELDS, true)) {
                continue;
            }
            $before = $log->action === 'created' ? null : ($old[$field] ?? null);
            $after = $log->action === 'deleted' ? null : ($new[$field] ?? null);
            if ($log->action === 'created' && ($after === null || $after === '' || $after === [])) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'before' => $before,
                'after' => $after,
                'before_display' => self::resolve($field, $before),
                'after_display' => self::resolve($field, $after),
            ];
        }

        return $changes;
    }

    /** "Chrome on Windows" from a user-agent string. */
    public static function device(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            str_contains($userAgent, 'curl/') => 'curl',
            default => null,
        };
        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $browser || $os ? trim(($browser ?? 'Browser').($os ? " on {$os}" : '')) : Str::limit($userAgent, 60);
    }

    // ---- helpers -------------------------------------------------------------

    /** @var array<string, ?array> */
    private static array $live = [];

    private static function liveAttributes(AuditLog $log): ?array
    {
        $key = $log->auditable_type.'#'.$log->auditable_id;
        if (! array_key_exists($key, self::$live)) {
            $class = $log->auditable_type;
            $model = null;
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                $query = $class::query();
                if (method_exists($class, 'bootSoftDeletes')) {
                    $query->withTrashed();
                }
                $model = $query->find($log->auditable_id);
            }
            self::$live[$key] = $model?->getAttributes();
        }

        return self::$live[$key];
    }

    private static function resolve(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        $id = is_numeric($value) ? (int) $value : null;
        if (! $id) {
            return null;
        }

        return match (true) {
            in_array($field, self::USER_FIELDS, true) => User::withTrashed()->find($id)?->name,
            $field === 'service_user_id' => self::clientName($id),
            $field === 'branch_id' => Branch::find($id)?->name,
            $field === 'medication_id' => Medication::find($id)?->name,
            $field === 'funder_id' => Funder::find($id)?->name,
            $field === 'care_plan_id' => ($plan = CarePlan::find($id)) ? "Version {$plan->version}" : null,
            $field === 'visit_id' => ($visit = Visit::find($id)) ? $visit->visit_date->toDateString().' '.substr($visit->start_time, 0, 5) : null,
            $field === 'invoice_id' => Invoice::find($id)?->invoice_number,
            default => null,
        };
    }

    private static function clientName(int $id): ?string
    {
        $su = ServiceUser::withTrashed()->find($id);

        return $su ? trim("{$su->first_name} {$su->last_name}") : null;
    }

    private static function planClient(?int $carePlanId): ?int
    {
        return $carePlanId ? CarePlan::find($carePlanId)?->service_user_id : null;
    }

    private static function fieldLabel(string $field): string
    {
        $field = preg_replace('/_id$/', '', $field);

        return ucfirst(str_replace('_', ' ', $field));
    }

    private static function label(?string $value): ?string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : null;
    }

    private static function dateOnly(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    private static function join(?string ...$parts): ?string
    {
        $parts = array_filter($parts, fn ($p) => $p !== null && $p !== '');

        return $parts ? implode(' — ', $parts) : null;
    }
}
