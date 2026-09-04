<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithRole(Tenant $tenant, string $roleName): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    public function test_creating_a_branch_records_an_audit_log_entry(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $this->actingAs($admin)->postJson("/api/v1/organizations/tenants/{$tenant->id}/branches", [
            'name' => 'Harare Branch',
            'country' => 'Zimbabwe',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => \App\Modules\Organization\Models\Branch::class,
            'user_id' => $admin->id,
        ]);
    }

    public function test_updating_a_tenant_records_old_and_new_values(): void
    {
        $tenant = Tenant::create(['name' => 'Original Name', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $tenant->update(['name' => 'Renamed Tenant']);

        $log = \App\Modules\Audit\Models\AuditLog::withoutTenantScope()
            ->where('auditable_type', Tenant::class)
            ->where('auditable_id', $tenant->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('Original Name', $log->old_values['name']);
        $this->assertSame('Renamed Tenant', $log->new_values['name']);
    }

    public function test_audit_log_is_scoped_to_the_current_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $branchA = \App\Modules\Organization\Models\Branch::create([
            'tenant_id' => $tenantA->id, 'name' => 'Branch A', 'country' => 'Zimbabwe',
        ]);
        \App\Modules\Organization\Models\Branch::create([
            'tenant_id' => $tenantB->id, 'name' => 'Branch B', 'country' => 'UK',
        ]);

        $userA = $this->makeUserWithRole($tenantA, 'Organization Admin');

        $response = $this->actingAs($userA)->getJson('/api/v1/audit-log');

        $response->assertOk();

        $entityIds = collect($response->json('data'))
            ->where('auditable_type', \App\Modules\Organization\Models\Branch::class)
            ->pluck('auditable_id');

        $this->assertTrue($entityIds->contains($branchA->id));
    }

    public function test_a_carer_cannot_view_the_audit_log(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');

        $this->actingAs($carer)->getJson('/api/v1/audit-log')->assertForbidden();
    }
}
