<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchTest extends TestCase
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

    public function test_an_owner_can_edit_and_deactivate_their_own_branch(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Old Name', 'country' => 'Zimbabwe', 'status' => 'active']);

        $update = $this->actingAs($owner)->patchJson("/api/v1/organizations/tenants/{$tenant->id}/branches/{$branch->id}", [
            'name' => 'New Name',
            'region' => 'Harare',
        ]);
        $update->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.region', 'Harare');

        $deactivate = $this->actingAs($owner)->patchJson(
            "/api/v1/organizations/tenants/{$tenant->id}/branches/{$branch->id}/status",
            ['status' => 'inactive']
        );
        $deactivate->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_an_invalid_branch_status_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);

        $this->actingAs($owner)
            ->patchJson("/api/v1/organizations/tenants/{$tenant->id}/branches/{$branch->id}/status", ['status' => 'archived'])
            ->assertStatus(422);
    }

    public function test_a_user_from_another_tenant_cannot_edit_this_branch(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $branch = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerB = $this->makeOwner($tenantB);

        $this->actingAs($ownerB)
            ->patchJson("/api/v1/organizations/tenants/{$tenantA->id}/branches/{$branch->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_a_branch_cannot_be_updated_via_a_mismatched_tenant_in_the_url(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $branchA = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerB = $this->makeOwner($tenantB);

        // ownerB legitimately owns tenantB (passes the authorize() tenant
        // check) but tries to sneak an edit to tenantA's branch by mixing
        // tenant ids in the URL — must still 404, not silently succeed.
        $this->actingAs($ownerB)
            ->patchJson("/api/v1/organizations/tenants/{$tenantB->id}/branches/{$branchA->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }
}
