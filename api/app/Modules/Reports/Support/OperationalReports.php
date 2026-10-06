<?php

namespace App\Modules\Reports\Support;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\Funder;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CareNotes\Models\CareNote;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Support\CarePathway;
use App\Modules\Documents\Models\Document;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\Observation;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Support\TenantSettings;
use App\Modules\Quality\Models\Complaint;
use App\Modules\Quality\Models\SpotCheck;
use App\Modules\Rostering\Models\Shift;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Models\ServiceUserContact;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Tracking\Models\CarerLocation;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use App\Support\Geo\Haversine;
use App\Support\Time\TenantClock;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Report handlers for client history, care delivery, workforce, rostering,
 * finance and travel — split out of ReportGeneratorController, which
 * dispatches to these. Each returns the same {title, columns, rows} shape.
 *
 * Tunable thresholds are tenant settings, read with a default:
 *   overtime_weekly_hours   hours a week before time counts as overtime (40)
 *   mileage_rate_per_mile   reimbursement rate in the tenant's currency (0.45, the HMRC rate)
 *   late_arrival_minutes    grace period before a clock-in counts as late (15)
 */
class OperationalReports
{
    /** GPS fixes worse than this are too noisy to add to a distance. */
    private const MAX_GPS_ACCURACY_METERS = 100;

    /** A jump between two fixes faster than this is a GPS glitch, not travel. */
    private const MAX_PLAUSIBLE_SPEED_KMH = 150;

    private const METERS_PER_MILE = 1609.344;

    public function __construct(private readonly array $filters) {}

    // ---- Shared helpers ---------------------------------------------------

    /** Start of the first day, local to the tenant, as a UTC instant (timestamps are stored in UTC). */
    private function from(): Carbon
    {
        return TenantClock::dayBoundsUtc($this->tenantId(), $this->filters['from'])[0];
    }

    /** End of the last day, local to the tenant, as a UTC instant. */
    private function to(): Carbon
    {
        return TenantClock::dayBoundsUtc($this->tenantId(), $this->filters['to'])[1];
    }

    private function tenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    /** A UTC timestamp in the tenant's local time. */
    private function local(\DateTimeInterface $instant): Carbon
    {
        return TenantClock::local($this->tenantId(), $instant);
    }

    private function branchId(): ?int
    {
        return $this->filters['branch_id'];
    }

    private function tenant(): ?Tenant
    {
        return auth()->user()?->tenant;
    }

    private function setting(string $key, mixed $default): mixed
    {
        return TenantSettings::for($this->tenantId(), $key) ?? $default;
    }

    private function clientName(?ServiceUser $serviceUser): string
    {
        return $serviceUser ? trim("{$serviceUser->first_name} {$serviceUser->last_name}") : '—';
    }

    private function label(?string $value): string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : '—';
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    /** Postgres returns time columns as "HH:MM:SS". */
    private function hm(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '—';
    }

    private function scheduledHours(Visit $visit): float
    {
        return max(0, $this->minutes($visit->end_time) - $this->minutes($visit->start_time)) / 60;
    }

    /** Actual time on site when both ends were recorded, otherwise the booked length of a completed visit. */
    private function deliveredHours(Visit $visit): float
    {
        if ($visit->check_in_at && $visit->check_out_at) {
            return max(0, $visit->check_in_at->diffInMinutes($visit->check_out_at)) / 60;
        }

        return $visit->status === 'completed' ? $this->scheduledHours($visit) : 0;
    }

    private function percent(float|int $part, float|int $whole): string
    {
        return $whole > 0 ? round($part / $whole * 100, 1).'%' : '—';
    }

