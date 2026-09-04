<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RosteringTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithRole(Tenant $tenant, string $roleName): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    public function test_tenant_user_can_create_a_shift(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeUserWithRole($tenant, 'Organization Admin');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($admin)->postJson('/api/v1/shifts', [
            'user_id' => $staff->id,
            'shift_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'shift_type' => 'day',
        ]);

        $response->assertCreated()->assertJsonPath('data.shift_type', 'day');
    }

    public function test_overlapping_shift_for_the_same_staff_member_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeUserWithRole($tenant, 'Organization Admin');
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->postJson('/api/v1/shifts', [
            'user_id' => $staff->id,
            'shift_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertCreated();

        $response = $this->actingAs($admin)->postJson('/api/v1/shifts', [
            'user_id' => $staff->id,
            'shift_date' => '2026-09-10',
            'start_time' => '15:00',
            'end_time' => '23:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_a_shift_overlapping_an_existing_visit_for_the_same_carer_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $admin = $this->makeUserWithRole($tenant, 'Organization Admin');
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id, 'first_name' => 'John', 'last_name' => 'Smith',
        ]);

        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/shifts', [
            'user_id' => $carer->id,
            'shift_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_a_carer_cannot_view_or_create_shifts(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');

        $this->actingAs($carer)->getJson('/api/v1/shifts')->assertForbidden();

        $this->actingAs($carer)->postJson('/api/v1/shifts', [
            'user_id' => $carer->id,
            'shift_date' => '2026-09-10',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertForbidden();
    }
}
