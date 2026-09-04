<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Tracking\Models\CarerLocation;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CarerLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeManager(Tenant $tenant): User
    {
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::where(['name' => 'Care Manager', 'tenant_id' => $tenant->id])->firstOrFail();
        $manager->assignRole($role);

        return $manager;
    }

    public function test_a_carer_can_post_their_own_location(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($carer)->postJson('/api/v1/carer-locations', [
            'latitude' => -17.8292,
            'longitude' => 31.0522,
            'accuracy' => 15.5,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('carer_locations', [
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
        ]);
    }

    public function test_a_plain_carer_cannot_view_the_live_map(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)->getJson('/api/v1/carer-locations/live')->assertForbidden();
    }

    public function test_live_map_reports_checked_in_and_checked_out_carers_with_their_trail(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeManager($tenant);

        $carerA = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carer A']);
        $carerB = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carer B']);

        $serviceUser1 = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);
        $serviceUser2 = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Josiah', 'last_name' => 'Ndlovu']);

        // Carer A: checked in for work and currently on an in-progress visit.
        DutyPeriod::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carerA->id,
            'started_at' => now()->subHours(1),
            'start_lat' => -17.8292,
            'start_lng' => 31.0522,
        ]);
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser1->id,
            'carer_id' => $carerA->id,
            'visit_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'in_progress',
            'check_in_at' => now()->subMinutes(20),
        ]);

        // Carer B: checked out for the day after their only visit.
        DutyPeriod::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carerB->id,
            'started_at' => now()->subHours(3),
            'start_lat' => -17.8292,
            'start_lng' => 31.0522,
            'ended_at' => now()->subMinutes(30),
            'end_lat' => -17.8292,
            'end_lng' => 31.0522,
        ]);
        Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser2->id,
            'carer_id' => $carerB->id,
            'visit_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'completed',
            'check_in_at' => now()->subHours(2),
            'check_out_at' => now()->subHours(1),
        ]);

        CarerLocation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carerA->id,
            'latitude' => -17.8200,
            'longitude' => 31.0400,
            'recorded_at' => now()->subMinutes(15),
        ]);
        CarerLocation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carerA->id,
            'latitude' => -17.8210,
            'longitude' => 31.0410,
            'recorded_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($manager)->getJson('/api/v1/carer-locations/live');

        $response->assertOk()
            ->assertJsonPath('checked_in.count', 1)
            ->assertJsonPath('checked_in.items.0.name', 'Carer A')
            ->assertJsonPath('checked_out.count', 1)
            ->assertJsonPath('checked_out.items.0.name', 'Carer B')
            ->assertJsonCount(2, 'carers');

        $carerAPayload = collect($response->json('carers'))->firstWhere('user_id', $carerA->id);
        $this->assertTrue($carerAPayload['is_checked_in']);
        $this->assertCount(2, $carerAPayload['trail']);
    }

    public function test_live_map_can_be_filtered_by_branch(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = $this->makeManager($tenant);

        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch A', 'country' => 'Zimbabwe']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Branch B', 'country' => 'Zimbabwe']);

        $carerA = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carer A']);
        StaffProfile::create(['tenant_id' => $tenant->id, 'user_id' => $carerA->id, 'branch_id' => $branchA->id]);
        $carerB = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carer B']);
        StaffProfile::create(['tenant_id' => $tenant->id, 'user_id' => $carerB->id, 'branch_id' => $branchB->id]);

        $serviceUser = ServiceUser::create(['tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu']);

        foreach ([$carerA, $carerB] as $carer) {
            DutyPeriod::create([
                'tenant_id' => $tenant->id,
                'user_id' => $carer->id,
                'started_at' => now()->subHour(),
                'start_lat' => -17.8292,
                'start_lng' => 31.0522,
            ]);
            Visit::create([
                'tenant_id' => $tenant->id,
                'service_user_id' => $serviceUser->id,
                'carer_id' => $carer->id,
                'visit_date' => now()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'in_progress',
                'check_in_at' => now()->subMinutes(10),
            ]);
        }

        $response = $this->actingAs($manager)->getJson("/api/v1/carer-locations/live?branch_id={$branchA->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'carers')
            ->assertJsonPath('carers.0.name', 'Carer A');
    }
}
