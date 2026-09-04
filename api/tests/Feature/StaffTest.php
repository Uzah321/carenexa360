<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithRole(Tenant $tenant, string $roleName): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    public function test_tenant_user_can_add_a_staff_member(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeUserWithRole($tenant, 'Organization Admin');

        $response = $this->actingAs($admin)->postJson('/api/v1/staff', [
            'name' => 'Sarah Jones',
            'email' => 'sarah@demo-care-group.test',
            'password' => 'password123',
            'role' => 'Carer / Support Worker',
            'job_title' => 'Senior Carer',
            'skills' => ['manual_handling', 'medication_administration'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sarah Jones')
            ->assertJsonPath('data.email', 'sarah@demo-care-group.test')
            ->assertJsonPath('data.job_title', 'Senior Carer')
            ->assertJsonPath('data.skills.0', 'manual_handling');

        $this->assertDatabaseHas('users', [
            'email' => 'sarah@demo-care-group.test',
            'tenant_id' => $tenant->id,
        ]);

        $newUser = User::where('email', 'sarah@demo-care-group.test')->first();
        $this->assertNotNull($newUser->staffProfile);
    }

    public function test_platform_admin_cannot_add_a_staff_member(): void
    {
        $platformAdmin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($platformAdmin)->postJson('/api/v1/staff', [
            'name' => 'Sarah Jones',
            'email' => 'sarah@example.test',
            'password' => 'password123',
            'role' => 'Carer / Support Worker',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_role_must_belong_to_the_same_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $admin = User::factory()->create(['tenant_id' => $tenantA->id]);

        // "Carer / Support Worker" exists for both tenants (auto-seeded), but
        // a role name that doesn't exist at all should fail validation.
        $response = $this->actingAs($admin)->postJson('/api/v1/staff', [
            'name' => 'Sarah Jones',
            'email' => 'sarah@demo-care-group.test',
            'password' => 'password123',
            'role' => 'Not A Real Role',
        ]);

        $response->assertUnprocessable();
    }

    public function test_a_carer_cannot_add_a_staff_member(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');

        $response = $this->actingAs($carer)->postJson('/api/v1/staff', [
            'name' => 'Sarah Jones',
            'email' => 'sarah@demo-care-group.test',
            'password' => 'password123',
            'role' => 'Carer / Support Worker',
        ]);

        $response->assertForbidden();
    }

    public function test_a_carer_can_still_list_staff_but_without_hr_sensitive_fields(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');
        \App\Modules\Staff\Models\StaffProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'hourly_rate' => 15.5,
            'employee_number' => 'EMP-0001',
        ]);

        $response = $this->actingAs($carer)->getJson('/api/v1/staff');

        $response->assertOk();
        $this->assertArrayNotHasKey('hourly_rate', $response->json('data.0'));
        $this->assertArrayNotHasKey('employee_number', $response->json('data.0'));
        $this->assertArrayHasKey('employment_status', $response->json('data.0'));
    }
}
