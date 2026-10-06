<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithRole(Tenant $tenant, string $roleName): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::where(['name' => $roleName, 'tenant_id' => $tenant->id])->firstOrFail());

        return $user;
    }

    public function test_creating_a_branch_records_an_audit_log_entry(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $this->actingAs($admin)->postJson("/api/v1/organizations/tenants/{$tenant->id}/branches", [
            'name' => 'Harare Branch',
            'country' => 'Zimbabwe',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Branch::class,
            'user_id' => $admin->id,
        ]);
    }

    public function test_updating_a_tenant_records_old_and_new_values(): void
    {
        $tenant = Tenant::create(['name' => 'Original Name', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);

        $tenant->update(['name' => 'Renamed Tenant']);

        $log = AuditLog::withoutTenantScope()
            ->where('auditable_type', Tenant::class)
            ->where('auditable_id', $tenant->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('Original Name', $log->old_values['name']);
        $this->assertSame('Renamed Tenant', $log->new_values['name']);
    }

    public function test_audit_log_is_scoped_to_the_current_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);

        $branchA = Branch::create([
            'tenant_id' => $tenantA->id, 'name' => 'Branch A', 'country' => 'Zimbabwe',
        ]);
        Branch::create([
            'tenant_id' => $tenantB->id, 'name' => 'Branch B', 'country' => 'UK',
        ]);

        $userA = $this->makeUserWithRole($tenantA, 'Organization Admin');

        $response = $this->actingAs($userA)->getJson('/api/v1/audit-log');

        $response->assertOk();

        $entityIds = collect($response->json('data'))
            ->where('auditable_type', Branch::class)
            ->pluck('auditable_id');

        $this->assertTrue($entityIds->contains($branchA->id));
    }

    public function test_a_carer_cannot_view_the_audit_log(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');

        $this->actingAs($carer)->getJson('/api/v1/audit-log')->assertForbidden();
    }

    public function test_an_entry_shows_what_it_was_done_to_and_each_change_with_names(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeUserWithRole($tenant, 'Organization Owner');
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tendai Moyo']);
        $client = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        $this->actingAs($owner)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36')
            ->patchJson("/api/v1/service-users/{$client->id}", ['care_manager_id' => $manager->id, 'phone' => '0771 000 000'])
            ->assertOk();

        $entry = collect($this->actingAs($owner)->getJson('/api/v1/audit-log?record_type=ServiceUser&action=updated')->assertOk()->json('data'))->first();
        $this->assertSame('Client', $entry['record_label']);
        $this->assertSame('Ruth Chikafu', $entry['record_name']);
        $this->assertSame("/service-users/{$client->id}", $entry['record_link']);
        $this->assertSame('Chrome on Windows', $entry['device']);

        $detail = $this->actingAs($owner)->getJson("/api/v1/audit-log/{$entry['id']}")->assertOk()->json('data');
        $changes = collect($detail['changes'])->keyBy('field');
        $this->assertSame('Care manager', $changes['care_manager_id']['label']);
        $this->assertNull($changes['care_manager_id']['before']);
        $this->assertSame('Tendai Moyo', $changes['care_manager_id']['after_display']);
        $this->assertSame('0771 000 000', $changes['phone']['after']);
        $this->assertSame($owner->email, $detail['user_email']);
        $this->assertContains('Organization Owner', $detail['user_roles']);
    }

    public function test_a_deleted_record_is_still_named_from_what_was_logged(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeUserWithRole($tenant, 'Organization Owner');
        $this->actingAs($owner);
        $requirement = ComplianceRequirement::create(['tenant_id' => $tenant->id, 'name' => 'Public Liability Insurance', 'status' => 'pending']);
        $requirement->forceDelete();

        $entry = collect($this->getJson('/api/v1/audit-log?action=deleted')->assertOk()->json('data'))->first();
        $this->assertSame('Compliance requirement', $entry['record_label']);
        $this->assertSame('Public Liability Insurance', $entry['record_name']);
        $this->assertNull($entry['record_link']);

        $detail = $this->getJson("/api/v1/audit-log/{$entry['id']}")->assertOk()->json('data');
        $this->assertSame('Public Liability Insurance', collect($detail['changes'])->firstWhere('field', 'name')['before']);
    }

    public function test_entries_can_be_filtered_by_person_and_date_and_other_tenants_are_hidden(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $owner = $this->makeUserWithRole($tenant, 'Organization Owner');
        $other = $this->makeUserWithRole($tenant, 'Organization Admin');
        $carer = $this->makeUserWithRole($tenant, 'Carer / Support Worker');
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'country' => 'UK']);
        $ownerB = $this->makeUserWithRole($tenantB, 'Organization Owner');
        $this->actingAs($other)->postJson('/api/v1/service-users', ['first_name' => 'Peter', 'last_name' => 'Sibanda'])->assertCreated();

        $this->actingAs($owner)->getJson("/api/v1/audit-log?user_id={$other->id}")
            ->assertOk()->assertJsonPath('data.0.record_name', 'Peter Sibanda');
        $this->actingAs($owner)->getJson('/api/v1/audit-log?user_id='.$other->id.'&to=2000-01-01')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/v1/audit-log?record_type=Nonsense')->assertUnprocessable();

        $entryA = AuditLog::withoutTenantScope()->where('tenant_id', $tenant->id)->first();
        // Withheld either way — 404 when tenant scoping hides it first, 403 from the explicit check.
        $this->assertContains($this->actingAs($ownerB)->getJson("/api/v1/audit-log/{$entryA->id}")->status(), [403, 404]);

        $this->assertContains($this->actingAs($carer)->getJson("/api/v1/audit-log/{$entryA->id}")->status(), [403, 404]);
    }

    public function test_entries_are_stamped_in_utc_whatever_the_database_timezone(): void
    {
        DB::statement("SET TIME ZONE 'Africa/Johannesburg'");
        $this->travelTo(now()->parse('2026-06-10 08:00:00', 'UTC'));
        $tenant = Tenant::create(['name' => 'Original', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $tenant->update(['name' => 'Renamed']);

        $log = AuditLog::withoutTenantScope()->where('auditable_type', Tenant::class)->where('action', 'updated')->firstOrFail();
        $this->assertSame('2026-06-10 08:00:00', $log->created_at->utc()->toDateTimeString());
    }
}
