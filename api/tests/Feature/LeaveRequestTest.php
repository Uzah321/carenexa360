<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_view_their_own_leave_request(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/leave-requests', [
            'type' => 'annual',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->actingAs($user)
            ->getJson('/api/v1/leave-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_cannot_approve_their_own_leave_request(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $leaveRequest = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'annual',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/leave-requests/{$leaveRequest->id}", ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_an_hr_officer_can_approve_anyones_leave_request(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $hrOfficer = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'HR Officer', 'tenant_id' => $tenant->id])->firstOrFail();
        $hrOfficer->assignRole($role);

        $leaveRequest = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'type' => 'sick',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($hrOfficer)->patchJson("/api/v1/leave-requests/{$leaveRequest->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $hrOfficer->id);
    }

    public function test_a_user_can_cancel_their_own_pending_request_but_not_an_approved_one(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $pending = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'annual',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/leave-requests/{$pending->id}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $approved = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'annual',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/leave-requests/{$approved->id}", ['status' => 'cancelled'])
            ->assertUnprocessable();
    }
}
