<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(Tenant $tenant, string $roleName): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        StaffProfile::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'employment_status' => 'active']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    protected function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
    }

    public function test_an_admin_can_deactivate_a_staff_account_and_it_can_no_longer_sign_in(): void
    {
        $tenant = $this->tenant();
        $admin = $this->makeUser($tenant, 'Organization Admin');
        $carer = $this->makeUser($tenant, 'Carer / Support Worker');

        $this->actingAs($admin)
            ->patchJson("/api/v1/user-roles/{$carer->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSame('inactive', $carer->fresh()->staffProfile->employment_status);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $carer = $this->makeUser($this->tenant(), 'Carer / Support Worker');
        $carer->update(['status' => 'inactive']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', ['email' => $carer->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_a_deactivated_account_can_be_reactivated(): void
    {
        $tenant = $this->tenant();
        $admin = $this->makeUser($tenant, 'Organization Admin');
        $carer = $this->makeUser($tenant, 'Carer / Support Worker');
        $staffId = $carer->staffProfile->id;

        $this->actingAs($admin)->patchJson("/api/v1/staff/{$staffId}/deactivate")->assertOk();
        $this->actingAs($admin)
            ->patchJson("/api/v1/staff/{$staffId}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.employment_status', 'active');

        $this->assertSame('active', $carer->fresh()->status);
    }

    public function test_an_admin_can_delete_a_staff_account(): void
    {
        $tenant = $this->tenant();
        $admin = $this->makeUser($tenant, 'Organization Admin');
        $carer = $this->makeUser($tenant, 'Carer / Support Worker');
        $email = $carer->email;

        $this->actingAs($admin)
            ->deleteJson("/api/v1/staff/{$carer->staffProfile->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $carer->id]);
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $carer->id]);
        // The email is free to be used for a new account.
        $this->assertDatabaseMissing('users', ['email' => $email]);

        $ids = collect($this->actingAs($admin)->getJson('/api/v1/user-roles')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($carer->id));
    }

    public function test_a_non_admin_cannot_deactivate_or_delete_an_account(): void
    {
        $tenant = $this->tenant();
        $manager = $this->makeUser($tenant, 'Care Manager');
        $carer = $this->makeUser($tenant, 'Carer / Support Worker');

        $this->actingAs($manager)->patchJson("/api/v1/staff/{$carer->staffProfile->id}/deactivate")->assertForbidden();
        $this->actingAs($manager)->deleteJson("/api/v1/staff/{$carer->staffProfile->id}")->assertForbidden();

        $this->assertSame('active', $carer->fresh()->status);
    }

    public function test_an_admin_cannot_deactivate_or_delete_their_own_account(): void
    {
        $tenant = $this->tenant();
        $admin = $this->makeUser($tenant, 'Organization Admin');

        $this->actingAs($admin)->patchJson("/api/v1/user-roles/{$admin->id}/deactivate")->assertStatus(422);
        $this->actingAs($admin)->deleteJson("/api/v1/user-roles/{$admin->id}")->assertStatus(422);
    }

    public function test_an_admin_cannot_remove_an_organization_owner(): void
    {
        $tenant = $this->tenant();
        $admin = $this->makeUser($tenant, 'Organization Admin');
        $owner = $this->makeUser($tenant, 'Organization Owner');

        $this->actingAs($admin)->deleteJson("/api/v1/user-roles/{$owner->id}")->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }
}
