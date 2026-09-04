<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithServiceUser(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        return compact('tenant', 'admin', 'serviceUser');
    }

    public function test_a_normal_reading_does_not_raise_a_clinical_alert(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'oxygen_saturation',
            'value' => ['value' => 98],
        ]);

        $response->assertCreated()->assertJsonCount(0, 'data.alerts');
        $this->assertDatabaseCount('clinical_alerts', 0);
    }

    public function test_a_threshold_breach_raises_a_clinical_alert(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'oxygen_saturation',
            'value' => ['value' => 85],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.alerts');
        $this->assertDatabaseHas('clinical_alerts', [
            'service_user_id' => $serviceUser->id,
            'severity' => 'critical',
        ]);
    }

    public function test_a_blood_pressure_breach_is_detected_from_systolic_and_diastolic(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'blood_pressure',
            'value' => ['systolic' => 200, 'diastolic' => 130],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.alerts');
        $this->assertDatabaseHas('clinical_alerts', ['severity' => 'critical']);
    }

    public function test_an_alert_can_be_acknowledged(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $create = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'oxygen_saturation',
            'value' => ['value' => 85],
        ]);

        $alertId = $create->json('data.alerts.0.id');

        $response = $this->actingAs($admin)->postJson("/api/v1/clinical-alerts/{$alertId}/acknowledge");

        $response->assertOk()->assertJsonPath('data.acknowledged_by', $admin->id);
        $this->assertNotNull(ClinicalAlert::find($alertId)->acknowledged_at);
    }

    public function test_tenant_user_cannot_view_another_tenants_observation(): void
    {
        ['serviceUser' => $serviceUserA] = $this->makeTenantWithServiceUser();

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $observation = \App\Modules\Observations\Models\Observation::create([
            'tenant_id' => $serviceUserA->tenant_id,
            'service_user_id' => $serviceUserA->id,
            'type' => 'pulse',
            'value' => ['value' => 70],
            'recorded_at' => now(),
        ]);

        $this->actingAs($userB)
            ->getJson("/api/v1/observations/{$observation->id}")
            ->assertForbidden();
    }
}
