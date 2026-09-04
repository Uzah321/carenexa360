<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsDashboardControllerTest extends TestCase
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

    public function test_a_plain_carer_cannot_view_the_operations_dashboard(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)->getJson('/api/v1/operations-dashboard')->assertForbidden();
    }

    public function test_headline_and_trends_reflect_real_data(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'status' => 'active']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'carer_id' => $carer->id, 'visit_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'completed']);
        Visit::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'carer_id' => null, 'visit_date' => now()->toDateString(), 'start_time' => '11:00', 'end_time' => '12:00', 'status' => 'scheduled']);

        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->toDateString(), 'issue_date' => now()->toDateString(), 'status' => 'sent', 'total' => 250.50]);

        Incident::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'type' => 'fall', 'severity' => 'medium', 'description' => 'Slipped.', 'status' => 'reported', 'reported_by' => $owner->id]);

        $response = $this->actingAs($owner)->getJson('/api/v1/operations-dashboard');

        $response->assertOk()
            ->assertJsonPath('headline.active_service_users', 1)
            ->assertJsonPath('headline.visits_this_month', 2)
            ->assertJsonPath('headline.revenue_this_month', 250.5)
            ->assertJsonPath('headline.open_incidents', 1)
            ->assertJsonPath('risk.open_incidents', 1)
            ->assertJsonCount(8, 'trends.weeks')
            ->assertJsonCount(8, 'trends.visits')
            ->assertJsonCount(8, 'trends.revenue')
            ->assertJsonCount(8, 'trends.rota_coverage_pct');

        // Both visits and the invoice happened this week — the last trend
        // bucket (current week) should reflect them.
        $lastWeekVisits = collect($response->json('trends.visits'))->last();
        $lastWeekRevenue = collect($response->json('trends.revenue'))->last();
        $lastWeekCoverage = collect($response->json('trends.rota_coverage_pct'))->last();
        $this->assertSame(2, $lastWeekVisits);
        $this->assertSame(250.5, $lastWeekRevenue);
        $this->assertSame(50, $lastWeekCoverage);
    }

    public function test_draft_and_cancelled_invoices_are_not_counted_as_revenue_or_outstanding(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'status' => 'active']);

        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->toDateString(), 'issue_date' => now()->toDateString(), 'status' => 'draft', 'total' => 500]);
        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->toDateString(), 'issue_date' => now()->toDateString(), 'status' => 'cancelled', 'total' => 300]);
        Invoice::create(['tenant_id' => $tenant->id, 'service_user_id' => $serviceUser->id, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->toDateString(), 'issue_date' => now()->toDateString(), 'status' => 'sent', 'total' => 100]);

        $response = $this->actingAs($owner)->getJson('/api/v1/operations-dashboard');

        $response->assertOk()
            ->assertJsonPath('headline.revenue_this_month', 100)
            ->assertJsonPath('headline.outstanding_invoices_total', 100);

        $lastWeekRevenue = collect($response->json('trends.revenue'))->last();
        $this->assertSame(100, $lastWeekRevenue);
    }

    public function test_branch_comparison_is_scoped_per_branch(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeOwner($tenant);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch B', 'country' => 'Zimbabwe']);

        ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu', 'status' => 'active']);
        ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'first_name' => 'Josiah', 'last_name' => 'Ndlovu', 'status' => 'active']);
        ServiceUser::create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'first_name' => 'Agnes', 'last_name' => 'Moyo', 'status' => 'active']);

        $response = $this->actingAs($owner)->getJson('/api/v1/operations-dashboard');

        $response->assertOk();
        $branches = collect($response->json('branches'))->keyBy('name');
        $this->assertSame(1, $branches['Branch A']['service_user_count']);
        $this->assertSame(2, $branches['Branch B']['service_user_count']);
    }

    public function test_tenant_isolation_on_headline_counts(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerA = $this->makeOwner($tenantA);

        ServiceUser::create(['tenant_id' => $tenantB->id, 'first_name' => 'Jane', 'last_name' => 'Doe', 'status' => 'active']);

        $response = $this->actingAs($ownerA)->getJson('/api/v1/operations-dashboard');

        $response->assertOk()->assertJsonPath('headline.active_service_users', 0);
    }
}
