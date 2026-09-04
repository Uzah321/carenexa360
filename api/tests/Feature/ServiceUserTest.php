<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_tenant_user_can_archive_a_service_user(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/service-users/{$serviceUser->id}")
            ->assertNoContent();

        // Soft deleted, not gone — excluded from the default index/show
        // queries but the row and its history are still in the database.
        $this->assertSoftDeleted($serviceUser);
        $this->actingAs($user)
            ->getJson('/api/v1/service-users')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_tenant_user_cannot_archive_another_tenants_service_user(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $serviceUserB = ServiceUser::create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($userA)
            ->deleteJson("/api/v1/service-users/{$serviceUserB->id}")
            ->assertForbidden();
    }
}
