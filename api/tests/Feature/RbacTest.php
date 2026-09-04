<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_list_tenants(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $response = $this->actingAs($admin)->getJson('/api/v1/organizations/tenants');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_tenant_scoped_user_cannot_list_tenants(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/organizations/tenants');

        $response->assertForbidden();
    }

    public function test_platform_admin_can_create_a_tenant(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($admin)->postJson('/api/v1/organizations/tenants', [
            'name' => 'New Tenant',
            'slug' => 'new-tenant',
            'country' => 'Zimbabwe',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ]);

        // Regression check: the controller re-fetches the model via ->fresh()
        // before building the resource (to pick up DB-level column defaults),
        // which clears Eloquent's wasRecentlyCreated flag that Laravel's
        // automatic-201 heuristic relies on — the controller must therefore
        // set the 201 status explicitly rather than relying on that heuristic.
        $response->assertCreated()->assertJsonPath('data.slug', 'new-tenant');
    }

    public function test_tenant_scoped_user_cannot_create_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/organizations/tenants', [
            'name' => 'Rogue Tenant',
            'slug' => 'rogue-tenant',
            'country' => 'Nowhere',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tenants', ['slug' => 'rogue-tenant']);
    }

    public function test_creating_a_tenant_seeds_the_default_role_catalog(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $roleNames = Role::where('tenant_id', $tenant->id)->pluck('name')->all();

        foreach (DefaultRoles::TENANT_ROLES as $expectedRole) {
            $this->assertContains($expectedRole, $roleNames);
        }
    }
}
