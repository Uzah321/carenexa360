<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'United Kingdom',
        ]);

        $this->branchA = Branch::create([
            'tenant_id' => $this->tenantA->id, 'name' => 'Branch A', 'country' => 'Zimbabwe',
        ]);
        $this->branchB = Branch::create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Branch B', 'country' => 'United Kingdom',
        ]);

        Department::create(['tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id, 'name' => 'Dept A']);
        Department::create(['tenant_id' => $this->tenantB->id, 'branch_id' => $this->branchB->id, 'name' => 'Dept B']);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_tenant_user_cannot_view_another_tenants_branch_list(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson("/api/v1/organizations/tenants/{$this->tenantB->id}/branches");

        $response->assertForbidden();
    }

    public function test_tenant_user_cannot_view_another_tenants_departments(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson("/api/v1/organizations/tenants/{$this->tenantB->id}/departments");

        $response->assertForbidden();
    }

    public function test_tenant_user_cannot_view_another_tenant_directly(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson("/api/v1/organizations/tenants/{$this->tenantB->id}");

        $response->assertForbidden();
    }

    public function test_tenant_user_can_view_own_tenants_branches(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson("/api/v1/organizations/tenants/{$this->tenantA->id}/branches");

        $response->assertOk()->assertJsonPath('data.0.name', 'Branch A');
    }

    public function test_platform_admin_can_view_any_tenant(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/organizations/tenants/{$this->tenantB->id}");

        $response->assertOk()->assertJsonPath('data.name', 'Tenant B');
    }

    public function test_row_level_security_blocks_cross_tenant_reads_at_the_database_level(): void
    {
        app(TenantContext::class)->set($this->tenantA->id);

        // Raw query builder, bypassing every Eloquent global scope, to prove
        // Postgres row-level security is the isolation backstop, not just app code.
        $branchIds = DB::table('branches')->pluck('id')->all();

        $this->assertContains($this->branchA->id, $branchIds);
        $this->assertNotContains($this->branchB->id, $branchIds);
    }

    public function test_row_level_security_allows_full_access_when_no_tenant_context_is_set(): void
    {
        app(TenantContext::class)->set(null);

        $branchIds = DB::table('branches')->pluck('id')->all();

        $this->assertContains($this->branchA->id, $branchIds);
        $this->assertContains($this->branchB->id, $branchIds);
    }
}
