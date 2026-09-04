<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Training\Models\TrainingCourse;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TodayControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAnalyticsAdmin(Tenant $tenant): User
    {
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Organization Admin', 'tenant_id' => $tenant->id])->firstOrFail();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_a_plain_carer_cannot_view_today(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)->getJson('/api/v1/today')->assertForbidden();
    }

    public function test_todays_visits_are_returned_for_the_requested_date(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAnalyticsAdmin($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $today = now()->toDateString();
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => $today, 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled']);

        $response = $this->actingAs($admin)->getJson('/api/v1/today');

        $response->assertOk()
            ->assertJsonPath('date', $today)
            ->assertJsonCount(1, 'visits');
    }

    public function test_missed_visits_and_open_incidents_stats_are_correct(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAnalyticsAdmin($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'missed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'visit_date' => now()->toDateString(), 'start_time' => '11:00', 'end_time' => '12:00', 'status' => 'completed']);

        Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'fall', 'severity' => 'medium', 'description' => 'Slipped.', 'status' => 'reported', 'reported_by' => $admin->id]);
        Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'fall', 'severity' => 'critical', 'description' => 'Serious fall.', 'status' => 'reported', 'reported_by' => $admin->id]);
        Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'fall', 'severity' => 'low', 'description' => 'Resolved.', 'status' => 'closed', 'reported_by' => $admin->id]);

        $response = $this->actingAs($admin)->getJson('/api/v1/today');

        $response->assertOk()
            ->assertJsonPath('stats.missed_visits_this_week', 1)
            // Only the medium-severity open incident counts — critical is
            // excluded (surfaced elsewhere, not folded into "non-serious")
            // and the closed one doesn't count as open.
            ->assertJsonPath('stats.open_incidents', 1);
    }

    public function test_training_expiring_soon_excludes_already_expired_records(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAnalyticsAdmin($tenant);
        $course = TrainingCourse::create(['tenant_id' => $tenant->id, 'name' => 'Manual Handling']);

        TrainingRecord::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'training_course_id' => $course->id, 'completed_date' => now()->subYear(), 'expiry_date' => now()->addDays(10)->toDateString()]);
        TrainingRecord::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'training_course_id' => $course->id, 'completed_date' => now()->subYears(3), 'expiry_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/today');

        $response->assertOk()->assertJsonPath('stats.training_expiring_soon.count', 1);
    }

    public function test_a_configured_training_warning_window_widens_what_counts_as_expiring_soon(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'settings' => ['training_expiry_warning_days' => 60]]);
        $admin = $this->makeAnalyticsAdmin($tenant);
        $course = TrainingCourse::create(['tenant_id' => $tenant->id, 'name' => 'Manual Handling']);

        // 45 days out — outside the default 30-day window, inside this
        // tenant's configured 60-day window.
        TrainingRecord::create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'training_course_id' => $course->id, 'completed_date' => now()->subYear(), 'expiry_date' => now()->addDays(45)->toDateString()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/today');

        $response->assertOk()->assertJsonPath('stats.training_expiring_soon.count', 1);
    }

    public function test_client_snapshot_returns_care_plan_and_medications(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAnalyticsAdmin($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $carePlan = CarePlan::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'version' => 1, 'status' => 'active', 'effective_from' => now()->toDateString()]);
        CarePlanSection::create(['tenant_id' => $tenant->id, 'care_plan_id' => $carePlan->id, 'area' => 'personal_care', 'identified_need' => 'Needs support washing', 'goal' => 'Maintain hygiene', 'intervention' => 'Assist with washing', 'status' => 'ongoing']);

        $medication = Medication::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'name' => 'Paracetamol', 'dose' => '500mg', 'route' => 'Oral', 'frequency' => 'Twice daily', 'start_date' => now()->toDateString(), 'status' => 'active']);
        MedicationAdministration::create(['tenant_id' => $tenant->id, 'medication_id' => $medication->id, 'status' => 'administered', 'administered_at' => now(), 'administered_by' => $admin->id]);

        $response = $this->actingAs($admin)->getJson("/api/v1/today/service-users/{$serviceUser->id}/snapshot");

        $response->assertOk()
            ->assertJsonCount(1, 'care_plan_sections')
            ->assertJsonCount(1, 'medications')
            ->assertJsonPath('medications.0.latest_administration.status', 'administered');
    }

    public function test_client_snapshot_is_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $adminB = $this->makeAnalyticsAdmin($tenantB);

        $serviceUserA = ServiceUser::create(['tenant_id' => $tenantA->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $this->actingAs($adminB)
            ->getJson("/api/v1/today/service-users/{$serviceUserA->id}/snapshot")
            ->assertForbidden();
    }
}
