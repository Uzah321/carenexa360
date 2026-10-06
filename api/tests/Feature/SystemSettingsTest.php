<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessments\Models\AssessmentResponse;
use App\Modules\Assessments\Models\AssessmentTemplate;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Support\TenantSettings;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        TenantSettings::forget();
        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'timezone' => 'Africa/Harare', 'currency' => 'USD']);
        $this->owner = $this->userWithRole('Organization Owner');
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $user->assignRole(Role::where(['name' => $role, 'tenant_id' => $this->tenant->id])->firstOrFail());

        return $user;
    }

    protected function saveSettings(array $payload)
    {
        $response = $this->actingAs($this->owner)->patchJson("/api/v1/organizations/tenants/{$this->tenant->id}", $payload);
        TenantSettings::forget();

        return $response;
    }

    protected function client(array $attributes = []): ServiceUser
    {
        return ServiceUser::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'status' => 'active', ...$attributes]);
    }

    // ---- Saving and validation ------------------------------------------------

    public function test_settings_are_validated_and_company_details_must_be_real_values(): void
    {
        $this->saveSettings(['timezone' => 'Harare'])->assertUnprocessable()->assertJsonValidationErrors('timezone');
        $this->saveSettings(['currency' => 'pounds'])->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->saveSettings(['locale' => 'english'])->assertUnprocessable()->assertJsonValidationErrors('locale');
        $this->saveSettings(['settings' => ['overtime_weekly_hours' => 500]])->assertUnprocessable()->assertJsonValidationErrors('settings.overtime_weekly_hours');
        $this->saveSettings(['settings' => ['care_pathway' => ['made_up' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('settings.care_pathway');

        $this->saveSettings(['currency' => 'gbp', 'locale' => 'en-GB', 'timezone' => 'Europe/London'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'GBP');
    }

    public function test_unsaved_settings_fall_back_to_defaults_and_partial_saves_keep_the_rest(): void
    {
        $this->saveSettings(['settings' => [
            'overtime_weekly_hours' => 45,
            'care_pathway' => ['review_interval_months' => 3],
            'reference_data' => ['skills' => ['Hoist Trained', 'Stoma Care']],
        ]])->assertOk()
            ->assertJsonPath('data.settings.overtime_weekly_hours', 45)
            ->assertJsonPath('data.settings.mileage_rate_per_mile', 0.45)
            ->assertJsonPath('data.settings.care_pathway.review_interval_months', 3)
            ->assertJsonPath('data.settings.care_pathway.assessment_within_days', 3)
            ->assertJsonPath('data.settings.reference_data.skills', ['Hoist Trained', 'Stoma Care'])
            ->assertJsonPath('data.settings.reference_data.equipment.0', 'Walking frame');

        // A later save of something else doesn't lose the earlier values.
        $this->saveSettings(['settings' => ['geofence_radius_meters' => 150]])
            ->assertJsonPath('data.settings.overtime_weekly_hours', 45)
            ->assertJsonPath('data.settings.reference_data.skills', ['Hoist Trained', 'Stoma Care']);
    }

    public function test_the_signed_in_user_carries_their_organisations_preferences(): void
    {
        $this->saveSettings(['settings' => ['reference_data' => ['care_tasks' => ['Feed the cat']]]]);

        $this->actingAs($this->owner)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.currency', 'USD')
            ->assertJsonPath('data.tenant.timezone', 'Africa/Harare')
            ->assertJsonPath('data.tenant.settings.reference_data.care_tasks', ['Feed the cat'])
            ->assertJsonPath('data.tenant.settings.care_pathway.care_plan_within_days', 7);
    }

    public function test_only_owners_and_admins_can_change_settings(): void
    {
        $carer = $this->userWithRole('Carer / Support Worker');

        $this->actingAs($carer)->patchJson("/api/v1/organizations/tenants/{$this->tenant->id}", ['settings' => ['overtime_weekly_hours' => 10]])
            ->assertForbidden();
    }

    // ---- Settings drive behaviour ------------------------------------------------

    public function test_late_arrival_grace_and_timezone_decide_late_visits(): void
    {
        $manager = $this->userWithRole('Care Manager');
        $carer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $ruth = $this->client();
        $visit = fn (string $checkInUtc) => Visit::create([
            'tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'carer_id' => $carer->id, 'visit_date' => '2026-06-10',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed', 'check_in_at' => $checkInUtc,
        ]);
        // 09:00 in Harare is 07:00 UTC.
        $visit('2026-06-10 07:05:00');   // 5 min late — within the grace
        $visit('2026-06-10 07:30:00');   // 30 min late

        $late = fn () => $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=late_visits&from=2026-06-01&to=2026-06-30')->assertOk()->json('rows');

        $rows = $late();
        $this->assertCount(1, $rows);
        $this->assertSame(30, $rows[0]['minutes_late']);
        $this->assertSame('09:30', $rows[0]['checked_in']);

        $this->saveSettings(['settings' => ['late_arrival_minutes' => 40]]);
        $this->assertCount(0, $late());
    }

    public function test_overtime_threshold_comes_from_settings(): void
    {
        $manager = $this->userWithRole('Care Manager');
        $amy = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Amy Moyo']);
        StaffProfile::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id]);
        foreach (['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11'] as $d) {
            DutyPeriod::create(['tenant_id' => $this->tenant->id, 'user_id' => $amy->id, 'start_lat' => 0, 'start_lng' => 0, 'started_at' => "{$d} 06:00:00", 'ended_at' => "{$d} 15:00:00"]);
        }
        $overtime = fn () => $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=overtime&from=2026-06-01&to=2026-06-30')->assertOk();

        $overtime()->assertJsonCount(0, 'rows');  // 36h, under the default 40
        $this->saveSettings(['settings' => ['overtime_weekly_hours' => 30]]);
        $overtime()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.overtime', 6)->assertJsonPath('title', 'Overtime (over 30 hours a week)');
    }

    public function test_complaint_response_days_and_stock_reorder_days_come_from_settings(): void
    {
        $this->saveSettings(['settings' => ['complaint_response_days' => 10, 'stock_reorder_days' => 30]]);
        $manager = $this->userWithRole('Care Manager');

        $this->actingAs($manager)->postJson('/api/v1/complaints', [
            'received_date' => '2026-06-01', 'complainant_name' => 'A', 'category' => 'other', 'description' => 'x',
        ])->assertCreated()->assertJsonPath('data.response_due_date', '2026-06-11');

        // 40 tablets at 2 a day lasts 20 days: fine at 7 days' warning, a reorder at 30.
        $medication = Medication::create([
            'tenant_id' => $this->tenant->id, 'service_user_id' => $this->client()->id, 'name' => 'Metformin', 'dose' => '1', 'route' => 'Oral',
            'frequency' => 'Twice daily', 'schedule' => ['08:00', '20:00'], 'start_date' => '2026-01-01', 'status' => 'active', 'stock_on_hand' => 40,
        ]);
        $this->assertTrue($medication->needsReorder());
    }

    public function test_todays_medication_round_follows_the_tenants_day_not_utcs(): void
    {
        $this->travelTo(now()->parse('2026-06-10 06:00:00', 'UTC'));   // 08:00 in Harare
        $ruth = $this->client();
        $medication = Medication::create([
            'tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'name' => 'Metformin', 'dose' => '1', 'route' => 'Oral',
            'frequency' => 'Twice daily', 'schedule' => ['08:00'], 'start_date' => '2026-01-01', 'status' => 'active',
        ]);
        // 23:30 UTC on the 9th is 01:30 on the 10th in Harare — today's, not yesterday's.
        MedicationAdministration::create(['tenant_id' => $this->tenant->id, 'medication_id' => $medication->id, 'status' => 'administered', 'administered_at' => '2026-06-09 23:30:00']);
        // 21:00 UTC on the 9th is 23:00 on the 9th in Harare — yesterday's.
        MedicationAdministration::create(['tenant_id' => $this->tenant->id, 'medication_id' => $medication->id, 'status' => 'administered', 'administered_at' => '2026-06-09 21:00:00']);

        $this->actingAs($this->owner)->getJson("/api/v1/service-users/{$ruth->id}/medications")
            ->assertOk()
            ->assertJsonCount(1, 'data.0.today_administrations');
    }

    // ---- Data maintenance ------------------------------------------------------------

    public function test_data_checks_find_gaps_and_link_to_the_record(): void
    {
        $ruth = $this->client();   // no DOB, NHS number, location, plan, manager or carers
        $this->client(['first_name' => 'Peter', 'date_of_birth' => '1950-01-01', 'nhs_number' => '1', 'latitude' => 1, 'longitude' => 1]);

        $checks = collect($this->actingAs($this->owner)->getJson('/api/v1/data-maintenance/checks')->assertOk()->json('data'))->keyBy('key');

        $this->assertSame(1, $checks['clients_missing_dob']['count']);
        $this->assertSame(['label' => 'Ruth Chikafu', 'link' => "/service-users/{$ruth->id}"], $checks['clients_missing_dob']['sample'][0]);
        $this->assertSame(2, $checks['clients_without_plan']['count']);
        $this->assertSame(0, $checks['stale_visits']['count']);

        $carer = $this->userWithRole('Carer / Support Worker');
        $this->actingAs($carer)->getJson('/api/v1/data-maintenance/checks')->assertForbidden();
        $this->actingAs($carer)->get('/api/v1/data-maintenance/exports/service_users')->assertForbidden();
    }

    public function test_csv_exports_contain_the_tenants_records_and_neutralise_formulas(): void
    {
        $this->client(['first_name' => '=HYPERLINK("http://evil")']);
        $other = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        ServiceUser::create(['tenant_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Tenant']);

        $response = $this->actingAs($this->owner)->get('/api/v1/data-maintenance/exports/service_users');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();

        $this->assertStringContainsString('"First name","Last name"', str_replace(['ID,'], '', $csv));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Other', $csv);

        $this->actingAs($this->owner)->get('/api/v1/data-maintenance/exports/passwords')->assertNotFound();
    }

    // ---- Care pathway ------------------------------------------------------------------

    public function test_care_pathway_tracks_each_stage_against_the_timescales(): void
    {
        $this->travelTo(now()->parse('2026-06-20 10:00:00', 'UTC'));
        $this->saveSettings(['settings' => ['care_pathway' => ['assessment_within_days' => 2, 'care_plan_within_days' => 5, 'first_review_within_weeks' => 1]]]);

        $this->travelTo(now()->parse('2026-06-01 10:00:00', 'UTC'));
        $ruth = $this->client();
        $template = AssessmentTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'Initial', 'fields' => []]);
        AssessmentResponse::create(['tenant_id' => $this->tenant->id, 'assessment_template_id' => $template->id, 'service_user_id' => $ruth->id, 'answers' => [], 'status' => 'completed', 'completed_at' => '2026-06-02 12:00:00']);
        $this->travelTo(now()->parse('2026-06-09 10:00:00', 'UTC'));
        CarePlan::create(['tenant_id' => $this->tenant->id, 'service_user_id' => $ruth->id, 'version' => 1, 'status' => 'active', 'effective_from' => '2026-06-09']);

        $this->travelTo(now()->parse('2026-06-20 10:00:00', 'UTC'));
        $stages = collect($this->actingAs($this->owner)->getJson("/api/v1/service-users/{$ruth->id}/care-pathway")->assertOk()->json('data.stages'))->keyBy('key');

        $this->assertSame('done', $stages['assessment']['status']);              // by 3 June, done 2 June
        $this->assertSame(['done_late', '2026-06-06', '2026-06-09'], [$stages['care_plan']['status'], $stages['care_plan']['due'], $stages['care_plan']['done']]);
        $this->assertSame(['overdue', '2026-06-16'], [$stages['first_review']['status'], $stages['first_review']['due']]);

        $manager = $this->userWithRole('Care Manager');
        $this->actingAs($manager)->getJson('/api/v1/reports/generate?key=care_pathway')
            ->assertOk()
            ->assertJsonPath('rows.0.first_review', 'Overdue (2026-06-16)')
            ->assertJsonPath('rows.0.overdue', 1);
    }
}
