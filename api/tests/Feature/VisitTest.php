<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithCarer(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        return compact('tenant', 'admin', 'carer', 'serviceUser');
    }

    protected function assignRole(User $user, Tenant $tenant, string $roleName): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());
    }

    public function test_tenant_user_can_create_a_single_visit(): void
    {
        ['admin' => $admin, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();

        $response = $this->actingAs($admin)->postJson('/api/v1/visits', [
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
            'care_tasks' => ['Morning wash', 'Breakfast'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.care_tasks.0', 'Morning wash')
            ->assertJsonPath('warnings', []);
    }

    public function test_recurring_visit_generates_one_row_per_matching_weekday(): void
    {
        ['admin' => $admin, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();

        // 2026-09-07 is a Monday. Ask for Mon/Wed/Fri through 2026-09-18 (two weeks).
        $response = $this->actingAs($admin)->postJson('/api/v1/visits', [
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-07',
            'start_time' => '09:00',
            'end_time' => '09:30',
            'recurrence' => [
                'weekdays' => [1, 3, 5],
                'until' => '2026-09-18',
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(6, $response->json('data'));
        $this->assertDatabaseCount('visits', 6);
    }

    public function test_carer_cannot_be_double_booked(): void
    {
        ['admin' => $admin, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();

        $this->actingAs($admin)->postJson('/api/v1/visits', [
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertCreated();

        $response = $this->actingAs($admin)->postJson('/api/v1/visits', [
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('carer_id');
    }

    public function test_skill_mismatch_warns_but_does_not_block(): void
    {
        ['admin' => $admin, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();

        StaffProfile::create([
            'tenant_id' => $admin->tenant_id,
            'user_id' => $carer->id,
            'skills' => ['manual_handling'],
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/visits', [
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
            'required_skills' => ['manual_handling', 'medication_administration'],
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('warnings'));
        $this->assertStringContainsString('medication_administration', $response->json('warnings.0'));
    }

    public function test_a_carer_can_tick_off_care_tasks_and_medication_during_a_visit(): void
    {
        ['admin' => $admin, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();
        $visit = \App\Modules\Visits\Models\Visit::create([
            'tenant_id' => $admin->tenant_id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'in_progress',
            'care_tasks' => ['Assist with washing', 'Prepare breakfast'],
            'medication_tasks' => true,
        ]);

        $response = $this->actingAs($carer)->patchJson("/api/v1/visits/{$visit->id}", [
            'completed_care_tasks' => ['Assist with washing'],
            'medication_tasks_completed' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.completed_care_tasks', ['Assist with washing'])
            ->assertJsonPath('data.medication_tasks_completed', true);

        // The untouched task list must survive — ticking one task off must
        // not silently clear the others.
        $this->assertSame(['Assist with washing', 'Prepare breakfast'], $visit->fresh()->care_tasks);
    }

    public function test_platform_admin_can_update_any_tenants_visit(): void
    {
        ['tenant' => $tenant, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();
        $visit = \App\Modules\Visits\Models\Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);
        $platformAdmin = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($platformAdmin)
            ->patchJson("/api/v1/visits/{$visit->id}", ['notes' => 'Reviewed by platform support.'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Reviewed by platform support.');
    }

    public function test_tenant_user_cannot_view_another_tenants_visit(): void
    {
        ['carer' => $carerA] = $this->makeTenantWithCarer();
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $serviceUserB = ServiceUser::create([
            'tenant_id' => $tenantB->id, 'first_name' => 'Jane', 'last_name' => 'Doe',
        ]);
        $visitB = \App\Modules\Visits\Models\Visit::create([
            'tenant_id' => $tenantB->id,
            'service_user_id' => $serviceUserB->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        $tenantA = Tenant::create(['name' => 'Tenant A2', 'slug' => 'tenant-a2', 'country' => 'Zimbabwe']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($userA)
            ->getJson("/api/v1/visits/{$visitB->id}")
            ->assertForbidden();
    }

    public function test_a_carer_cannot_reschedule_or_reassign_a_visit(): void
    {
        ['tenant' => $tenant, 'carer' => $carer, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();
        $this->assignRole($carer, $tenant, 'Carer / Support Worker');
        $visit = \App\Modules\Visits\Models\Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($carer)
            ->patchJson("/api/v1/visits/{$visit->id}", ['start_time' => '11:00', 'end_time' => '12:00'])
            ->assertForbidden();

        // Same carer, same visit — a non-schedule field (notes) is still fine.
        $this->actingAs($carer)
            ->patchJson("/api/v1/visits/{$visit->id}", ['notes' => 'Client asked to reschedule.'])
            ->assertOk();
    }

    public function test_a_care_coordinator_can_reassign_a_visit(): void
    {
        ['tenant' => $tenant, 'carer' => $carerA, 'serviceUser' => $serviceUser] = $this->makeTenantWithCarer();
        $carerB = User::factory()->create(['tenant_id' => $tenant->id]);
        $coordinator = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRole($coordinator, $tenant, 'Care Coordinator');
        $visit = \App\Modules\Visits\Models\Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carerA->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->actingAs($coordinator)
            ->patchJson("/api/v1/visits/{$visit->id}", ['carer_id' => $carerB->id])
            ->assertOk()
            ->assertJsonPath('data.carer_id', $carerB->id);
    }
}
