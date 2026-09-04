<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ComplianceRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeComplianceAdmin(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Compliance Officer', 'tenant_id' => $tenant->id])->firstOrFail();
        $admin->assignRole($role);

        return compact('tenant', 'admin');
    }

    public function test_a_plain_carer_cannot_view_or_create_compliance_requirements(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)->getJson('/api/v1/compliance-requirements')->assertForbidden();
        $this->actingAs($carer)->postJson('/api/v1/compliance-requirements', [
            'name' => 'Public Liability Insurance',
        ])->assertForbidden();
    }

    public function test_compliance_officer_can_create_and_view_a_requirement(): void
    {
        ['admin' => $admin] = $this->makeComplianceAdmin();

        $response = $this->actingAs($admin)->postJson('/api/v1/compliance-requirements', [
            'name' => 'Public Liability Insurance',
            'category' => 'insurance',
            'jurisdiction' => 'England',
            'renewal_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Public Liability Insurance')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.expiry_status', 'valid');
    }

    public function test_expiry_status_reflects_renewal_date_including_the_far_future_case(): void
    {
        ['tenant' => $tenant] = $this->makeComplianceAdmin();

        $expired = ComplianceRequirement::create([
            'tenant_id' => $tenant->id,
            'name' => 'Expired registration',
            'renewal_date' => now()->subDay()->toDateString(),
        ]);

        $expiringSoon = ComplianceRequirement::create([
            'tenant_id' => $tenant->id,
            'name' => 'Expiring soon registration',
            'renewal_date' => now()->addDays(10)->toDateString(),
        ]);

        // This is exactly the shape of date math that previously exposed the
        // Carbon 3 diffInDays() sign bug (see TrainingTest and the phasing
        // memory) — a renewal more than a year out must not be misreported
        // as "expiring_soon" via a negative diff satisfying <= 30.
        $farFuture = ComplianceRequirement::create([
            'tenant_id' => $tenant->id,
            'name' => 'Far future registration',
            'renewal_date' => now()->addYears(2)->toDateString(),
        ]);

        $noExpiry = ComplianceRequirement::create([
            'tenant_id' => $tenant->id,
            'name' => 'No expiry registration',
        ]);

        $this->assertEquals('expired', $expired->expiry_status);
        $this->assertEquals('expiring_soon', $expiringSoon->expiry_status);
        $this->assertEquals('valid', $farFuture->expiry_status);
        $this->assertEquals('no_expiry', $noExpiry->expiry_status);
    }

    public function test_a_compliance_officer_can_edit_every_field_and_delete_a_requirement(): void
    {
        ['admin' => $admin] = $this->makeComplianceAdmin();
        $requirement = ComplianceRequirement::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Old Name',
            'status' => 'pending',
        ]);

        $update = $this->actingAs($admin)->patchJson("/api/v1/compliance-requirements/{$requirement->id}", [
            'name' => 'New Name',
            'category' => 'insurance',
            'status' => 'compliant',
        ]);
        $update->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.category', 'insurance')
            ->assertJsonPath('data.status', 'compliant');

        $delete = $this->actingAs($admin)->deleteJson("/api/v1/compliance-requirements/{$requirement->id}");
        $delete->assertNoContent();

        // Soft-deleted — gone from the list, but not destroyed at the DB level.
        $this->actingAs($admin)->getJson('/api/v1/compliance-requirements')->assertJsonCount(0, 'data');
        $this->assertSoftDeleted('compliance_requirements', ['id' => $requirement->id]);
    }

    public function test_a_user_from_another_tenant_cannot_delete_this_requirement(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $requirementA = ComplianceRequirement::create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A requirement']);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $adminB->assignRole(Role::where(['name' => 'Compliance Officer', 'tenant_id' => $tenantB->id])->firstOrFail());

        $this->actingAs($adminB)
            ->deleteJson("/api/v1/compliance-requirements/{$requirementA->id}")
            ->assertForbidden();
    }

    public function test_tenant_isolation_applies_to_compliance_requirements(): void
    {
        $userA = $this->makeComplianceAdmin()['admin'];
        $requirementA = ComplianceRequirement::create([
            'tenant_id' => $userA->tenant_id,
            'name' => 'Tenant A requirement',
        ]);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $roleB = Role::where(['name' => 'Compliance Officer', 'tenant_id' => $tenantB->id])->firstOrFail();
        $userB->assignRole($roleB);

        $this->actingAs($userB)
            ->getJson("/api/v1/compliance-requirements/{$requirementA->id}")
            ->assertForbidden();
    }
}
