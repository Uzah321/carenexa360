<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ServiceUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_create_and_view_a_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/service-users', [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'date_of_birth' => '1950-01-01',
            'allergies' => ['Penicillin'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.allergies.0', 'Penicillin');

        $serviceUserId = $response->json('data.id');

        $this->actingAs($user)
            ->getJson("/api/v1/service-users/{$serviceUserId}")
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Smith');
    }

    public function test_a_service_user_can_be_created_with_hospital_records(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/service-users', [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'referring_hospital' => 'Parirenyatwa Group of Hospitals',
            'hospital_record_number' => 'PGH-00123',
            'nhs_number' => '462 423 5614',
            'discharge_date' => '2026-09-01',
            'discharge_summary' => 'Discharged post-hip-fracture surgery; needs mobility support and pain review.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.referring_hospital', 'Parirenyatwa Group of Hospitals')
            ->assertJsonPath('data.hospital_record_number', 'PGH-00123')
            ->assertJsonPath('data.nhs_number', '462 423 5614')
            ->assertJsonPath('data.discharge_date', '2026-09-01')
            ->assertJsonPath(
                'data.discharge_summary',
                'Discharged post-hip-fracture surgery; needs mobility support and pain review.',
            );
    }

    public function test_platform_admin_cannot_create_a_service_user(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($admin)->postJson('/api/v1/service-users', [
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_user_cannot_view_another_tenants_service_user(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $serviceUserB = ServiceUser::create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        // Implicit route-model-binding runs before our 'tenant' middleware sets
        // the tenant context, so it's the controller's explicit authorization
        // check — not scope-filtered binding — that rejects this (403).
        $this->actingAs($userA)
            ->getJson("/api/v1/service-users/{$serviceUserB->id}")
            ->assertForbidden();
    }

    public function test_service_user_contacts_can_be_added_and_listed(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $this->actingAs($user)->postJson("/api/v1/service-users/{$serviceUser->id}/contacts", [
            'type' => 'next_of_kin',
            'name' => 'Mary Smith',
            'relationship' => 'Daughter',
            'phone' => '0771234567',
        ])->assertCreated();

        $this->actingAs($user)
            ->getJson("/api/v1/service-users/{$serviceUser->id}/contacts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mary Smith');
    }

    protected function makeAdmin(Tenant $tenant, string $roleName = 'Organization Admin'): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAdmin($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/service-users/{$serviceUser->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin)
            ->patchJson("/api/v1/service-users/{$serviceUser->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_an_admin_can_permanently_delete_a_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeAdmin($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/service-users/{$serviceUser->id}")
            ->assertNoContent();

        // Gone, not archived — there's no soft-deleted row left behind.
        $this->assertDatabaseMissing('service_users', ['id' => $serviceUser->id]);
    }

    public function test_a_non_admin_cannot_deactivate_or_delete_a_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeAdmin($tenant, 'Carer / Support Worker');
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith']);

        $this->actingAs($carer)
            ->patchJson("/api/v1/service-users/{$serviceUser->id}/deactivate")
            ->assertForbidden();
        $this->actingAs($carer)
            ->deleteJson("/api/v1/service-users/{$serviceUser->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('service_users', ['id' => $serviceUser->id, 'status' => 'active']);
    }

    public function test_an_admin_cannot_delete_another_tenants_service_user(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $adminA = $this->makeAdmin($tenantA);
        $serviceUserB = ServiceUser::create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($adminA)
            ->deleteJson("/api/v1/service-users/{$serviceUserB->id}")
            ->assertForbidden();
    }
}
