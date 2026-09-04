<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_carer_can_read_but_not_post_an_announcement(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        Announcement::create([
            'tenant_id' => $tenant->id,
            'title' => 'Office closed Monday',
            'body' => 'The office will be closed for a public holiday.',
        ]);

        $this->actingAs($carer)
            ->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($carer)->postJson('/api/v1/announcements', [
            'title' => 'Unauthorized post',
            'body' => 'Should not work.',
        ])->assertForbidden();
    }

    public function test_a_care_manager_can_post_an_announcement(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Care Manager', 'tenant_id' => $tenant->id])->firstOrFail();
        $manager->assignRole($role);

        $response = $this->actingAs($manager)->postJson('/api/v1/announcements', [
            'title' => 'New rota system',
            'body' => 'We are rolling out a new rota system next week.',
            'pinned' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.pinned', true);
    }

    public function test_tenant_isolation_applies_to_announcements(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        Announcement::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A only',
            'body' => 'Should not be visible to tenant B.',
        ]);

        $response = $this->actingAs($userB)->getJson('/api/v1/announcements');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
