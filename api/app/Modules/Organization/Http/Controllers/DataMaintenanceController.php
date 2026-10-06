<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\Observation;
use App\Modules\Quality\Models\Complaint;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use App\Support\Time\TenantClock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * System Settings → Data Maintenance: checks that find records with
 * missing or stale data (each linking to where it's fixed), and CSV
 * exports of the organisation's core records.
 */
class DataMaintenanceController extends Controller
{
    private const SAMPLE_SIZE = 10;

    public function checks(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AdministrationRoles::ALLOWED), 403);

        $tenantId = $request->user()->tenant_id;
        $today = TenantClock::today($tenantId);
        $client = fn (ServiceUser $su) => ['label' => trim("{$su->first_name} {$su->last_name}"), 'link' => "/service-users/{$su->id}"];
        $staff = fn (StaffProfile $sp) => ['label' => $sp->user->name ?? "Staff #{$sp->id}", 'link' => '/staff'];
        $activeClients = fn () => ServiceUser::where('status', 'active');
        $activeStaff = fn () => StaffProfile::where('employment_status', '!=', 'inactive')->with('user');

        $checks = [
            $this->check('clients_missing_dob', 'Clients without a date of birth', 'Needed on care plans, risk assessments and NHS records.', 'warning',
                $activeClients()->whereNull('date_of_birth')->get(), $client),
            $this->check('clients_missing_nhs', 'Clients without an NHS number', 'Shown on risk assessments and printed care plans.', 'info',
                $activeClients()->whereNull('nhs_number')->get(), $client),
            $this->check('clients_missing_location', 'Clients without a map location', 'Visit check-ins can\'t be GPS-verified and mileage can\'t be estimated without one.', 'warning',
                $activeClients()->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->get(), $client),
            $this->check('clients_without_plan', 'Clients without an active care plan', 'Every client receiving care should have one.', 'danger',
                $activeClients()->whereDoesntHave('carePlans', fn ($q) => $q->where('status', 'active'))->get(), $client),
            $this->check('clients_without_manager', 'Clients without a care manager', 'Set on the client\'s Care Team card.', 'warning',
                $activeClients()->whereNull('care_manager_id')->get(), $client),
            $this->check('clients_without_carers', 'Clients without a care team', 'No carers are linked to them on the Care Team card.', 'info',
                $activeClients()->whereDoesntHave('carers')->get(), $client),
            $this->check('plans_without_home_care_plan', 'Care plans with no Home Care Plan', 'About Me, needs and consent haven\'t been recorded.', 'info',
                CarePlan::where('status', 'active')->whereNull('home_care_plan')->with('serviceUser')->get(),
                fn (CarePlan $p) => $p->serviceUser ? $client($p->serviceUser) : ['label' => "Plan #{$p->id}", 'link' => null]),
            $this->check('meds_without_times', 'Regular medications without dose times', 'They won\'t appear on the daily medication round or be checked for missed doses.', 'warning',
                Medication::where('status', 'active')->whereNull('archived_at')->where('is_prn', false)
                    ->where(fn ($q) => $q->whereNull('schedule')->orWhereJsonLength('schedule', 0))->with('serviceUser')->get(),
                fn (Medication $m) => ['label' => "{$m->name} — ".trim("{$m->serviceUser?->first_name} {$m->serviceUser?->last_name}"), 'link' => "/service-users/{$m->service_user_id}"]),
            $this->check('staff_without_branch', 'Staff without a location', 'Location filters on reports and rotas won\'t include them.', 'warning',
                $activeStaff()->whereNull('branch_id')->get(), $staff),
            $this->check('staff_without_rate', 'Staff without an hourly rate', 'Payroll, overtime cost and margin reports need it.', 'warning',
                $activeStaff()->whereNull('hourly_rate')->get(), $staff),
            $this->check('stale_visits', 'Past visits never checked in or out', 'Still "scheduled" after the day has passed — mark them completed, missed or cancelled.', 'danger',
                Visit::where('status', 'scheduled')->where('visit_date', '<', $today)->with('serviceUser')->orderByDesc('visit_date')->get(),
                fn (Visit $v) => ['label' => $v->visit_date->toDateString().' — '.trim("{$v->serviceUser?->first_name} {$v->serviceUser?->last_name}"), 'link' => "/visits/{$v->id}"]),
            $this->check('open_duty_periods', 'Staff clocked in for over 24 hours', 'Probably a forgotten clock-out — close it from the Live Map.', 'warning',
                DutyPeriod::whereNull('ended_at')->where('started_at', '<', now()->subDay())->with('carer')->get(),
                fn (DutyPeriod $d) => ['label' => ($d->carer->name ?? 'Staff').' — since '.TenantClock::local($tenantId, $d->started_at)->format('Y-m-d H:i'), 'link' => '/live-map']),
            $this->check('inactive_branch_records', 'Clients or staff at an inactive location', 'Their location has been deactivated — move them to an active one.', 'warning',
                $activeClients()->whereHas('branch', fn ($q) => $q->where('status', 'inactive'))->get()
                    ->map($client)
                    ->concat($activeStaff()->whereHas('branch', fn ($q) => $q->where('status', 'inactive'))->get()->map($staff)),
                null),
        ];

        return response()->json(['data' => $checks]);
    }

    /** @param Collection $records models, or already-mapped {label, link} items when $describe is null */
    private function check(string $key, string $label, string $description, string $severity, Collection $records, ?callable $describe): array
    {
        $items = $describe ? $records->map($describe) : $records;

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'severity' => $severity,
            'count' => $items->count(),
            'sample' => $items->take(self::SAMPLE_SIZE)->values(),
        ];
    }

    /** The datasets that can be exported, and the columns each one includes. */
    private function datasets(?int $tenantId): array
    {
        $name = fn ($su) => $su ? trim("{$su->first_name} {$su->last_name}") : '';
        $local = fn ($at) => $at ? TenantClock::local($tenantId, $at)->format('Y-m-d H:i') : '';

        return [
            'service_users' => [ServiceUser::query()->with(['branch', 'careManager', 'funder']), fn (ServiceUser $su) => [
                'ID' => $su->id, 'First name' => $su->first_name, 'Last name' => $su->last_name, 'Preferred name' => $su->preferred_name,
                'Date of birth' => $su->date_of_birth?->toDateString(), 'NHS number' => $su->nhs_number, 'Status' => $su->status,
                'Location' => $su->branch?->name, 'Care manager' => $su->careManager?->name, 'Funder' => $su->funder?->name ?? $su->funding_source,
                'Phone' => $su->phone, 'Address' => $su->address, 'Allergies' => implode('; ', $su->allergies ?? []),
                'Diagnoses' => implode('; ', $su->diagnoses ?? []), 'Created' => $local($su->created_at),
            ]],
            'staff' => [StaffProfile::query()->with(['user', 'branch']), fn (StaffProfile $sp) => [
                'ID' => $sp->id, 'Name' => $sp->user?->name, 'Email' => $sp->user?->email, 'Job title' => $sp->job_title,
                'Location' => $sp->branch?->name, 'Employee number' => $sp->employee_number, 'Started' => $sp->employment_start_date?->toDateString(),
                'Status' => $sp->employment_status, 'Hourly rate' => $sp->hourly_rate, 'Skills' => implode('; ', $sp->skills ?? []),
            ]],
            'visits' => [Visit::query()->with(['serviceUser', 'carer']), fn (Visit $v) => [
                'ID' => $v->id, 'Date' => $v->visit_date->toDateString(), 'Start' => substr($v->start_time, 0, 5), 'End' => substr($v->end_time, 0, 5),
                'Client' => $name($v->serviceUser), 'Carer' => $v->carer?->name, 'Status' => $v->status,
                'Checked in' => $local($v->check_in_at), 'Checked out' => $local($v->check_out_at),
                'Care tasks' => implode('; ', $v->care_tasks ?? []), 'Completed tasks' => implode('; ', $v->completed_care_tasks ?? []),
            ]],
            'medications' => [Medication::query()->with('serviceUser'), fn (Medication $m) => [
                'ID' => $m->id, 'Client' => $name($m->serviceUser), 'Medication' => $m->name, 'Strength' => $m->strength, 'Dose' => $m->dose,
                'Route' => $m->route, 'Frequency' => $m->frequency, 'Dose times' => implode('; ', $m->schedule ?? []), 'PRN' => $m->is_prn ? 'Yes' : 'No',
                'Controlled drug' => $m->is_controlled_drug ? 'Yes' : 'No', 'Stock on hand' => $m->stock_on_hand, 'Status' => $m->status,
                'Start' => $m->start_date?->toDateString(), 'End' => $m->end_date?->toDateString(),
            ]],
            'medication_administrations' => [MedicationAdministration::query()->with(['medication.serviceUser', 'administeredBy']), fn (MedicationAdministration $a) => [
                'ID' => $a->id, 'When' => $local($a->administered_at), 'Client' => $name($a->medication?->serviceUser), 'Medication' => $a->medication?->name,
                'Dose due' => $a->scheduled_time, 'Status' => $a->status, 'Reason not given' => $a->not_given_reason,
                'Stock checked' => $a->stock_checked === null ? '' : ($a->stock_checked ? 'Yes' : 'No'), 'Recorded by' => $a->administeredBy?->name, 'Notes' => $a->notes,
            ]],
            'observations' => [Observation::query()->with(['serviceUser', 'recordedBy']), fn (Observation $o) => [
                'ID' => $o->id, 'When' => $local($o->recorded_at), 'Client' => $name($o->serviceUser), 'Type' => $o->type,
                'Value' => json_encode($o->value), 'Unit' => $o->unit, 'Recorded by' => $o->recordedBy?->name, 'Notes' => $o->notes,
            ]],
            'incidents' => [Incident::query()->with(['serviceUser', 'reportedBy', 'assignedTo']), fn (Incident $i) => [
                'ID' => $i->id, 'Reported' => $local($i->created_at), 'Client' => $name($i->serviceUser), 'Type' => $i->type, 'Severity' => $i->severity,
                'Status' => $i->status, 'Description' => $i->description, 'Reported by' => $i->reportedBy?->name, 'Assigned to' => $i->assignedTo?->name,
            ]],
            'complaints' => [Complaint::query()->with(['serviceUser', 'assignedTo']), fn (Complaint $c) => [
                'ID' => $c->id, 'Received' => $c->received_date->toDateString(), 'Complainant' => $c->complainant_name, 'Client' => $name($c->serviceUser),
                'Category' => $c->category, 'Severity' => $c->severity, 'Status' => $c->status, 'Response due' => $c->response_due_date?->toDateString(),
                'Resolved' => $c->resolved_date?->toDateString(), 'Outcome' => $c->outcome, 'Investigating' => $c->assignedTo?->name, 'Description' => $c->description,
            ]],
        ];
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        abort_unless($request->user()->hasAnyRole(AdministrationRoles::ALLOWED), 403);

        $tenantId = $request->user()->tenant_id;
        $datasets = $this->datasets($tenantId);
        abort_unless(isset($datasets[$dataset]), 404, 'Unknown dataset.');
        [$query, $row] = $datasets[$dataset];

        $filename = $dataset.'-'.TenantClock::today($tenantId).'.csv';

        return response()->streamDownload(function () use ($query, $row) {
            $out = fopen('php://output', 'w');
            // A BOM so Excel reads the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            $headerWritten = false;
            $query->chunkById(500, function ($records) use ($out, $row, &$headerWritten) {
                foreach ($records as $record) {
                    $values = $row($record);
                    if (! $headerWritten) {
                        fputcsv($out, array_keys($values));
                        $headerWritten = true;
                    }
                    fputcsv($out, array_map(fn ($v) => self::safeCell($v), array_values($values)));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops a cell that starts with = + - @ being run as a formula when the
     * export is opened in Excel (CSV injection).
     */
    private static function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }

    /** Lists the datasets that can be exported, for the settings page. */
    public function exportList(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AdministrationRoles::ALLOWED), 403);

        return response()->json(['data' => array_keys($this->datasets($request->user()->tenant_id))]);
    }
}
