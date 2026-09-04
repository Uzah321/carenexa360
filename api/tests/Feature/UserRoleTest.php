<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserRoleTest extends TestCase
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

    protected function makeStaffWithRole(Tenant $tenant, string $roleName = 'Carer / Support Worker'): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        StaffProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    public function test_an_owner_can_list_staff_with_their_current_role(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $carer = $this->makeStaffWithRole($tenant, 'Carer / Support Worker');

        $response = $this->actingAs($owner)->getJson('/api/v1/user-roles');

        $response->assertOk();
        $rows = collect($response->json('data'));
        $this->assertTrue($rows->firstWhere('id', $carer->id)['role'] === 'Carer / Support Worker');
    }

    public function test_family_member_portal_accounts_are_excluded_from_the_list(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $familyMember = User::factory()->create(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $familyMember->assignRole(Role::where(['name' => 'Family Member', 'tenant_id' => $tenant->id])->firstOrFail());

        $response = $this->actingAs($owner)->getJson('/api/v1/user-roles');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($familyMember->id));
    }

    public function test_an_owner_can_change_a_staff_members_role(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $carer = $this->makeStaffWithRole($tenant, 'Carer / Support Worker');

        $response = $this->actingAs($owner)->patchJson("/api/v1/user-roles/{$carer->id}", [
            'role' => 'Senior Carer',
        ]);

        $response->assertOk()->assertJsonPath('data.role', 'Senior Carer');
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($carer->fresh()->hasRole('Senior Carer'));
        $this->assertFalse($carer->fresh()->hasRole('Carer / Support Worker'));
    }

    public function test_a_plain_carer_cannot_change_anyones_role(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeStaffWithRole($tenant, 'Carer / Support Worker');
        $other = $this->makeStaffWithRole($tenant, 'Nurse');

        $this->actingAs($carer)
            ->patchJson("/api/v1/user-roles/{$other->id}", ['role' => 'Organization Admin'])
            ->assertForbidden();
    }

    public function test_an_invalid_role_name_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $carer = $this->makeStaffWithRole($tenant);

        $this->actingAs($owner)
            ->patchJson("/api/v1/user-roles/{$carer->id}", ['role' => 'Supreme Overlord'])
            ->assertStatus(422);
    }

    public function test_an_owner_from_another_tenant_cannot_change_this_staff_members_role(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carerA = $this->makeStaffWithRole($tenantA);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerB = $this->makeOwner($tenantB);

        $this->actingAs($ownerB)
            ->patchJson("/api/v1/user-roles/{$carerA->id}", ['role' => 'Organization Admin'])
            ->assertForbidden();
    }
}
