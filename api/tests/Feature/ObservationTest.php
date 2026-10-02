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

    public function test_readings_are_classified_high_or_low_with_a_news2_score(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $fever = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'temperature',
            'value' => ['value' => 38.6],
        ]);
        $fever->assertCreated()
            ->assertJsonPath('data.news2.total', 1)
            ->assertJsonPath('data.news2.parameters.0.direction', 'high')
            ->assertJsonPath('data.alerts.0.severity', 'warning');
        $this->assertStringContainsString('High temperature', $fever->json('data.alerts.0.message'));

        $lowBp = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'blood_pressure',
            'value' => ['systolic' => 88, 'diastolic' => 65],
        ]);
        $lowBp->assertCreated()
            ->assertJsonPath('data.news2.total', 3)
            ->assertJsonPath('data.news2.parameters.0.direction', 'low')
            ->assertJsonPath('data.alerts.0.severity', 'critical');

        $normal = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'respiratory_rate',
            'value' => ['value' => 16],
        ]);
        $normal->assertCreated()->assertJsonPath('data.news2.total', 0)->assertJsonCount(0, 'data.alerts');
    }

    public function test_spo2_on_scale_2_does_not_flag_a_target_range_reading(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        // 89% is a red-zone reading on Scale 1, but on target for a Scale 2 (e.g. COPD) patient on air.
        $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'oxygen_saturation',
            'value' => ['value' => 89, 'spo2_scale' => 2, 'on_oxygen' => false],
        ])->assertCreated()->assertJsonPath('data.news2.total', 0)->assertJsonCount(0, 'data.alerts');
    }

    public function test_a_full_news2_set_is_scored_and_escalated(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'news2',
            'value' => [
                'respiration_rate' => 22, 'spo2' => 93, 'spo2_scale' => 1, 'on_oxygen' => true,
                'systolic' => 105, 'pulse' => 112, 'consciousness' => 'alert', 'temperature' => 38.4,
            ],
        ]);

        // 2 + 2 + 2 + 1 + 2 + 0 + 1 = 10
        $response->assertCreated()
            ->assertJsonPath('data.news2.total', 10)
            ->assertJsonPath('data.news2.risk', 'high')
            ->assertJsonCount(7, 'data.news2.parameters')
            ->assertJsonPath('data.alerts.0.severity', 'critical');
        $this->assertStringContainsString('Call 999', $response->json('data.alerts.0.message'));
    }

    public function test_a_full_news2_set_needs_every_parameter(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'news2',
            'value' => ['respiration_rate' => 16, 'pulse' => 70, 'consciousness' => 'sleepy'],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'value.spo2', 'value.on_oxygen', 'value.systolic', 'value.temperature', 'value.consciousness',
        ]);
    }

    public function test_correcting_a_reading_re_evaluates_its_alert(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $id = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/observations", [
            'type' => 'pulse',
            'value' => ['value' => 140],
        ])->assertJsonCount(1, 'data.alerts')->json('data.id');

        $this->actingAs($admin)->patchJson("/api/v1/observations/{$id}", ['value' => ['value' => 72]])
            ->assertOk()
            ->assertJsonCount(0, 'data.alerts')
            ->assertJsonPath('data.news2.total', 0);
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
