<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Tracking\Models\DutyPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_carer_can_check_in_for_work(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($carer)->postJson('/api/v1/duty-periods', [
            'latitude' => -17.8292,
            'longitude' => 31.0522,
            'accuracy' => 12.0,
        ]);

        $response->assertCreated()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('duty_periods', [
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
        ]);
    }

    public function test_a_carer_cannot_check_in_twice_without_checking_out(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        DutyPeriod::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'started_at' => now()->subHour(),
            'start_lat' => -17.8292,
            'start_lng' => 31.0522,
        ]);

        $this->actingAs($carer)->postJson('/api/v1/duty-periods', [
            'latitude' => -17.8292,
            'longitude' => 31.0522,
        ])->assertUnprocessable();
    }

    public function test_a_carer_can_check_out(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $period = DutyPeriod::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carer->id,
            'started_at' => now()->subHours(8),
            'start_lat' => -17.8292,
            'start_lng' => 31.0522,
        ]);

        $response = $this->actingAs($carer)->postJson("/api/v1/duty-periods/{$period->id}/check-out", [
            'latitude' => -17.8300,
            'longitude' => 31.0530,
        ]);

        $response->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertNotNull($period->fresh()->ended_at);
    }

    public function test_a_carer_cannot_check_out_someone_elses_duty_period(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carerA = User::factory()->create(['tenant_id' => $tenant->id]);
        $carerB = User::factory()->create(['tenant_id' => $tenant->id]);
        $period = DutyPeriod::create([
            'tenant_id' => $tenant->id,
            'user_id' => $carerA->id,
            'started_at' => now()->subHours(2),
            'start_lat' => -17.8292,
            'start_lng' => 31.0522,
        ]);

        $this->actingAs($carerB)->postJson("/api/v1/duty-periods/{$period->id}/check-out", [
            'latitude' => -17.8292,
            'longitude' => 31.0522,
        ])->assertForbidden();
    }

    public function test_current_returns_null_when_not_on_duty(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($carer)
            ->getJson('/api/v1/duty-periods/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
