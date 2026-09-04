<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SafeguardingCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantUserWithRole(string $roleName): array
    {
        $slug = 'tenant-'.uniqid();
        $tenant = Tenant::create(['name' => 'Tenant '.$slug, 'slug' => $slug, 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail();
        $user->assignRole($role);

        return compact('tenant', 'user');
    }

    public function test_a_user_without_an_allowed_role_is_denied_access(): void
    {
        ['user' => $user] = $this->makeTenantUserWithRole('Carer / Support Worker');

        $this->actingAs($user)
            ->getJson('/api/v1/safeguarding-cases')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/v1/safeguarding-cases', [
                'concern_type' => 'Neglect',
                'immediate_risk' => false,
            ])
            ->assertForbidden();
    }

    public function test_a_user_with_an_allowed_role_can_create_a_case(): void
    {
        ['user' => $user] = $this->makeTenantUserWithRole('Care Manager');

        $response = $this->actingAs($user)->postJson('/api/v1/safeguarding-cases', [
            'concern_type' => 'Neglect',
            'immediate_risk' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.reported_by', $user->id);
    }

    public function test_tenant_isolation_applies_on_top_of_the_role_check(): void
    {
        ['user' => $userA] = $this->makeTenantUserWithRole('Care Manager');

        $caseA = \App\Modules\Safeguarding\Models\SafeguardingCase::create([
            'tenant_id' => $userA->tenant_id,
            'concern_type' => 'Neglect',
            'immediate_risk' => false,
            'status' => 'reported',
            'reported_by' => $userA->id,
        ]);

        ['user' => $userB] = $this->makeTenantUserWithRole('Care Manager');

        $this->actingAs($userB)
            ->getJson("/api/v1/safeguarding-cases/{$caseA->id}")
            ->assertForbidden();
    }
}
