<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Rostering\Models\Shift;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayrollAdmin(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'HR Officer', 'tenant_id' => $tenant->id])->firstOrFail();
        $admin->assignRole($role);

        return compact('tenant', 'admin');
    }

    public function test_generating_payslips_sums_completed_visit_and_shift_hours_at_the_staff_rate(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->makePayrollAdmin();

        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        StaffProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'hourly_rate' => 15,
        ]);

        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        // 2 scheduled hours, completed, no check-in/out -> falls back to scheduled duration.
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-03',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'status' => 'completed',
        ]);

        // A completed 8-hour shift.
        Shift::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'shift_date' => '2026-09-04',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'completed',
        ]);

        $payPeriod = PayPeriod::create([
            'tenant_id' => $tenant->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/pay-periods/{$payPeriod->id}/generate-payslips");

        $response->assertOk();
        $payslip = Payslip::where('pay_period_id', $payPeriod->id)->where('user_id', $carer->id)->firstOrFail();
        $this->assertEquals(10, $payslip->regular_hours);
        $this->assertEquals(150, $payslip->gross_pay);
        $this->assertEquals(150, $payslip->net_pay);
    }

    public function test_regenerating_a_pay_period_does_not_overwrite_a_finalized_payslip(): void
    {
        ['tenant' => $tenant, 'admin' => $admin] = $this->makePayrollAdmin();

        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        StaffProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'hourly_rate' => 20,
        ]);

        $payPeriod = PayPeriod::create([
            'tenant_id' => $tenant->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
        ]);

        $payslip = Payslip::create([
            'tenant_id' => $tenant->id,
            'pay_period_id' => $payPeriod->id,
            'user_id' => $carer->id,
            'regular_hours' => 40,
            'gross_pay' => 800,
            'deductions' => 100,
            'net_pay' => 700,
            'status' => 'finalized',
        ]);

        $this->actingAs($admin)->postJson("/api/v1/pay-periods/{$payPeriod->id}/generate-payslips")->assertOk();

        $this->assertEquals(700, $payslip->fresh()->net_pay);
        $this->assertEquals('finalized', $payslip->fresh()->status);
    }

    public function test_staff_member_can_view_their_own_payslip_but_not_list_everyones(): void
    {
        ['tenant' => $tenant] = $this->makePayrollAdmin();
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $payPeriod = PayPeriod::create(['tenant_id' => $tenant->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-07']);
        $payslip = Payslip::create([
            'tenant_id' => $tenant->id,
            'pay_period_id' => $payPeriod->id,
            'user_id' => $carer->id,
            'regular_hours' => 10,
            'gross_pay' => 100,
            'net_pay' => 100,
        ]);

        $this->actingAs($carer)
            ->getJson("/api/v1/payslips/{$payslip->id}")
            ->assertOk()
            ->assertJsonPath('data.user_id', $carer->id);

        // A non-payroll-role user's index only ever returns their own.
        $response = $this->actingAs($carer)->getJson('/api/v1/payslips');
        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