    private function visitsInRange()
    {
        return Visit::whereBetween('visit_date', [$this->filters['from'], $this->filters['to']])
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())));
    }

    private function staffInScope(): Collection
    {
        return StaffProfile::with('user')
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->get()
            ->filter(fn (StaffProfile $sp) => $sp->user !== null);
    }

    private function hoursBetween(?Carbon $start, ?Carbon $end): float
    {
        return $start && $end ? max(0, $start->diffInMinutes($end)) / 60 : 0;
    }

    // ---- Client / Service User ---------------------------------------------

    /** Everything that happened in each client's care over the period. */
    public function careHistory(): array
    {
        $clients = ServiceUser::when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->orderBy('first_name')->get();
        $visits = $this->visitsInRange()->get()->groupBy('service_user_id');
        $count = fn (string $model, string $column) => $model::whereBetween($column, [$this->from(), $this->to()])
            ->selectRaw('service_user_id, count(*) as c')->groupBy('service_user_id')->pluck('c', 'service_user_id');
        $notes = $count(CareNote::class, 'created_at');
        $incidents = $count(Incident::class, 'created_at');
        $observations = $count(Observation::class, 'recorded_at');
        $doses = MedicationAdministration::whereBetween('administered_at', [$this->from(), $this->to()])
            ->join('medications', 'medications.id', '=', 'medication_administrations.medication_id')
            ->selectRaw('medications.service_user_id, count(*) as c')->groupBy('medications.service_user_id')
            ->pluck('c', 'service_user_id');

        return [
            'title' => 'Care History',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'visits', 'label' => 'Visits'],
                ['key' => 'completed', 'label' => 'Completed'],
                ['key' => 'missed', 'label' => 'Missed'],
                ['key' => 'hours', 'label' => 'Hours Delivered'],
                ['key' => 'notes', 'label' => 'Care Notes'],
                ['key' => 'observations', 'label' => 'Observations'],
                ['key' => 'doses', 'label' => 'Doses Recorded'],
                ['key' => 'incidents', 'label' => 'Incidents'],
            ],
            'rows' => $clients->map(function (ServiceUser $su) use ($visits, $notes, $incidents, $observations, $doses) {
                $v = $visits[$su->id] ?? collect();

                return [
                    'client' => $this->clientName($su),
                    'visits' => $v->count(),
                    'completed' => $v->where('status', 'completed')->count(),
                    'missed' => $v->where('status', 'missed')->count(),
                    'hours' => round($v->sum(fn (Visit $visit) => $this->deliveredHours($visit)), 2),
                    'notes' => (int) ($notes[$su->id] ?? 0),
                    'observations' => (int) ($observations[$su->id] ?? 0),
                    'doses' => (int) ($doses[$su->id] ?? 0),
                    'incidents' => (int) ($incidents[$su->id] ?? 0),
                ];
            })->values(),
        ];
    }

    /** Care plan versions created in the period — each one is a review. */
    public function reviewHistory(): array
    {
        $plans = CarePlan::whereBetween('created_at', [$this->from(), $this->to()])
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with(['serviceUser', 'createdBy'])
            ->withCount(['sections', 'riskAssessments'])
            ->orderByDesc('created_at')
            ->get();

        return [
            'title' => 'Care Plan Review History',
            'columns' => [
                ['key' => 'when', 'label' => 'Reviewed'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'version', 'label' => 'Version'],
                ['key' => 'effective_from', 'label' => 'Effective From'],
                ['key' => 'sections', 'label' => 'Care Areas'],
                ['key' => 'risks', 'label' => 'Risks'],
                ['key' => 'reviewed_by', 'label' => 'Reviewed By'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $plans->map(fn (CarePlan $p) => [
                'when' => $this->local($p->created_at)->format('Y-m-d H:i'),
                'client' => $this->clientName($p->serviceUser),
                'version' => $p->version,
                'effective_from' => $p->effective_from?->toDateString() ?? '—',
                'sections' => $p->sections_count,
                'risks' => $p->risk_assessments_count,
                'reviewed_by' => $p->createdBy->name ?? '—',
                'status' => $p->status === 'active' ? 'Current' : 'Superseded',
            ]),
        ];
    }

    public function dailyNotes(): array
    {
        $notes = CareNote::whereBetween('created_at', [$this->from(), $this->to()])
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with(['serviceUser', 'author'])
            ->orderByDesc('created_at')
            ->get();

        return [
            'title' => 'Daily Notes',
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'author', 'label' => 'Recorded By'],
                ['key' => 'note', 'label' => 'Note'],
                ['key' => 'audio', 'label' => 'Voice Note'],
            ],
            'rows' => $notes->map(fn (CareNote $n) => [
                'when' => $this->local($n->created_at)->format('Y-m-d H:i'),
                'client' => $this->clientName($n->serviceUser),
                'author' => $n->author->name ?? '—',
                'note' => $n->caption ?: '—',
                'audio' => $n->audio_path ? ($n->duration_seconds ? gmdate('i:s', (int) $n->duration_seconds) : 'Yes') : 'No',
            ]),
        ];
    }

    /** Each client contact, whether they have family-portal access, and how they've used it. */
    public function familyContactActivity(): array
    {
        $contacts = ServiceUserContact::query()
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with(['serviceUser', 'user'])
            ->get();
        $actions = AuditLog::whereIn('user_id', $contacts->pluck('user_id')->filter())
            ->whereBetween('created_at', [$this->from(), $this->to()])
            ->selectRaw('user_id, count(*) as c')->groupBy('user_id')->pluck('c', 'user_id');

        return [
            'title' => 'Family / Contact Activity',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'contact', 'label' => 'Contact'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'relationship', 'label' => 'Relationship'],
                ['key' => 'portal', 'label' => 'Portal Access'],
                ['key' => 'last_login', 'label' => 'Last Signed In'],
                ['key' => 'actions', 'label' => 'Actions in Period'],
            ],
            'rows' => $contacts->sortBy(fn ($c) => $this->clientName($c->serviceUser))->map(fn (ServiceUserContact $c) => [
                'client' => $this->clientName($c->serviceUser),
                'contact' => $c->name,
                'type' => $this->label($c->type),
                'relationship' => $c->relationship ?? '—',
                'portal' => $c->user_id ? 'Yes' : 'No',
                'last_login' => $c->user?->last_login_at?->format('Y-m-d H:i') ?? ($c->user_id ? 'Never' : '—'),
                'actions' => $c->user_id ? (int) ($actions[$c->user_id] ?? 0) : '—',
            ])->values(),
        ];
    }

    /**
     * Clients taken on (record created) and discharged (status changed to
     * discharged, from the audit log) in the period.
     */
    public function admissionsAndDischarges(bool $admissionsOnly = false): array
    {
        $admitted = ServiceUser::whereBetween('created_at', [$this->from(), $this->to()])
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->get()
            ->map(fn (ServiceUser $su) => [
                'date' => $this->local($su->created_at)->toDateString(),
                'client' => $this->clientName($su),
                'event' => 'Admitted',
                'funding' => $su->funding_source ?? '—',
                'details' => $su->referring_hospital
                    ? "From {$su->referring_hospital}".($su->discharge_date ? ' (hospital discharge '.$su->discharge_date->toDateString().')' : '')
                    : '—',
            ]);

        $discharged = $admissionsOnly ? collect() : AuditLog::where('auditable_type', ServiceUser::class)
            ->where('action', 'updated')
            ->whereBetween('created_at', [$this->from(), $this->to()])
            ->get()
            ->filter(fn (AuditLog $log) => ($log->new_values['status'] ?? null) === 'discharged')
            ->map(function (AuditLog $log) {
                $su = ServiceUser::withTrashed()->find($log->auditable_id);
                if (! $su || ($this->branchId() && $su->branch_id !== $this->branchId())) {
                    return null;
                }

                return [
                    'date' => $this->local($log->created_at)->toDateString(),
                    'client' => $this->clientName($su),
                    'event' => 'Discharged',
                    'funding' => $su->funding_source ?? '—',
                    'details' => $su->discharge_summary ?? '—',
                ];
            })
            ->filter();

        return [
            'title' => $admissionsOnly ? 'New Admissions' : 'Admission / Discharge History',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'event', 'label' => 'Event'],
                ['key' => 'funding', 'label' => 'Funding'],
                ['key' => 'details', 'label' => 'Details'],
            ],
            'rows' => $admitted->concat($discharged)->sortByDesc('date')->values(),
        ];
    }

    /** Every active client's progress against the care pathway timescales, most overdue first. */
    public function carePathway(): array
    {
        $labels = ['done' => 'Done', 'done_late' => 'Done late', 'due' => 'Due', 'overdue' => 'Overdue', 'waiting' => '—'];
        $cell = fn (?array $stage) => $stage === null ? '—' : match ($stage['status']) {
            'done', 'done_late' => $labels[$stage['status']].' ('.$stage['done'].')',
            'due', 'overdue' => $labels[$stage['status']].' ('.$stage['due'].')',
            default => '—',
        };

        $rows = ServiceUser::where('status', 'active')
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->get()
            ->map(function (ServiceUser $su) use ($cell) {
                $pathway = CarePathway::for($su);
                $stage = fn (string $key) => collect($pathway['stages'])->firstWhere('key', $key);

                return [
                    'client' => $this->clientName($su),
                    'referred' => $stage('referral')['done'] ?? '—',
                    'assessment' => $cell($stage('assessment')),
                    'care_plan' => $cell($stage('care_plan')),
                    'first_review' => $cell($stage('first_review')),
                    'next_review' => $cell($stage('next_review')),
                    'risk_review' => $cell($stage('risk_review')),
                    'overdue' => $pathway['overdue'],
                ];
            })
            ->sortByDesc('overdue')
            ->values();

        return [
            'title' => 'Care Pathway',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'referred', 'label' => 'Referred'],
                ['key' => 'assessment', 'label' => 'Assessment'],
                ['key' => 'care_plan', 'label' => 'Care Plan'],
                ['key' => 'first_review', 'label' => 'First Review'],
                ['key' => 'next_review', 'label' => 'Next Review'],
                ['key' => 'risk_review', 'label' => 'Risk Review'],
                ['key' => 'overdue', 'label' => 'Stages Overdue'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Care Delivery -------------------------------------------------------

    public function careTasks(): array
    {
        $visits = $this->visitsInRange()
            ->whereIn('status', ['completed', 'in_progress', 'missed'])
            ->with(['serviceUser', 'carer'])
            ->orderBy('visit_date')->orderBy('start_time')
            ->get()
            ->filter(fn (Visit $v) => ! empty($v->care_tasks));

        return [
            'title' => 'Care Tasks Completed / Not Completed',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'carer', 'label' => 'Carer'],
                ['key' => 'planned', 'label' => 'Tasks Planned'],
                ['key' => 'done', 'label' => 'Completed'],
                ['key' => 'rate', 'label' => '% Done'],
                ['key' => 'not_done', 'label' => 'Not Completed'],
            ],
            'rows' => $visits->map(function (Visit $v) {
                $planned = array_values($v->care_tasks ?? []);
                $done = array_values(array_intersect($planned, $v->completed_care_tasks ?? []));
                $notDone = array_values(array_diff($planned, $done));

                return [
                    'date' => $v->visit_date->toDateString(),
                    'client' => $this->clientName($v->serviceUser),
                    'carer' => $v->carer->name ?? 'Unassigned',
                    'planned' => count($planned),
                    'done' => count($done),
                    'rate' => $this->percent(count($done), count($planned)),
                    'not_done' => $notDone === [] ? '—' : implode(', ', $notDone),
                ];
            })->values(),
        ];
    }

    /** Booked hours against hours actually delivered, per client. */
    public function carePackageUtilization(): array
    {
        $byClient = $this->visitsInRange()->where('status', '!=', 'cancelled')->with('serviceUser.funder')->get()->groupBy('service_user_id');

        return [
            'title' => 'Care Package Utilisation',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'funder', 'label' => 'Funder'],
                ['key' => 'visits', 'label' => 'Visits Booked'],
                ['key' => 'booked', 'label' => 'Hours Booked'],
                ['key' => 'delivered', 'label' => 'Hours Delivered'],
                ['key' => 'utilisation', 'label' => 'Utilisation'],
            ],
            'rows' => $byClient->map(function (Collection $visits) {
                $su = $visits->first()->serviceUser;
                $booked = $visits->sum(fn (Visit $v) => $this->scheduledHours($v));
                $delivered = $visits->sum(fn (Visit $v) => $this->deliveredHours($v));

                return [
                    'client' => $this->clientName($su),
                    'funder' => $su?->funder->name ?? ($su?->funding_source ?? '—'),
                    'visits' => $visits->count(),
                    'booked' => round($booked, 2),
                    'delivered' => round($delivered, 2),
                    'utilisation' => $this->percent($delivered, $booked),
                ];
            })->sortBy('client')->values(),
        ];
    }

    // ---- Medication -----------------------------------------------------------

    /** Stock-tracked medications, the ones to reorder first. */
    public function medicationStock(): array
    {
        $medications = Medication::where('status', 'active')
            ->whereNull('archived_at')
            ->whereNotNull('stock_on_hand')
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with('serviceUser')
            ->get();

        return [
            'title' => 'Medication Stock / Reorder',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'stock', 'label' => 'Stock on Hand'],
                ['key' => 'per_day', 'label' => 'Units a Day'],
                ['key' => 'days_left', 'label' => 'Days Left'],
                ['key' => 'reorder_level', 'label' => 'Reorder Level'],
                ['key' => 'pharmacy', 'label' => 'Pharmacy'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $medications->sortBy(fn (Medication $m) => [$m->needsReorder() ? 0 : 1, $m->daysOfStockLeft() ?? PHP_INT_MAX])
                ->map(fn (Medication $m) => [
                    'client' => $this->clientName($m->serviceUser),
                    'medication' => trim("{$m->name} {$m->strength}"),
                    'stock' => $m->stock_on_hand + 0,
                    'per_day' => $m->dosesPerDay() ? $m->dosesPerDay() * $m->units_per_dose : 'PRN',
                    'days_left' => $m->daysOfStockLeft() ?? '—',
                    'reorder_level' => $m->reorder_level !== null ? $m->reorder_level + 0 : '—',
                    'pharmacy' => $m->pharmacy ?? '—',
                    'status' => match (true) {
                        $m->stock_on_hand <= 0 => 'Out of stock',
                        $m->needsReorder() => 'Reorder now',
                        default => 'OK',
                    },
                ])->values(),
        ];
    }

    // ---- Clinical ---------------------------------------------------------------

    /**
     * Each wound assessment in the period, compared with the previous one
     * for the same client and wound site (including assessments from before
     * the period), so you can see whether it's healing.
     */
    public function woundProgress(): array
    {
        $wounds = Observation::where('type', 'wound')
            ->where('recorded_at', '<=', $this->to())
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with(['serviceUser', 'recordedBy'])
            ->orderBy('recorded_at')
            ->get();

        $area = fn (array $v) => isset($v['length_cm'], $v['width_cm']) ? round((float) $v['length_cm'] * (float) $v['width_cm'], 2) : null;

        $rows = $wounds->groupBy(fn (Observation $o) => $o->service_user_id.'|'.mb_strtolower(trim((string) ($o->value['site'] ?? ''))))
            ->flatMap(function (Collection $assessments) use ($area) {
                $previousArea = null;

                return $assessments->map(function (Observation $o) use (&$previousArea, $area) {
                    $v = $o->value ?? [];
                    $current = $area($v);
                    $change = match (true) {
                        $current === null || $previousArea === null => '—',
                        $previousArea == 0.0 => $current > 0 ? 'Larger' : 'No change',
                        default => (($pct = round(($current - $previousArea) / $previousArea * 100)) < 0 ? 'Smaller by '.abs($pct).'%' : ($pct > 0 ? "Larger by {$pct}%" : 'No change')),
                    };
                    $previousArea = $current ?? $previousArea;

                    return [
                        'when' => $o->recorded_at,
                        'date' => $this->local($o->recorded_at)->format('Y-m-d'),
                        'client' => $this->clientName($o->serviceUser),
                        'site' => $v['site'] ?? '—',
                        'size' => isset($v['length_cm'], $v['width_cm'])
                            ? $v['length_cm'].' × '.$v['width_cm'].(isset($v['depth_cm']) ? ' × '.$v['depth_cm'] : '').' cm'
                            : '—',
                        'area' => $current ?? '—',
                        'change' => $change,
                        'stage' => $this->label($v['stage'] ?? null),
                        'appearance' => $this->label($v['appearance'] ?? null),
                        'exudate' => $this->label($v['exudate'] ?? null),
                        'recorded_by' => $o->recordedBy->name ?? '—',
                    ];
                });
            })
            ->filter(fn ($r) => $r['when']->gte($this->from()))
            ->sortByDesc('when')
            ->map(fn ($r) => collect($r)->except('when')->all())
            ->values();

        return [
            'title' => 'Wound Progress',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'site', 'label' => 'Site'],
                ['key' => 'size', 'label' => 'Size (L × W × D)'],
                ['key' => 'area', 'label' => 'Area (cm²)'],
                ['key' => 'change', 'label' => 'Since Last'],
                ['key' => 'stage', 'label' => 'Stage'],
                ['key' => 'appearance', 'label' => 'Wound Bed'],
                ['key' => 'exudate', 'label' => 'Exudate'],
                ['key' => 'recorded_by', 'label' => 'Recorded By'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Staff & Workforce -----------------------------------------------------

    /** Each rostered shift checked against the staff member's clock-ins. */
    public function staffAttendance(): array
    {
        $grace = (int) $this->setting('late_arrival_minutes', 10);
        $shifts = Shift::whereBetween('shift_date', [$this->filters['from'], $this->filters['to']])
            ->where('status', '!=', 'cancelled')
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->with('user')
            ->orderBy('shift_date')->orderBy('start_time')
            ->get();
        $duty = DutyPeriod::whereIn('user_id', $shifts->pluck('user_id')->unique())
            ->whereBetween('started_at', [$this->from()->copy()->subDay(), $this->to()->copy()->addDay()])
            ->get()->groupBy('user_id');
        $leave = LeaveRequest::where('status', 'approved')
            ->whereIn('user_id', $shifts->pluck('user_id')->unique())
            ->where('start_date', '<=', $this->filters['to'])->where('end_date', '>=', $this->filters['from'])
            ->get()->groupBy('user_id');

        return [
            'title' => 'Staff Attendance',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'shift', 'label' => 'Shift'],
                ['key' => 'clocked_in', 'label' => 'Clocked In'],
                ['key' => 'clocked_out', 'label' => 'Clocked Out'],
                ['key' => 'status', 'label' => 'Attendance'],
            ],
            'rows' => $shifts->map(function (Shift $s) use ($duty, $leave, $grace) {
                $date = $s->shift_date->toDateString();
                $start = TenantClock::wallClock($this->tenantId(), $date, $s->start_time);
                $end = TenantClock::wallClock($this->tenantId(), $date, $s->end_time);
                if ($end->lte($start)) {
                    $end->addDay();
                }
                // The clock-in nearest the shift start, from two hours before until the shift ends.
                $period = ($duty[$s->user_id] ?? collect())
                    ->filter(fn (DutyPeriod $d) => $d->started_at->between($start->copy()->subHours(2), $end))
                    ->sortBy(fn (DutyPeriod $d) => abs($d->started_at->diffInMinutes($start, false)))
                    ->first();
                $onLeave = ($leave[$s->user_id] ?? collect())
                    ->contains(fn (LeaveRequest $l) => $date >= $l->start_date->toDateString() && $date <= $l->end_date->toDateString());

                $status = match (true) {
                    $period !== null => $period->started_at->gt($start->copy()->addMinutes($grace))
                        ? 'Late ('.$start->diffInMinutes($period->started_at).' min)'
                        : 'Attended',
                    $onLeave => 'On leave',
                    $start->isFuture() => 'Upcoming',
                    default => 'Absent',
                };

                return [
                    'date' => $date,
                    'staff' => $s->user->name ?? '—',
                    'shift' => $this->hm($s->start_time).'–'.$this->hm($s->end_time),
                    'clocked_in' => $period ? $this->local($period->started_at)->format('H:i') : '—',
                    'clocked_out' => $period?->ended_at ? $this->local($period->ended_at)->format('H:i') : ($period ? 'Still on duty' : '—'),
                    'status' => $status,
                ];
            })->values(),
        ];
    }

    public function clockInOut(): array
    {
        $periods = DutyPeriod::whereBetween('started_at', [$this->from(), $this->to()])
            ->when($this->branchId(), fn ($q) => $q->whereHas('carer.staffProfile', fn ($sp) => $sp->where('branch_id', $this->branchId())))
            ->with(['carer', 'closedBy'])
            ->orderByDesc('started_at')
            ->get();

        return [
            'title' => 'Clock-in / Clock-out',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'clock_in', 'label' => 'Clocked In'],
                ['key' => 'clock_out', 'label' => 'Clocked Out'],
                ['key' => 'hours', 'label' => 'Hours'],
                ['key' => 'closed', 'label' => 'Closed By'],
            ],
            'rows' => $periods->map(fn (DutyPeriod $d) => [
                'staff' => $d->carer->name ?? '—',
                'clock_in' => $this->local($d->started_at)->format('Y-m-d H:i'),
                'clock_out' => $d->ended_at ? $this->local($d->ended_at)->format('Y-m-d H:i') : 'Still on duty',
                'hours' => $d->ended_at ? round($this->hoursBetween($d->started_at, $d->ended_at), 2) : '—',
                'closed' => $d->closed_by ? (($d->closedBy->name ?? 'Manager').($d->close_reason ? " — {$d->close_reason}" : '')) : ($d->ended_at ? 'Self' : '—'),
            ]),
        ];
    }

    /**
     * Hours worked per staff member per week — clocked hours where they
     * clocked in, otherwise completed visit hours.
     *
     * @return Collection<int, array{user: User, week: string, clocked: float, visits: float, hours: float}>
     */
    private function weeklyWorkedHours(): Collection
    {
        $duty = DutyPeriod::whereBetween('started_at', [$this->from(), $this->to()])->whereNotNull('ended_at')->get();
        $visits = $this->visitsInRange()->where('status', 'completed')->whereNotNull('carer_id')->get();
        $week = fn (Carbon $d) => $d->copy()->startOfWeek()->toDateString();

        $clocked = $duty->groupBy(fn (DutyPeriod $d) => $d->user_id.'|'.$week($this->local($d->started_at)))
            ->map(fn ($g) => $g->sum(fn (DutyPeriod $d) => $this->hoursBetween($d->started_at, $d->ended_at)));
        $visitHours = $visits->groupBy(fn (Visit $v) => $v->carer_id.'|'.$week($v->visit_date))
            ->map(fn ($g) => $g->sum(fn (Visit $v) => $this->deliveredHours($v)));

        $staff = $this->staffInScope()->keyBy('user_id');

        return $clocked->keys()->merge($visitHours->keys())->unique()
            ->map(function (string $key) use ($clocked, $visitHours, $staff) {
                [$userId, $weekStart] = explode('|', $key);
                if (! $staff->has((int) $userId)) {
                    return null;
                }
                $c = (float) ($clocked[$key] ?? 0);
                $v = (float) ($visitHours[$key] ?? 0);

                return ['user' => $staff[(int) $userId]->user, 'week' => $weekStart, 'clocked' => $c, 'visits' => $v, 'hours' => $c > 0 ? $c : $v];
            })
            ->filter()
            ->values();
    }

    public function overtime(): array
    {
        $threshold = (float) $this->setting('overtime_weekly_hours', 40);
        $rates = StaffProfile::pluck('hourly_rate', 'user_id');

        $rows = $this->weeklyWorkedHours()
            ->filter(fn ($w) => $w['hours'] > $threshold)
            ->sortBy(['week', fn ($a, $b) => $b['hours'] <=> $a['hours']])
            ->map(fn ($w) => [
                'week' => 'w/c '.$w['week'],
                'staff' => $w['user']->name,
                'hours' => round($w['hours'], 2),
                'overtime' => round($w['hours'] - $threshold, 2),
                'cost' => isset($rates[$w['user']->id]) ? round(($w['hours'] - $threshold) * (float) $rates[$w['user']->id], 2) : '—',
                'source' => $w['clocked'] > 0 ? 'Clock-ins' : 'Completed visits',
            ])->values();

        return [
            'title' => "Overtime (over {$threshold} hours a week)",
            'columns' => [
                ['key' => 'week', 'label' => 'Week'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'hours', 'label' => 'Hours Worked'],
                ['key' => 'overtime', 'label' => 'Overtime Hours'],
                ['key' => 'cost', 'label' => 'Cost at Base Rate'],
                ['key' => 'source', 'label' => 'From'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Sickness absence per staff member, with the Bradford factor
     * (spells² × days) many UK providers use to flag frequent short absences.
     */
    public function sickness(): array
    {
        $leave = LeaveRequest::where('type', 'sick')
            ->whereIn('status', ['approved', 'pending'])
            ->where('start_date', '<=', $this->filters['to'])->where('end_date', '>=', $this->filters['from'])
            ->when($this->branchId(), fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $this->branchId())))
            ->with('user')
            ->get();

        $rows = $leave->groupBy('user_id')->map(function (Collection $spells) {
            $days = $spells->sum(function (LeaveRequest $l) {
                $start = $l->start_date->max($this->from());
                $end = $l->end_date->min($this->to());

                return $start->diffInDays($end->copy()->startOfDay()) + 1;
            });
            $count = $spells->count();

            return [
                'staff' => $spells->first()->user->name ?? '—',
                'spells' => $count,
                'days' => (int) $days,
                'bradford' => $count * $count * (int) $days,
                'latest' => $spells->max(fn ($l) => $l->start_date->toDateString()),
                'reasons' => $spells->pluck('reason')->filter()->unique()->implode('; ') ?: '—',
            ];
        })->sortByDesc('bradford')->values();

        return [
            'title' => 'Sickness Absence',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'spells', 'label' => 'Spells'],
                ['key' => 'days', 'label' => 'Days Off Sick'],
                ['key' => 'bradford', 'label' => 'Bradford Factor'],
                ['key' => 'latest', 'label' => 'Most Recent'],
                ['key' => 'reasons', 'label' => 'Reasons'],
            ],
            'rows' => $rows,
        ];
    }

    /** Visits in the period that still have no carer. */
    public function unfilledShifts(): array
    {
        $visits = $this->visitsInRange()
            ->whereNull('carer_id')
            ->whereNotIn('status', ['cancelled'])
            ->with('serviceUser')
            ->orderBy('visit_date')->orderBy('start_time')
            ->get();

        return [
            'title' => 'Unfilled Visits',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'time', 'label' => 'Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'hours', 'label' => 'Hours'],
                ['key' => 'skills', 'label' => 'Skills Needed'],
                ['key' => 'priority', 'label' => 'Priority'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $visits->map(fn (Visit $v) => [
                'date' => $v->visit_date->toDateString(),
                'time' => $this->hm($v->start_time).'–'.$this->hm($v->end_time),
                'client' => $this->clientName($v->serviceUser),
                'hours' => round($this->scheduledHours($v), 2),
                'skills' => implode(', ', $v->required_skills ?? []) ?: '—',
                'priority' => $this->label($v->priority),
                'status' => $this->label($v->status),
            ]),
        ];
    }

    /** Rostered (shift) hours against hours booked on visits, per staff member. */
    public function staffUtilization(): array
    {
        $shiftHours = Shift::whereBetween('shift_date', [$this->filters['from'], $this->filters['to']])
            ->where('status', '!=', 'cancelled')->get()
            ->groupBy('user_id')
            ->map(fn ($g) => $g->sum(function (Shift $s) {
                $m = $this->minutes($s->end_time) - $this->minutes($s->start_time);

                return ($m <= 0 ? $m + 1440 : $m) / 60;
            }));
        $visitHours = $this->visitsInRange()->whereNotNull('carer_id')->where('status', '!=', 'cancelled')->get()
            ->groupBy('carer_id')->map(fn ($g) => $g->sum(fn (Visit $v) => $this->scheduledHours($v)));

        $rows = $this->staffInScope()
            ->filter(fn (StaffProfile $sp) => isset($shiftHours[$sp->user_id]) || isset($visitHours[$sp->user_id]))
            ->map(fn (StaffProfile $sp) => [
                'staff' => $sp->user->name,
                'job_title' => $sp->job_title ?? '—',
                'rostered' => round((float) ($shiftHours[$sp->user_id] ?? 0), 2),
                'booked' => round((float) ($visitHours[$sp->user_id] ?? 0), 2),
                'utilisation' => $this->percent((float) ($visitHours[$sp->user_id] ?? 0), (float) ($shiftHours[$sp->user_id] ?? 0)),
            ])
            ->sortByDesc('booked')->values();

        return [
            'title' => 'Staff Utilisation',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'job_title', 'label' => 'Role'],
                ['key' => 'rostered', 'label' => 'Rostered Hours'],
                ['key' => 'booked', 'label' => 'Visit Hours Booked'],
                ['key' => 'utilisation', 'label' => 'Utilisation'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Travel (GPS) -------------------------------------------------------------

    /**
     * Distance travelled per staff member per day. Taken from the GPS track
     * when there is one (noisy fixes and impossible jumps dropped); without
     * a track, estimated as the straight line between the day's clients.
     * Travel time is the gap between leaving one visit and arriving at the next.
     *
     * @return Collection<int, array{user: User, date: string, meters: float, travel_minutes: int, visits: int, source: string}>
     */
    private function dailyTravel(): Collection
    {
        $staff = $this->staffInScope()->keyBy('user_id');
        $points = CarerLocation::whereBetween('recorded_at', [$this->from(), $this->to()])
            ->whereIn('user_id', $staff->keys())
            ->where(fn ($q) => $q->whereNull('accuracy')->orWhere('accuracy', '<=', self::MAX_GPS_ACCURACY_METERS))
            ->orderBy('recorded_at')
            ->get(['user_id', 'latitude', 'longitude', 'recorded_at'])
            ->groupBy(fn ($p) => $p->user_id.'|'.$this->local($p->recorded_at)->toDateString());
        $visits = $this->visitsInRange()->whereNotNull('carer_id')->where('status', '!=', 'cancelled')
            ->with('serviceUser')->orderBy('start_time')->get()
            ->groupBy(fn (Visit $v) => $v->carer_id.'|'.$v->visit_date->toDateString());

        return $points->keys()->merge($visits->keys())->unique()->map(function (string $key) use ($points, $visits, $staff) {
            [$userId, $date] = explode('|', $key);
            if (! $staff->has((int) $userId)) {
                return null;
            }

            $meters = 0.0;
            $source = 'GPS';
            $track = $points[$key] ?? collect();
            if ($track->count() >= 2) {
                $prev = null;
                foreach ($track as $p) {
                    if ($prev) {
                        $d = Haversine::distanceInMeters((float) $prev->latitude, (float) $prev->longitude, (float) $p->latitude, (float) $p->longitude);
                        $hours = max($prev->recorded_at->diffInSeconds($p->recorded_at), 1) / 3600;
                        if ($d / 1000 / $hours <= self::MAX_PLAUSIBLE_SPEED_KMH) {
                            $meters += $d;
                        }
                    }
                    $prev = $p;
                }
            } else {
                $source = 'Estimated (client to client)';
                $located = ($visits[$key] ?? collect())->filter(fn (Visit $v) => $v->serviceUser?->latitude !== null)->values();
                for ($i = 1; $i < $located->count(); $i++) {
                    $meters += Haversine::distanceInMeters(
                        (float) $located[$i - 1]->serviceUser->latitude, (float) $located[$i - 1]->serviceUser->longitude,
                        (float) $located[$i]->serviceUser->latitude, (float) $located[$i]->serviceUser->longitude,
                    );
                }
            }

            $dayVisits = ($visits[$key] ?? collect())->sortBy('start_time')->values();
            $travel = 0;
            for ($i = 1; $i < $dayVisits->count(); $i++) {
                $out = $dayVisits[$i - 1]->check_out_at;
                $in = $dayVisits[$i]->check_in_at;
                if ($out && $in && $in->gt($out)) {
                    $travel += (int) $out->diffInMinutes($in);
                }
            }

            return [
                'user' => $staff[(int) $userId]->user,
                'date' => $date,
                'meters' => $meters,
                'travel_minutes' => $travel,
                'visits' => $dayVisits->count(),
                'source' => $source,
            ];
        })->filter(fn ($r) => $r && ($r['meters'] > 0 || $r['travel_minutes'] > 0))->sortBy('date')->values();
    }

    public function travelByDay(string $title): array
    {
        return [
            'title' => $title,
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'visits', 'label' => 'Visits'],
                ['key' => 'km', 'label' => 'Distance (km)'],
                ['key' => 'miles', 'label' => 'Distance (miles)'],
                ['key' => 'travel_time', 'label' => 'Travel Time'],
                ['key' => 'source', 'label' => 'Source'],
            ],
            'rows' => $this->dailyTravel()->map(fn ($d) => [
                'date' => $d['date'],
                'staff' => $d['user']->name,
                'visits' => $d['visits'],
                'km' => round($d['meters'] / 1000, 1),
                'miles' => round($d['meters'] / self::METERS_PER_MILE, 1),
                'travel_time' => $d['travel_minutes'] ? intdiv($d['travel_minutes'], 60).'h '.($d['travel_minutes'] % 60).'m' : '—',
                'source' => $d['source'],
            ]),
        ];
    }

    /** Total miles per staff member, optionally priced at the tenant's mileage rate. */
    public function mileage(bool $withReimbursement): array
    {
        $rate = (float) $this->setting('mileage_rate_per_mile', 0.45);
        $currency = $this->tenant()?->currency ?? 'GBP';

        $rows = $this->dailyTravel()->groupBy(fn ($d) => $d['user']->id)->map(function (Collection $days) use ($rate, $withReimbursement) {
            $miles = $days->sum('meters') / self::METERS_PER_MILE;

            return array_filter([
                'staff' => $days->first()['user']->name,
                'days' => $days->count(),
                'miles' => round($miles, 1),
                'km' => round($days->sum('meters') / 1000, 1),
                'estimated' => $days->where('source', '!=', 'GPS')->count() ? $days->where('source', '!=', 'GPS')->count().' day(s) estimated' : 'All GPS',
                'reimbursement' => $withReimbursement ? round($miles * $rate, 2) : null,
            ], fn ($v) => $v !== null);
        })->sortByDesc('miles')->values();

        $columns = [
            ['key' => 'staff', 'label' => 'Staff'],
            ['key' => 'days', 'label' => 'Days Travelled'],
            ['key' => 'miles', 'label' => 'Miles'],
            ['key' => 'km', 'label' => 'Kilometres'],
            ['key' => 'estimated', 'label' => 'Source'],
        ];
        if ($withReimbursement) {
            $columns[] = ['key' => 'reimbursement', 'label' => "Reimbursement ({$currency} @ {$rate}/mile)"];
        }

        return [
            'title' => $withReimbursement ? 'Mileage Reimbursement' : 'Mileage',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    // ---- Rostering ----------------------------------------------------------------

    /** Per day: who's rostered, who's booked on visits, who's off, and what's uncovered. */
    public function staffingByDay(string $mode): array
    {
        $shifts = Shift::whereBetween('shift_date', [$this->filters['from'], $this->filters['to']])
            ->where('status', '!=', 'cancelled')
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->get()->groupBy(fn (Shift $s) => $s->shift_date->toDateString());
        $visits = $this->visitsInRange()->where('status', '!=', 'cancelled')->get()
            ->groupBy(fn (Visit $v) => $v->visit_date->toDateString());
        $leave = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $this->filters['to'])->where('end_date', '>=', $this->filters['from'])
            ->get();

        $rows = collect(CarbonPeriod::create($this->filters['from'], $this->filters['to']))->map(function (Carbon $day) use ($shifts, $visits, $leave) {
            $date = $day->toDateString();
            $dayVisits = $visits[$date] ?? collect();
            $unassigned = $dayVisits->whereNull('carer_id');

            return [
                'date' => $date,
                'day' => $day->format('D'),
                'rostered' => ($shifts[$date] ?? collect())->pluck('user_id')->unique()->count(),
                'assigned' => $dayVisits->pluck('carer_id')->filter()->unique()->count(),
                'on_leave' => $leave->filter(fn ($l) => $date >= $l->start_date->toDateString() && $date <= $l->end_date->toDateString())->pluck('user_id')->unique()->count(),
                'visits' => $dayVisits->count(),
                'unassigned' => $unassigned->count(),
                'unassigned_hours' => round($unassigned->sum(fn (Visit $v) => $this->scheduledHours($v)), 2),
                'demand_hours' => round($dayVisits->sum(fn (Visit $v) => $this->scheduledHours($v)), 2),
                'supply_hours' => round(($shifts[$date] ?? collect())->sum(function (Shift $s) {
                    $m = $this->minutes($s->end_time) - $this->minutes($s->start_time);

                    return ($m <= 0 ? $m + 1440 : $m) / 60;
                }), 2),
            ];
        });

        return match ($mode) {
            'gaps' => [
                'title' => 'Staffing Gaps',
                'columns' => [
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'day', 'label' => 'Day'],
                    ['key' => 'unassigned', 'label' => 'Visits Without a Carer'],
                    ['key' => 'unassigned_hours', 'label' => 'Uncovered Hours'],
                    ['key' => 'on_leave', 'label' => 'Staff on Leave'],
                ],
                'rows' => $rows->filter(fn ($r) => $r['unassigned'] > 0)->values(),
            ],
            'shortages' => [
                'title' => 'Staff Shortages (visit hours needed vs rostered hours)',
                'columns' => [
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'day', 'label' => 'Day'],
                    ['key' => 'demand_hours', 'label' => 'Visit Hours Needed'],
                    ['key' => 'supply_hours', 'label' => 'Rostered Hours'],
                    ['key' => 'shortfall', 'label' => 'Shortfall'],
                    ['key' => 'unassigned', 'label' => 'Unassigned Visits'],
                    ['key' => 'on_leave', 'label' => 'Staff on Leave'],
                ],
                'rows' => $rows
                    ->map(fn ($r) => [...$r, 'shortfall' => round(max(0, $r['demand_hours'] - $r['supply_hours']), 2)])
                    ->filter(fn ($r) => $r['shortfall'] > 0 || $r['unassigned'] > 0)
                    ->values(),
            ],
            default => [
                'title' => 'Assigned vs Available Staff',
                'columns' => [
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'day', 'label' => 'Day'],
                    ['key' => 'rostered', 'label' => 'Staff Rostered'],
                    ['key' => 'assigned', 'label' => 'Staff on Visits'],
                    ['key' => 'on_leave', 'label' => 'Staff on Leave'],
                    ['key' => 'visits', 'label' => 'Visits'],
                    ['key' => 'unassigned', 'label' => 'Unassigned Visits'],
                ],
                'rows' => $rows->values(),
            ],
        };
    }

    /** Staff whose booked hours in a week are at or near the overtime threshold. */
    public function overtimeRisk(): array
    {
        $threshold = (float) $this->setting('overtime_weekly_hours', 40);
        $week = fn (Carbon $d) => $d->copy()->startOfWeek()->toDateString();
        $staff = $this->staffInScope()->keyBy('user_id');

        $booked = $this->visitsInRange()->whereNotNull('carer_id')->whereNotIn('status', ['cancelled', 'missed'])->get()
            ->groupBy(fn (Visit $v) => $v->carer_id.'|'.$week($v->visit_date))
            ->map(fn ($g) => $g->sum(fn (Visit $v) => $this->scheduledHours($v)));
        $rostered = Shift::whereBetween('shift_date', [$this->filters['from'], $this->filters['to']])->where('status', '!=', 'cancelled')->get()
            ->groupBy(fn (Shift $s) => $s->user_id.'|'.$week($s->shift_date))
            ->map(fn ($g) => $g->sum(function (Shift $s) {
                $m = $this->minutes($s->end_time) - $this->minutes($s->start_time);

                return ($m <= 0 ? $m + 1440 : $m) / 60;
            }));

        $rows = $booked->keys()->merge($rostered->keys())->unique()->map(function ($key) use ($booked, $rostered, $staff, $threshold) {
            [$userId, $weekStart] = explode('|', $key);
            if (! $staff->has((int) $userId)) {
                return null;
            }
            $hours = max((float) ($booked[$key] ?? 0), (float) ($rostered[$key] ?? 0));
            if ($hours < $threshold * 0.9) {
                return null;
            }

            return [
                'week' => 'w/c '.$weekStart,
                'staff' => $staff[(int) $userId]->user->name,
                'booked' => round((float) ($booked[$key] ?? 0), 2),
                'rostered' => round((float) ($rostered[$key] ?? 0), 2),
                'risk' => $hours > $threshold ? 'Over by '.round($hours - $threshold, 1).'h' : 'Within '.round($threshold - $hours, 1).'h of limit',
            ];
        })->filter()->sortBy('week')->values();

        return [
            'title' => "Overtime Risk (limit {$threshold} hours a week)",
            'columns' => [
                ['key' => 'week', 'label' => 'Week'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'booked', 'label' => 'Visit Hours Booked'],
                ['key' => 'rostered', 'label' => 'Rostered Hours'],
                ['key' => 'risk', 'label' => 'Risk'],
            ],
            'rows' => $rows,
        ];
    }

    /** Each client's care team, who actually visited, and how consistent their carers were. */
    public function clientCarerAllocation(): array
    {
        $clients = ServiceUser::where('status', 'active')
            ->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))
            ->with(['carers', 'careManager'])
            ->orderBy('first_name')->get();
        $visits = $this->visitsInRange()->whereNotNull('carer_id')->whereNotIn('status', ['cancelled'])->with('carer')->get()
            ->groupBy('service_user_id');

        return [
            'title' => 'Client-to-Carer Allocation',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'care_manager', 'label' => 'Care Manager'],
                ['key' => 'care_team', 'label' => 'Care Team'],
                ['key' => 'visits', 'label' => 'Visits'],
                ['key' => 'carers_seen', 'label' => 'Carers Who Visited'],
                ['key' => 'continuity', 'label' => 'Visits by Care Team'],
            ],
            'rows' => $clients->map(function (ServiceUser $su) use ($visits) {
                $v = $visits[$su->id] ?? collect();
                $team = $su->carers->pluck('id');
                $byCarer = $v->groupBy('carer_id')->map(fn ($g) => $g->first()->carer->name.' ('.$g->count().')');

                return [
                    'client' => $this->clientName($su),
                    'care_manager' => $su->careManager->name ?? '—',
                    'care_team' => $su->carers->pluck('name')->implode(', ') ?: 'None set',
                    'visits' => $v->count(),
                    'carers_seen' => $byCarer->implode(', ') ?: '—',
                    'continuity' => $team->isEmpty() ? '—' : $this->percent($v->whereIn('carer_id', $team)->count(), $v->count()),
                ];
            })->values(),
        ];
    }

    // ---- Training & Compliance ----------------------------------------------------

    /**
     * Each active staff member's most recent background check (DBS, PVG,
     * police clearance), found by document category or filename.
     */
    public function backgroundChecks(): array
    {
        $pattern = '/\b(dbs|pvg|background|police|criminal|vetting)\b/i';
        $soon = now()->addDays((int) $this->setting('training_expiry_warning_days', 30));

        $rows = $this->staffInScope()
            ->filter(fn (StaffProfile $sp) => $sp->employment_status !== 'inactive')
            ->map(function (StaffProfile $sp) use ($pattern, $soon) {
                $check = $sp->documents()->get()
                    ->filter(fn (Document $d) => preg_match($pattern, (string) $d->category) || preg_match($pattern, (string) $d->original_filename))
                    ->sortByDesc('created_at')
                    ->first();

                $status = match (true) {
                    $check === null => 'No check on file',
                    $check->expiry_date === null => 'On file (no expiry)',
                    $check->expiry_date->isPast() => 'Expired',
                    $check->expiry_date->lte($soon) => 'Expiring soon',
                    default => 'Valid',
                };

                return [
                    'staff' => $sp->user->name,
                    'job_title' => $sp->job_title ?? '—',
                    'document' => $check?->original_filename ?? '—',
                    'uploaded' => $check?->created_at->toDateString() ?? '—',
                    'expiry' => $check?->expiry_date?->toDateString() ?? '—',
                    'status' => $status,
                    'sort' => ['No check on file' => 0, 'Expired' => 1, 'Expiring soon' => 2, 'On file (no expiry)' => 3, 'Valid' => 4][$status],
                ];
            })
            ->sortBy('sort')->map(fn ($r) => collect($r)->except('sort')->all())->values();

        return [
            'title' => 'Background Check Status',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'job_title', 'label' => 'Role'],
                ['key' => 'document', 'label' => 'Check Document'],
                ['key' => 'uploaded', 'label' => 'Uploaded'],
                ['key' => 'expiry', 'label' => 'Expires'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Quality & Audit --------------------------------------------------------------

    public function complaints(): array
    {
        $complaints = Complaint::whereBetween('received_date', [$this->filters['from'], $this->filters['to']])
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())))
            ->with(['serviceUser', 'assignedTo'])
            ->orderByDesc('received_date')
            ->get();

        return [
            'title' => 'Complaints',
            'columns' => [
                ['key' => 'received', 'label' => 'Received'],
                ['key' => 'complainant', 'label' => 'Complainant'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'assigned_to', 'label' => 'Investigating'],
                ['key' => 'days', 'label' => 'Days to Resolve'],
                ['key' => 'response', 'label' => 'Response'],
                ['key' => 'outcome', 'label' => 'Outcome'],
            ],
            'rows' => $complaints->map(fn (Complaint $c) => [
                'received' => $c->received_date->toDateString(),
                'complainant' => $c->complainant_name.($c->complainant_relationship ? " ({$c->complainant_relationship})" : ''),
                'client' => $c->serviceUser ? $this->clientName($c->serviceUser) : '—',
                'category' => $this->label($c->category),
                'severity' => $this->label($c->severity),
                'status' => $this->label($c->status),
                'assigned_to' => $c->assignedTo->name ?? 'Unassigned',
                'days' => $c->resolved_date ? (int) $c->received_date->diffInDays($c->resolved_date) : (int) $c->received_date->diffInDays(Carbon::parse(TenantClock::today($this->tenantId()))).' (open)',
                'response' => match (true) {
                    $c->isOverdue() => 'Overdue (due '.$c->response_due_date->toDateString().')',
                    $c->isOpen() => 'Due '.($c->response_due_date?->toDateString() ?? '—'),
                    $c->resolved_date && $c->response_due_date && $c->resolved_date->gt($c->response_due_date) => 'Resolved late',
                    default => 'On time',
                },
                'outcome' => $this->label($c->outcome),
            ]),
        ];
    }

    public function spotChecks(): array
    {
        $checks = SpotCheck::whereBetween('check_date', [$this->filters['from'], $this->filters['to']])
            ->when($this->branchId(), fn ($q) => $q->whereHas('staff.staffProfile', fn ($sp) => $sp->where('branch_id', $this->branchId())))
            ->with(['staff', 'checkedBy', 'serviceUser'])
            ->orderByDesc('check_date')
            ->get();

        return [
            'title' => 'Spot Checks',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff', 'label' => 'Staff Checked'],
                ['key' => 'checked_by', 'label' => 'Checked By'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'outcome', 'label' => 'Outcome'],
                ['key' => 'failed', 'label' => 'Areas Failed'],
                ['key' => 'actions', 'label' => 'Actions Required'],
                ['key' => 'follow_up', 'label' => 'Follow Up'],
            ],
            'rows' => $checks->map(fn (SpotCheck $c) => [
                'date' => $c->check_date->toDateString(),
                'staff' => $c->staff->name ?? '—',
                'checked_by' => $c->checkedBy->name ?? '—',
                'client' => $c->serviceUser ? $this->clientName($c->serviceUser) : '—',
                'outcome' => $this->label($c->outcome),
                'failed' => collect($c->results ?? [])->filter(fn ($r) => $r === 'fail')->keys()->map(fn ($a) => $this->label($a))->implode(', ') ?: '—',
                'actions' => $c->actions_required ?? '—',
                'follow_up' => $c->follow_up_date?->toDateString() ?? '—',
            ]),
        ];
    }

    /**
     * Headline quality measures for the period, the kind a registered
     * manager reports to the board or an inspector.
     */
    public function serviceQualityIndicators(): array
    {
        $visits = $this->visitsInRange()->whereNotIn('status', ['cancelled'])->get();
        $today = TenantClock::today($this->tenantId());
        $grace = (int) $this->setting('late_arrival_minutes', 10);
        $past = $visits->filter(fn (Visit $v) => $v->visit_date->toDateString() < $today || $v->status === 'completed');
        $completed = $visits->where('status', 'completed');
        $onTime = $completed->filter(fn (Visit $v) => $v->check_in_at
            && $v->check_in_at->lte(TenantClock::wallClock($this->tenantId(), $v->visit_date->toDateString(), $v->start_time)->addMinutes($grace)));

        $doses = MedicationAdministration::whereBetween('administered_at', [$this->from(), $this->to()])
            ->where('status', '!=', 'prn')->get();
        $incidents = Incident::whereBetween('created_at', [$this->from(), $this->to()])->count();
        $complaints = Complaint::whereBetween('received_date', [$this->filters['from'], $this->filters['to']])->get();
        $openComplaints = Complaint::whereIn('status', Complaint::OPEN_STATUSES)->get();
        $spotChecks = SpotCheck::whereBetween('check_date', [$this->filters['from'], $this->filters['to']])->get();
        $activeClients = ServiceUser::where('status', 'active')->when($this->branchId(), fn ($q) => $q->where('branch_id', $this->branchId()))->count();
        $plansOverdue = CarePlan::where('status', 'active')
            ->whereHas('sections', fn ($q) => $q->whereNotNull('review_date')->whereDate('review_date', '<', now()))
            ->count();

        $kpi = fn (string $indicator, string|int|float $value, string $measure) => compact('indicator', 'value', 'measure');

        return [
            'title' => 'Service Quality Indicators',
            'columns' => [
                ['key' => 'indicator', 'label' => 'Indicator'],
                ['key' => 'value', 'label' => 'Result'],
                ['key' => 'measure', 'label' => 'How It\'s Measured'],
            ],
            'rows' => [
                $kpi('Visits completed', $this->percent($completed->count(), $past->count()), 'Completed visits out of visits due so far'),
                $kpi('Visits on time', $this->percent($onTime->count(), $completed->count()), "Checked in within {$grace} minutes of the start time"),
                $kpi('Missed visits', $visits->where('status', 'missed')->count(), 'Visits marked missed'),
                $kpi('Medication given as scheduled', $this->percent($doses->where('status', 'administered')->count(), $doses->count()), 'Given doses out of all scheduled doses recorded'),
                $kpi('Incidents per 100 visits', $completed->count() ? round($incidents / $completed->count() * 100, 1) : '—', 'Incidents reported against completed visits'),
                $kpi('Complaints received', $complaints->count(), 'Complaints received in the period'),
                $kpi('Complaints resolved on time', $this->percent(
                    $complaints->filter(fn (Complaint $c) => $c->resolved_date && (! $c->response_due_date || $c->resolved_date->lte($c->response_due_date)))->count(),
                    $complaints->whereNotNull('resolved_date')->count(),
                ), 'Resolved by their response due date'),
                $kpi('Complaints overdue now', $openComplaints->filter(fn (Complaint $c) => $c->isOverdue())->count(), 'Open complaints past their response date'),
                $kpi('Spot checks passed', $this->percent($spotChecks->where('outcome', 'pass')->count(), $spotChecks->count()), 'Spot checks with no failed areas'),
                $kpi('Care plans with overdue reviews', $plansOverdue.' of '.$activeClients.' clients', 'Active plans with a section past its review date'),
            ],
        ];
    }

    // ---- Finance --------------------------------------------------------------------

    private function billedInvoices()
    {
        return Invoice::whereIn('status', ['sent', 'paid', 'overdue'])
            ->whereBetween('issue_date', [$this->filters['from'], $this->filters['to']])
            ->when($this->branchId(), fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $this->branchId())));
    }

    public function fundingUtilization(): array
    {
        $invoices = $this->billedInvoices()->get()->groupBy('funder_id');
        $visits = $this->visitsInRange()->where('status', 'completed')->with('serviceUser')->get()
            ->groupBy(fn (Visit $v) => $v->serviceUser?->funder_id);

        $rows = Funder::orderBy('name')->get()->map(function (Funder $f) use ($invoices, $visits) {
            $inv = $invoices[$f->id] ?? collect();
            $hours = ($visits[$f->id] ?? collect())->sum(fn (Visit $v) => $this->deliveredHours($v));

            return [
                'funder' => $f->name,
                'type' => $this->label($f->type),
                'clients' => ServiceUser::where('funder_id', $f->id)->where('status', 'active')->count(),
                'hours' => round($hours, 2),
                'invoices' => $inv->count(),
                'billed' => round((float) $inv->sum('total'), 2),
                'paid' => round((float) $inv->where('status', 'paid')->sum('total'), 2),
                'outstanding' => round((float) $inv->whereIn('status', ['sent', 'overdue'])->sum('total'), 2),
            ];
        })->values();

        return [
            'title' => 'Funding Utilisation',
            'columns' => [
                ['key' => 'funder', 'label' => 'Funder'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'clients', 'label' => 'Active Clients'],
                ['key' => 'hours', 'label' => 'Care Hours Delivered'],
                ['key' => 'invoices', 'label' => 'Invoices'],
                ['key' => 'billed', 'label' => 'Billed'],
                ['key' => 'paid', 'label' => 'Paid'],
                ['key' => 'outstanding', 'label' => 'Outstanding'],
            ],
            'rows' => $rows,
        ];
    }

    /** Billed revenue against carer labour cost (delivered hours × hourly rate), per client. */
    public function profitMargin(): array
    {
        $revenue = $this->billedInvoices()->get()->groupBy('service_user_id')->map(fn ($g) => (float) $g->sum('total'));
        $rates = StaffProfile::pluck('hourly_rate', 'user_id');
        $visits = $this->visitsInRange()->where('status', 'completed')->with('serviceUser.funder')->get()->groupBy('service_user_id');

        $rows = $visits->keys()->merge($revenue->keys())->unique()->map(function ($clientId) use ($revenue, $rates, $visits) {
            $v = $visits[$clientId] ?? collect();
            $su = $v->first()?->serviceUser ?? ServiceUser::with('funder')->find($clientId);
            $hours = $v->sum(fn (Visit $visit) => $this->deliveredHours($visit));
            $cost = $v->sum(fn (Visit $visit) => $this->deliveredHours($visit) * (float) ($rates[$visit->carer_id] ?? 0));
            $income = (float) ($revenue[$clientId] ?? 0);

            return [
                'client' => $this->clientName($su),
                'service' => $su?->funder->name ?? ($su?->funding_source ?? '—'),
                'hours' => round($hours, 2),
                'revenue' => round($income, 2),
                'labour_cost' => round($cost, 2),
                'margin' => round($income - $cost, 2),
                'margin_pct' => $this->percent($income - $cost, $income),
            ];
        })->sortBy('margin')->values();

        return [
            'title' => 'Profit / Margin by Client and Funder',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'service', 'label' => 'Funder / Service'],
                ['key' => 'hours', 'label' => 'Hours Delivered'],
                ['key' => 'revenue', 'label' => 'Revenue Billed'],
                ['key' => 'labour_cost', 'label' => 'Carer Labour Cost'],
                ['key' => 'margin', 'label' => 'Margin'],
                ['key' => 'margin_pct', 'label' => 'Margin %'],
            ],
            'rows' => $rows,
        ];
    }
}
