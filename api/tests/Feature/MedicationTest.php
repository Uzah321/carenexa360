<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Medications\Models\Medication;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationTest extends TestCase
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

    public function test_tenant_user_can_add_a_medication(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $response = $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/medications", [
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'Twice daily',
            'start_date' => '2026-09-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Paracetamol')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_administering_a_controlled_drug_without_a_witness_is_rejected(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $medication = Medication::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'name' => 'Morphine',
            'dose' => '10mg',
            'route' => 'Oral',
            'frequency' => 'As needed',
            'start_date' => '2026-09-01',
            'is_controlled_drug' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'administered',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('witness_id');
    }

    public function test_administering_a_controlled_drug_with_a_witness_succeeds(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $witness = User::factory()->create(['tenant_id' => $serviceUser->tenant_id]);

        $medication = Medication::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'name' => 'Morphine',
            'dose' => '10mg',
            'route' => 'Oral',
            'frequency' => 'As needed',
            'start_date' => '2026-09-01',
            'is_controlled_drug' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'administered',
            'witness_id' => $witness->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'administered');
    }

    public function test_non_controlled_drug_administration_does_not_require_a_witness(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $medication = Medication::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'Twice daily',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'administered',
        ]);

        $response->assertCreated();
    }

    public function test_tenant_user_cannot_view_another_tenants_medication(): void
    {
        ['serviceUser' => $serviceUserA] = $this->makeTenantWithServiceUser();
        $medicationA = Medication::create([
            'tenant_id' => $serviceUserA->tenant_id,
            'service_user_id' => $serviceUserA->id,
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'Twice daily',
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userB)
            ->getJson("/api/v1/medications/{$medicationA->id}")
            ->assertForbidden();
    }

    protected function makeParacetamol(ServiceUser $serviceUser, array $overrides = []): Medication
    {
        return Medication::create([
            'tenant_id' => $serviceUser->tenant_id,
            'service_user_id' => $serviceUser->id,
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'Four times daily',
            'schedule' => ['08:00', '12:00', '17:00', '19:30'],
            'start_date' => '2026-09-01',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_a_dose_can_be_recorded_as_not_given_with_a_reason(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $medication = $this->makeParacetamol($serviceUser);

        $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'not_given',
            'not_given_reason' => 'social_leave',
            'scheduled_time' => '19:30',
            'stock_checked' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'not_given')
            ->assertJsonPath('data.not_given_reason', 'social_leave')
            ->assertJsonPath('data.scheduled_time', '19:30')
            ->assertJsonPath('data.stock_checked', true);
    }

    public function test_not_given_requires_a_known_reason(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $medication = $this->makeParacetamol($serviceUser);

        $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", ['status' => 'not_given'])
            ->assertUnprocessable()->assertJsonValidationErrors('not_given_reason');

        $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'not_given',
            'not_given_reason' => 'felt_like_it',
        ])->assertUnprocessable()->assertJsonValidationErrors('not_given_reason');
    }

    public function test_a_given_dose_cannot_carry_a_not_given_reason(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $medication = $this->makeParacetamol($serviceUser);

        $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", [
            'status' => 'administered',
            'not_given_reason' => 'refused',
        ])->assertUnprocessable()->assertJsonValidationErrors('not_given_reason');
    }

    public function test_giving_a_controlled_prn_dose_requires_a_witness(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $medication = $this->makeParacetamol($serviceUser, ['name' => 'Morphine', 'is_prn' => true, 'is_controlled_drug' => true]);

        $this->actingAs($admin)->postJson("/api/v1/medications/{$medication->id}/administrations", ['status' => 'prn'])
            ->assertUnprocessable()->assertJsonValidationErrors('witness_id');
    }

    public function test_schedule_times_must_be_hh_mm(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();

        $this->actingAs($admin)->postJson("/api/v1/service-users/{$serviceUser->id}/medications", [
            'name' => 'Paracetamol',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'Twice daily',
            'schedule' => ['08:00', 'evening'],
            'start_date' => '2026-09-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule.1');
    }

    public function test_medication_list_includes_only_todays_administrations(): void
    {
        ['admin' => $admin, 'serviceUser' => $serviceUser] = $this->makeTenantWithServiceUser();
        $medication = $this->makeParacetamol($serviceUser);

        $medication->administrations()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'status' => 'administered',
            'scheduled_time' => '08:00',
            'administered_at' => now()->subDay(),
        ]);
        $medication->administrations()->create([
            'tenant_id' => $serviceUser->tenant_id,
            'status' => 'administered',
            'scheduled_time' => '08:00',
            'administered_at' => now(),
        ]);

        $this->actingAs($admin)->getJson("/api/v1/service-users/{$serviceUser->id}/medications")
            ->assertOk()
            ->assertJsonCount(1, 'data.0.today_administrations')
            ->assertJsonPath('data.0.today_administrations.0.scheduled_time', '08:00');
    }
}
