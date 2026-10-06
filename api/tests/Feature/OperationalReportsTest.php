<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Billing\Models\Funder;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CareNotes\Models\CareNote;
use App\Modules\Documents\Models\Document;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Rostering\Models\Shift;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Tracking\Models\CarerLocation;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'UK', 'currency' => 'GBP']);
        $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->manager->assignRole(Role::where(['name' => 'Care Manager', 'tenant_id' => $this->tenant->id])->firstOrFail());
    }

    protected function staff(string $name, array $profile = []): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
        StaffProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'employment_status' => 'active', ...$profile]);

        return $user;
    }

    protected function client(string $first = 'Ruth', array $attributes = []): ServiceUser
    {
        return ServiceUser::create(['tenant_id' => $this->tenant->id, 'first_name' => $first, 'last_name' => 'Chikafu', ...$attributes]);
    }

    protected function visit(ServiceUser $client, ?User $carer, string $date, string $start, string $end, string $status = 'completed', array $extra = []): Visit
    {
        return Visit::create([
            'tenant_id' => $this->tenant->id, 'service_user_id' => $client->id, 'carer_id' => $carer?->id,
            'visit_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => $status, ...$extra,
        ]);
    }

    protected function report(string $key, string $from = '2026-06-01', string $to = '2026-06-30'): array
    {
        return $this->actingAs($this->manager)->getJson("/api/v1/reports/generate?key={$key}&from={$from}&to={$to}")->assertOk()->json();
    }

    public function test_care_history_and_daily_notes(): void
    {
        $carer = $this->staff('Amy Moyo');
        $ruth = $this->client();
        $this->visit($ruth, $carer, '2026-06-10', '09:00', '10:00');
        $this->visit($ruth, $carer, '2026-06-11', '09:00', '10:30', 'missed');
        $note = CareNote::create(['tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'author_id' => $carer->id, 'caption' => 'Ate well, in good spirits.', 'audio_path' => 'notes/1.webm', 'duration_seconds' => 42]);
        $note->forceFill(['created_at' => '2026-06-10 10:05:00'])->save();

        $history = $this->report('care_history');
        $this->assertSame(['client' => 'Ruth Chikafu', 'visits' => 2, 'completed' => 1, 'missed' => 1, 'hours' => 1, 'notes' => 1, 'observations' => 0, 'doses' => 0, 'incidents' => 0], $history['rows'][0]);

        $notes = $this->report('daily_notes');
        $this->assertSame('Ate well, in good spirits.', $notes['rows'][0]['note']);
        $this->assertSame('Amy Moyo', $notes['rows'][0]['author']);
        $this->assertSame('00:42', $notes['rows'][0]['audio']);
    }

    public function test_admissions_and_discharges_come_from_creation_and_the_audit_log(): void
    {
        // Audit rows are timestamped by the database clock, so this runs on today's date.
        $today = now()->toDateString();
        $ruth = $this->client('Ruth', ['referring_hospital' => 'Parirenyatwa']);
        $this->client('Peter');
        $this->actingAs($this->manager)->patchJson("/api/v1/service-users/{$ruth->id}", ['status' => 'discharged'])->assertOk();

        $rows = collect($this->report('admission_discharge_history', $today, $today)['rows']);
        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows->where('event', 'Discharged')->count());
        $this->assertSame('Ruth Chikafu', $rows->firstWhere('event', 'Discharged')['client']);
        $this->assertSame('From Parirenyatwa', $rows->firstWhere(fn ($r) => $r['event'] === 'Admitted' && $r['client'] === 'Ruth Chikafu')['details']);

        $this->assertCount(2, $this->report('new_admissions', $today, $today)['rows']);
    }

    public function test_care_tasks_and_package_utilisation(): void
    {
        $carer = $this->staff('Amy Moyo');
        $ruth = $this->client();
        $this->visit($ruth, $carer, '2026-06-10', '09:00', '10:00', 'completed', [
            'care_tasks' => ['Personal care', 'Breakfast', 'Medication prompt'],
            'completed_care_tasks' => ['Personal care', 'Breakfast'],
            'check_in_at' => '2026-06-10 09:00:00', 'check_out_at' => '2026-06-10 09:30:00',
        ]);
        $this->visit($ruth, $carer, '2026-06-11', '09:00', '10:00', 'missed');

        $tasks = $this->report('care_tasks')['rows'];
        $this->assertSame(1, count(array_filter($tasks, fn ($r) => $r['planned'] > 0 && $r['date'] === '2026-06-10')));
        $this->assertSame('Medication prompt', $tasks[0]['not_done']);
        $this->assertSame('66.7%', $tasks[0]['rate']);

        $util = $this->report('care_package_utilization')['rows'][0];
        $this->assertSame([2, 2, 0.5, '25%'], [$util['visits'], $util['booked'], $util['delivered'], $util['utilisation']]);
    }

    public function test_staff_attendance_marks_attended_late_absent_and_on_leave(): void
    {
        $this->travelTo(now()->parse('2026-06-30 12:00:00'));
        $amy = $this->staff('Amy Moyo');
        $shift = fn (string $date) => Shift::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'shift_date' => $date, 'start_time' => '08:00', 'end_time' => '16:00', 'shift_type' => 'day', 'status' => 'scheduled']);
        $shift('2026-06-10');
        $shift('2026-06-11');
        $shift('2026-06-12');
        $shift('2026-06-13');
        DutyPeriod::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'start_lat' => 51.5, 'start_lng' => -0.12, 'started_at' => '2026-06-10 07:55:00', 'ended_at' => '2026-06-10 16:05:00']);
        DutyPeriod::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'start_lat' => 51.5, 'start_lng' => -0.12, 'started_at' => '2026-06-11 08:40:00', 'ended_at' => '2026-06-11 16:00:00']);
        LeaveRequest::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'type' => 'annual', 'start_date' => '2026-06-13', 'end_date' => '2026-06-13', 'status' => 'approved']);

        $statuses = collect($this->report('staff_attendance')['rows'])->pluck('status', 'date');
        $this->assertSame(['2026-06-10' => 'Attended', '2026-06-11' => 'Late (40 min)', '2026-06-12' => 'Absent', '2026-06-13' => 'On leave'], $statuses->all());

        $clock = $this->report('clock_in_out')['rows'];
        $this->assertCount(2, $clock);
        $this->assertSame(8.17, $clock[1]['hours']);
    }

    public function test_overtime_overtime_risk_and_sickness(): void
    {
        $amy = $this->staff('Amy Moyo', ['hourly_rate' => 12]);
        $ben = $this->staff('Ben Dube');
        // Amy: five 9.5-hour clock-ins in one week = 47.5h, 7.5h over 40.
        foreach (['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11', '2026-06-12'] as $d) {
            DutyPeriod::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'start_lat' => 51.5, 'start_lng' => -0.12, 'started_at' => "{$d} 07:00:00", 'ended_at' => "{$d} 16:30:00"]);
        }
        // Ben: 37 booked visit hours next week — within 10% of the limit.
        $ruth = $this->client();
        foreach (['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18'] as $d) {
            $this->visit($ruth, $ben, $d, '08:00', '17:15', 'scheduled');
        }
        LeaveRequest::create(['tenant_id' => $this->tenant->id, 'user_id' => $ben->id, 'type' => 'sick', 'start_date' => '2026-06-01', 'end_date' => '2026-06-02', 'status' => 'approved', 'reason' => 'Flu']);
        LeaveRequest::create(['tenant_id' => $this->tenant->id, 'user_id' => $ben->id, 'type' => 'sick', 'start_date' => '2026-06-22', 'end_date' => '2026-06-22', 'status' => 'approved']);

        $overtime = $this->report('overtime')['rows'];
        $this->assertCount(1, $overtime);
        $this->assertSame(['Amy Moyo', 47.5, 7.5, 90], [$overtime[0]['staff'], $overtime[0]['hours'], $overtime[0]['overtime'], $overtime[0]['cost']]);

        $risk = $this->report('overtime_risk')['rows'];
        $this->assertSame('Ben Dube', $risk[0]['staff']);
        $this->assertSame('Within 3h of limit', $risk[0]['risk']);

        $sick = $this->report('sickness')['rows'][0];
        $this->assertSame(['Ben Dube', 2, 3, 12], [$sick['staff'], $sick['spells'], $sick['days'], $sick['bradford']]);
    }

    public function test_unfilled_visits_staffing_gaps_and_shortages(): void
    {
        $amy = $this->staff('Amy Moyo');
        $ruth = $this->client();
        Shift::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'shift_date' => '2026-06-10', 'start_time' => '09:00', 'end_time' => '11:00', 'shift_type' => 'day', 'status' => 'scheduled']);
        $this->visit($ruth, $amy, '2026-06-10', '09:00', '10:00', 'scheduled');
        $this->visit($ruth, null, '2026-06-10', '12:00', '14:00', 'scheduled');

        $unfilled = $this->report('unfilled_shifts')['rows'];
        $this->assertSame(['2026-06-10', '12:00–14:00', 2], [$unfilled[0]['date'], $unfilled[0]['time'], $unfilled[0]['hours']]);

        $gaps = $this->report('staffing_gaps')['rows'];
        $this->assertEquals([['date' => '2026-06-10', 'day' => 'Wed', 'unassigned' => 1, 'unassigned_hours' => 2, 'on_leave' => 0]], array_map(
            fn ($r) => array_intersect_key($r, array_flip(['date', 'day', 'unassigned', 'unassigned_hours', 'on_leave'])), $gaps
        ));

        $short = $this->report('staff_shortages')['rows'][0];
        $this->assertSame([3, 2, 1], [$short['demand_hours'], $short['supply_hours'], $short['shortfall']]);

        $available = collect($this->report('assigned_vs_available')['rows'])->firstWhere('date', '2026-06-10');
        $this->assertSame([1, 1, 2, 1], [$available['rostered'], $available['assigned'], $available['visits'], $available['unassigned']]);

        $util = $this->report('staff_utilization')['rows'][0];
        $this->assertSame(['Amy Moyo', 2, 1, '50%'], [$util['staff'], $util['rostered'], $util['booked'], $util['utilisation']]);
    }

    public function test_mileage_uses_gps_and_drops_glitches(): void
    {
        $amy = $this->staff('Amy Moyo');
        $point = fn (string $at, float $lat, float $lng, ?int $accuracy = 10) => CarerLocation::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'latitude' => $lat, 'longitude' => $lng, 'accuracy' => $accuracy, 'recorded_at' => $at,
        ]);
        // ~1.11 km north per 0.01° latitude.
        $point('2026-06-10 09:00:00', 51.50, -0.12);
        $point('2026-06-10 09:10:00', 51.51, -0.12);
        $point('2026-06-10 09:10:05', 52.51, -0.12);   // 111 km in 5 s: a glitch, dropped
        $point('2026-06-10 09:20:00', 51.52, -0.12);
        $point('2026-06-10 09:25:00', 51.90, -0.12, 900); // too inaccurate, ignored

        $day = $this->report('travel_distance')['rows'][0];
        $this->assertSame('GPS', $day['source']);
        $this->assertEqualsWithDelta(1.1, $day['km'], 0.05);

        $pay = $this->report('mileage_reimbursement')['rows'][0];
        $this->assertEqualsWithDelta(0.7, $pay['miles'], 0.05);
        $this->assertEqualsWithDelta(0.31, $pay['reimbursement'], 0.02);
    }

    public function test_client_carer_allocation_continuity(): void
    {
        $amy = $this->staff('Amy Moyo');
        $ben = $this->staff('Ben Dube');
        $ruth = $this->client();
        $ruth->carers()->attach($amy->id, ['tenant_id' => $this->tenant->id]);
        $this->visit($ruth, $amy, '2026-06-10', '09:00', '10:00');
        $this->visit($ruth, $amy, '2026-06-11', '09:00', '10:00');
        $this->visit($ruth, $ben, '2026-06-12', '09:00', '10:00');

        $row = $this->report('client_carer_allocation')['rows'][0];
        $this->assertSame(['Amy Moyo', 3, '66.7%'], [$row['care_team'], $row['visits'], $row['continuity']]);
        $this->assertStringContainsString('Amy Moyo (2)', $row['carers_seen']);
    }

    public function test_background_checks_by_document_category(): void
    {
        $amy = $this->staff('Amy Moyo');
        $ben = $this->staff('Ben Dube');
        $profile = StaffProfile::where('user_id', $amy->id)->first();
        Document::create([
            'tenant_id' => $this->tenant->id, 'documentable_type' => 'staff_profile', 'documentable_id' => $profile->id,
            'category' => 'DBS', 'original_filename' => 'enhanced-dbs.pdf', 'path' => 'x', 'mime_type' => 'application/pdf', 'size' => 1,
            'version' => 1, 'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $rows = collect($this->report('background_checks')['rows'])->keyBy('staff');
        $this->assertSame('No check on file', $rows['Ben Dube']['status']);
        $this->assertSame('Expired', $rows['Amy Moyo']['status']);
        $this->assertSame('enhanced-dbs.pdf', $rows['Amy Moyo']['document']);
    }

    public function test_funding_utilisation_and_margin(): void
    {
        $amy = $this->staff('Amy Moyo', ['hourly_rate' => 12]);
        $funder = Funder::create(['tenant_id' => $this->tenant->id, 'name' => 'Harare City Council', 'type' => 'local_authority']);
        $ruth = $this->client('Ruth', ['funder_id' => $funder->id, 'status' => 'active']);
        $this->visit($ruth, $amy, '2026-06-10', '09:00', '11:00');
        Invoice::create(['tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'funder_id' => $funder->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-15', 'status' => 'paid', 'total' => 60, 'currency' => 'GBP']);
        Invoice::create(['tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'funder_id' => $funder->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'issue_date' => '2026-06-16', 'status' => 'draft', 'total' => 999, 'currency' => 'GBP']);

        $funding = $this->report('funding_utilization')['rows'][0];
        $this->assertSame(['Harare City Council', 1, 2, 1, 60, 60, 0], [
            $funding['funder'], $funding['clients'], $funding['hours'], $funding['invoices'], $funding['billed'], $funding['paid'], $funding['outstanding'],
        ]);

        $margin = $this->report('profit_margin')['rows'][0];
        $this->assertSame([60, 24, 36, '60%'], [$margin['revenue'], $margin['labour_cost'], $margin['margin'], $margin['margin_pct']]);
    }

    public function test_family_contact_activity(): void
    {
        $ruth = $this->client();
        $familyUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'last_login_at' => '2026-06-18 19:00:00']);
        $ruth->contacts()->create(['tenant_id' => $this->tenant->id, 'type' => 'next_of_kin', 'name' => 'Tendai Chikafu', 'relationship' => 'Son', 'user_id' => $familyUser->id]);
        $ruth->contacts()->create(['tenant_id' => $this->tenant->id, 'type' => 'gp', 'name' => 'Dr Moyo']);

        $rows = collect($this->report('family_contact_activity')['rows'])->keyBy('contact');
        $this->assertSame(['Yes', '2026-06-18 19:00'], [$rows['Tendai Chikafu']['portal'], $rows['Tendai Chikafu']['last_login']]);
        $this->assertSame(['No', '—'], [$rows['Dr Moyo']['portal'], $rows['Dr Moyo']['last_login']]);
    }

    public function test_every_new_report_runs_on_an_empty_tenant(): void
    {
        foreach ([
            'care_history', 'review_history', 'daily_notes', 'family_contact_activity', 'admission_discharge_history', 'new_admissions',
            'care_tasks', 'care_package_utilization', 'staff_attendance', 'clock_in_out', 'overtime', 'sickness', 'unfilled_shifts',
            'staff_utilization', 'mileage_travel_time', 'travel_distance', 'mileage', 'mileage_reimbursement', 'assigned_vs_available',
            'staffing_gaps', 'staff_shortages', 'overtime_risk', 'client_carer_allocation', 'background_checks', 'funding_utilization', 'profit_margin',
            'medication_stock', 'wound_progress',
        ] as $key) {
            $report = $this->report($key);
            $this->assertNotEmpty($report['columns'], $key);
        }
    }

    public function test_wounds_are_recorded_validated_and_tracked_per_site(): void
    {
        $ruth = $this->client();
        $record = fn (array $value, string $at) => $this->actingAs($this->manager)->postJson("/api/v1/service-users/{$ruth->id}/observations", [
            'type' => 'wound', 'value' => $value, 'recorded_at' => $at,
        ]);

        $record(['site' => 'Left heel', 'length_cm' => 4, 'width_cm' => 3, 'stage' => 'category_2', 'appearance' => 'sloughy', 'exudate' => 'moderate'], '2026-05-20 10:00:00')->assertCreated();
        $record(['site' => 'left heel ', 'length_cm' => 3, 'width_cm' => 2, 'stage' => 'category_2', 'appearance' => 'granulating', 'exudate' => 'low'], '2026-06-10 10:00:00')->assertCreated();
        $record(['site' => 'Sacrum', 'length_cm' => 2, 'width_cm' => 2], '2026-06-12 10:00:00')->assertCreated();
        $record(['length_cm' => 2], '2026-06-12 10:00:00')->assertUnprocessable()->assertJsonValidationErrors('value.site');
        $record(['site' => 'Hip', 'stage' => 'category_9'], '2026-06-12 10:00:00')->assertUnprocessable()->assertJsonValidationErrors('value.stage');

        $rows = collect($this->report('wound_progress')['rows']);
        // The May assessment is outside the period but still the baseline for June.
        $this->assertCount(2, $rows);
        $heel = $rows->firstWhere('date', '2026-06-10');
        $this->assertSame(['3 × 2 cm', 6, 'Smaller by 50%', 'Category 2', 'Granulating'], [$heel['size'], $heel['area'], $heel['change'], $heel['stage'], $heel['appearance']]);
        $this->assertSame('—', $rows->firstWhere('site', 'Sacrum')['change']);
    }

    public function test_medication_stock_report_lists_reorders_first(): void
    {
        $ruth = $this->client();
        $med = fn (string $name, float $stock, ?float $reorder) => \App\Modules\Medications\Models\Medication::create([
            'tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'name' => $name, 'dose' => '1 tablet', 'route' => 'Oral',
            'frequency' => 'Twice daily', 'schedule' => ['08:00', '20:00'], 'start_date' => '2026-01-01', 'status' => 'active',
            'stock_on_hand' => $stock, 'reorder_level' => $reorder, 'units_per_dose' => 1,
        ]);
        $med('Plenty', 100, 10);
        $med('Low', 8, 10);
        $med('Empty', 0, null);

        $rows = $this->report('medication_stock')['rows'];
        $this->assertSame(['Empty', 'Out of stock'], [$rows[0]['medication'], $rows[0]['status']]);
        $this->assertEquals(['Low', 'Reorder now', 4], [$rows[1]['medication'], $rows[1]['status'], $rows[1]['days_left']]);
        $this->assertEquals(['Plenty', 'OK', 50, 2], [$rows[2]['medication'], $rows[2]['status'], $rows[2]['days_left'], $rows[2]['per_day']]);
    }
}
