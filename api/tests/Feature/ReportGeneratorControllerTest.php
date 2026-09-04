<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\Observations\Models\Observation;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportGeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeReportViewer(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Care Manager', 'tenant_id' => $tenant->id])->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    public function test_a_plain_carer_cannot_generate_reports(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)->getJson('/api/v1/reports/generate?key=visit_history')->assertForbidden();
    }

    public function test_an_unknown_report_key_returns_a_validation_error(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);

        $this->actingAs($manager)
            ->getJson('/api/v1/reports/generate?key=not_a_real_report')
            ->assertStatus(422);
    }

    public function test_a_key_is_required(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);

        $this->actingAs($manager)->getJson('/api/v1/reports/generate')->assertStatus(422);
    }

    public function test_visit_history_lists_visits_in_range_with_client_and_carer_names(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Farai Ncube']);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'carer_id' => $carer->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-07-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=visit_history&from=2026-06-01&to=2026-06-30');

        $response->assertOk()
            ->assertJsonPath('title', 'Visit History')
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.client', 'Ruth Chikafu')
            ->assertJsonPath('rows.0.carer', 'Farai Ncube')
            ->assertJsonPath('rows.0.status', 'completed');
    }

    public function test_missed_visits_only_returns_missed_status(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'missed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-16', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=missed_visits&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.status', 'missed');
    }

    public function test_late_visits_flags_a_checkin_more_than_ten_minutes_after_scheduled_start(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        // 20 minutes late.
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed', 'check_in_at' => '2026-06-15 09:20:00']);
        // On time.
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-16', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed', 'check_in_at' => '2026-06-16 09:02:00']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=late_visits&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.minutes_late', 20);
    }

    public function test_care_hours_delivered_sums_completed_visit_durations_by_day(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:30', 'status' => 'completed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '11:00', 'end_time' => '12:00', 'status' => 'completed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '13:00', 'end_time' => '14:00', 'status' => 'missed']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=care_hours_delivered&from=2026-06-01&to=2026-06-30');

        $response->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.date', '2026-06-15')
            ->assertJsonPath('rows.0.visits', 2)
            ->assertJsonPath('rows.0.hours', 2.5);
    }

    public function test_emar_administration_report_lists_medication_administrations(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $medication = Medication::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'name' => 'Paracetamol', 'dose' => '500mg', 'route' => 'Oral', 'frequency' => 'Once daily', 'start_date' => '2026-01-01', 'status' => 'active']);

        MedicationAdministration::create(['tenant_id' => $tenant->id, 'medication_id' => $medication->id, 'status' => 'administered', 'administered_at' => '2026-06-15 08:00:00']);
        MedicationAdministration::create(['tenant_id' => $tenant->id, 'medication_id' => $medication->id, 'status' => 'refused', 'administered_at' => '2026-06-16 08:00:00']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=refused_medication&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.status', 'refused')->assertJsonPath('rows.0.medication', 'Paracetamol');
    }

    public function test_blood_pressure_trend_extracts_systolic_and_diastolic_from_json_value(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Observation::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'blood_pressure', 'value' => ['systolic' => 130, 'diastolic' => 85], 'recorded_at' => '2026-06-15 08:00:00']);
        Observation::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'blood_glucose', 'value' => ['value' => 5.5], 'recorded_at' => '2026-06-15 08:00:00']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=bp_trends&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.value', '130/85');
    }

    public function test_abnormal_observation_alerts_lists_clinical_alerts(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $observation = Observation::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'oxygen_saturation', 'value' => ['value' => 88], 'recorded_at' => '2026-06-15 08:00:00']);
        $alert = ClinicalAlert::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'observation_id' => $observation->id, 'message' => 'Low SpO2', 'severity' => 'critical']);
        $alert->created_at = '2026-06-15 08:00:00';
        $alert->save();

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=abnormal_observation_alerts&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.severity', 'critical')->assertJsonPath('rows.0.acknowledged', 'No');
    }

    public function test_falls_report_filters_incidents_by_type(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $fall = Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'fall', 'severity' => 'medium', 'description' => 'Slipped.', 'status' => 'reported', 'reported_by' => $manager->id]);
        $fall->created_at = '2026-06-15 10:00:00';
        $fall->save();
        $other = Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'injury', 'severity' => 'low', 'description' => 'Bruise.', 'status' => 'reported', 'reported_by' => $manager->id]);
        $other->created_at = '2026-06-15 11:00:00';
        $other->save();

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=falls&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.type', 'fall');
    }

    public function test_safeguarding_concerns_lists_cases_in_range(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $case = SafeguardingCase::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'concern_type' => 'neglect', 'status' => 'reported', 'immediate_risk' => true]);
        $case->created_at = '2026-06-15 10:00:00';
        $case->save();

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=safeguarding_concerns&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.immediate_risk', 'Yes');
    }

    public function test_leave_report_lists_leave_requests_in_range(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Chipo Dube']);
        LeaveRequest::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'type' => 'sick', 'start_date' => '2026-06-10', 'end_date' => '2026-06-12', 'status' => 'approved']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=leave_report&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.staff', 'Chipo Dube')->assertJsonPath('rows.0.type', 'sick');
    }

    public function test_shift_coverage_lists_shifts_and_can_be_scoped_by_branch(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch B', 'country' => 'Zimbabwe']);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        Shift::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'branch_id' => $branchA->id, 'shift_date' => '2026-06-15', 'start_time' => '08:00', 'end_time' => '16:00']);
        Shift::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'branch_id' => $branchB->id, 'shift_date' => '2026-06-15', 'start_time' => '08:00', 'end_time' => '16:00']);

        $response = $this->actingAs($manager)->getJson("/api/v1/reports/generate?key=shift_coverage&from=2026-06-01&to=2026-06-30&branch_id={$branchA->id}");

        $response->assertOk()->assertJsonCount(1, 'rows');
    }

    public function test_invoices_report_lists_invoices_in_range(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-15', 'status' => 'sent', 'total' => 250.5, 'currency' => 'USD']);
        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-20', 'status' => 'paid', 'total' => 100, 'currency' => 'USD']);

        $outstanding = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=outstanding_balances&from=2026-06-01&to=2026-06-30');
        $outstanding->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.status', 'sent');

        $paid = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=payments&from=2026-06-01&to=2026-06-30');
        $paid->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.status', 'paid');
    }

    public function test_revenue_breakdown_excludes_draft_and_cancelled_invoices(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-15', 'status' => 'draft', 'total' => 500]);
        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-16', 'status' => 'cancelled', 'total' => 300]);
        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-17', 'status' => 'sent', 'total' => 100]);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=revenue_breakdown&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.revenue', '100.00');
    }

    public function test_verified_checkins_and_manual_overrides_split_correctly(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu',
            'latitude' => -17.8292, 'longitude' => 31.0522,
        ]);

        // Verified — checked in right at the client's location.
        Visit::create([
            'tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed',
            'check_in_at' => '2026-06-15 09:00:00', 'check_in_lat' => -17.8292, 'check_in_lng' => 31.0522,
        ]);
        // Manual override.
        Visit::create([
            'tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-06-16', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed',
            'check_in_at' => '2026-06-16 09:00:00', 'check_in_lat' => -17.9, 'check_in_lng' => 31.1,
            'override_reason' => 'GPS signal was weak indoors',
        ]);

        $verified = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=verified_checkins&from=2026-06-01&to=2026-06-30');
        $verified->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.override_reason', '—');

        $overrides = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=manual_overrides&from=2026-06-01&to=2026-06-30');
        $overrides->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.override_reason', 'GPS signal was weak indoors');
    }

    public function test_care_plan_reviews_overdue_lists_sections_past_their_review_date(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $carePlan = CarePlan::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'version' => 1, 'status' => 'active', 'effective_from' => '2026-01-01']);
        CarePlanSection::create(['tenant_id' => $tenant->id, 'care_plan_id' => $carePlan->id, 'area' => 'personal_care', 'identified_need' => 'x', 'goal' => 'y', 'intervention' => 'z', 'review_date' => now()->subDays(5)->toDateString(), 'status' => 'ongoing']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=care_plan_reviews_overdue');

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.client', 'Ruth Chikafu');
    }

    public function test_branch_filter_scopes_visit_history(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch B', 'country' => 'Zimbabwe']);
        $serviceUserA = ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $serviceUserB = ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'first_name' => 'Josiah', 'last_name' => 'Ndlovu']);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUserA->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUserB->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);

        $response = $this->actingAs($manager)->getJson("/api/v1/reports/generate?key=visit_history&from=2026-06-01&to=2026-06-30&branch_id={$branchA->id}");

        $response->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.client', 'Ruth Chikafu');
    }

    public function test_tenant_user_cannot_see_another_tenants_data(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $managerA = $this->makeReportViewer($tenantA);

        $serviceUserB = ServiceUser::create(['tenant_id' => $tenantB->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
        Visit::create(['tenant_id' => $tenantB->id, 'service_user_id' => $serviceUserB->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);

        $response = $this->actingAs($managerA)->getJson('/api/v1/reports/generate?key=visit_history&from=2026-06-01&to=2026-06-30');

        $response->assertOk()->assertJsonCount(0, 'rows');
    }

    public function test_branch_performance_summarises_each_branch(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeReportViewer($tenant);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'status' => 'active']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => '2026-06-15', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'missed']);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=branch_performance&from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $row = collect($response->json('rows'))->firstWhere('branch', 'Branch A');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['active_clients']);
        $this->assertSame(1, $row['missed_visits']);
    }
}
