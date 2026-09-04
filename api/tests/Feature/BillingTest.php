<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Billing\Models\Funder;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeFinanceUser(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Finance Officer', 'tenant_id' => $tenant->id])->firstOrFail();
        $user->assignRole($role);

        return compact('tenant', 'user');
    }

    public function test_finance_officer_can_create_a_funder(): void
    {
        ['user' => $user] = $this->makeFinanceUser();

        $response = $this->actingAs($user)->postJson('/api/v1/funders', [
            'name' => 'City Council',
            'type' => 'local_authority',
            'default_hourly_rate' => 18.5,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'City Council');
    }

    public function test_a_user_without_finance_role_cannot_create_a_funder(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)->postJson('/api/v1/funders', [
            'name' => 'City Council',
            'type' => 'local_authority',
        ])->assertForbidden();
    }

    public function test_generating_an_invoice_only_bills_completed_visits_in_range(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->makeFinanceUser();
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        // In range, completed — should be billed.
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-09-05',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'status' => 'completed',
        ]);

        // In range but not completed — should be excluded.
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-09-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);

        // Outside the billing range — should be excluded.
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-09-20',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/invoices/generate', [
            'service_user_id' => $serviceUser->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-10',
            'hourly_rate' => 20,
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.line_items')
            ->assertJsonPath('data.subtotal', '40.00')
            ->assertJsonPath('data.total', '40.00');
    }

    public function test_invoice_uses_actual_checkin_checkout_duration_when_available(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->makeFinanceUser();
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'visit_date' => '2026-09-05',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'status' => 'completed',
            'check_in_at' => '2026-09-05 09:05:00',
            'check_out_at' => '2026-09-05 10:35:00',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/invoices/generate', [
            'service_user_id' => $serviceUser->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-10',
            'hourly_rate' => 10,
        ]);

        // 1.5 actual hours * 10 = 15.00, not the scheduled 2 hours * 10 = 20.00.
        $response->assertCreated()->assertJsonPath('data.total', '15.00');
    }

    public function test_tenant_isolation_applies_to_funders(): void
    {
        ['user' => $userA] = $this->makeFinanceUser();
        $funderA = Funder::create(['tenant_id' => $userA->tenant_id, 'name' => 'Funder A', 'type' => 'private']);

        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $roleB = Role::where(['name' => 'Finance Officer', 'tenant_id' => $tenantB->id])->firstOrFail();
        $userB->assignRole($roleB);

        $this->actingAs($userB)
            ->getJson("/api/v1/funders/{$funderA->id}")
            ->assertForbidden();
    }
}
