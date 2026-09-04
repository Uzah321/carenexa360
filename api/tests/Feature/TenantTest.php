<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwner(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Organization Owner', 'tenant_id' => $tenant->id])->firstOrFail();
        $owner->assignRole($role);

        return $owner;
    }

    public function test_an_organization_owner_can_update_their_own_tenants_company_details(): void
    {
        $tenant = Tenant::create(['name' => 'Old Name', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);

        $response = $this->actingAs($owner)->patchJson("/api/v1/organizations/tenants/{$tenant->id}", [
            'name' => 'New Name',
            'timezone' => 'Africa/Harare',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertSame('Africa/Harare', $tenant->fresh()->timezone);
    }

    public function test_a_plain_carer_cannot_update_the_tenants_company_details(): void
    {
        $tenant = Tenant::create(['name' => 'Old Name', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_a_user_from_another_tenant_cannot_update_this_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerB = $this->makeOwner($tenantB);

        $this->actingAs($ownerB)
            ->patchJson("/api/v1/organizations/tenants/{$tenantA->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_a_platform_admin_can_suspend_and_reactivate_a_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'status' => 'active']);
        $platformAdmin = User::factory()->create(['tenant_id' => null]);

        $suspend = $this->actingAs($platformAdmin)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}/status", ['status' => 'suspended']);
        $suspend->assertOk()->assertJsonPath('data.status', 'suspended');

        $reactivate = $this->actingAs($platformAdmin)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}/status", ['status' => 'active']);
        $reactivate->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_an_organization_owner_cannot_change_their_own_tenants_status(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe', 'status' => 'active']);
        $owner = $this->makeOwner($tenant);

        $this->actingAs($owner)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}/status", ['status' => 'suspended'])
            ->assertForbidden();
    }

    public function test_an_invalid_tenant_status_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $platformAdmin = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($platformAdmin)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}/status", ['status' => 'deleted'])
            ->assertStatus(422);
    }

    public function test_updating_settings_merges_rather_than_replaces(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'country' => 'Zimbabwe',
            'settings' => ['geofence_radius_meters' => 150],
        ]);
        $owner = $this->makeOwner($tenant);

        $response = $this->actingAs($owner)->patchJson("/api/v1/organizations/tenants/{$tenant->id}", [
            'settings' => ['training_expiry_warning_days' => 45],
        ]);

        $response->assertOk();
        $fresh = $tenant->fresh();
        $this->assertSame(150, $fresh->setting('geofence_radius_meters'));
        $this->assertSame(45, $fresh->setting('training_expiry_warning_days'));
    }
}
