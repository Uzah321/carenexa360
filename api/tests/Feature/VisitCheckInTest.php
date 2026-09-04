<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitCheckInTest extends TestCase
{
    use RefreshDatabase;

    // Client address vs. a point ~3m away (inside the 100m geofence) and
    // ~500m away (outside), precomputed via the same Haversine formula the
    // controller uses.
    protected const CLIENT_LAT = -17.8252000;

    protected const CLIENT_LNG = 31.0335000;

    protected const NEARBY_LAT = -17.8252300;

    protected const FAR_LAT = -17.8297000;

    protected function makeVisit(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);
        $serviceUser = ServiceUser::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'latitude' => self::CLIENT_LAT,
            'longitude' => self::CLIENT_LNG,
        ]);
        $visit = Visit::create([
            'tenant_id' => $tenant->id,
            'service_user_id' => $serviceUser->id,
            'carer_id' => $carer->id,
            'visit_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '09:30',
        ]);

        return compact('tenant', 'carer', 'serviceUser', 'visit');
    }

    public function test_check_in_succeeds_within_the_geofence(): void
    {
        ['carer' => $carer, 'visit' => $visit] = $this->makeVisit();

        $response = $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-in", [
            'latitude' => self::NEARBY_LAT,
            'longitude' => self::CLIENT_LNG,
            'accuracy' => 10,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->assertNotNull($visit->fresh()->check_in_at);
    }

    public function test_check_in_fails_outside_the_geofence_without_override(): void
    {
        ['carer' => $carer, 'visit' => $visit] = $this->makeVisit();

        $response = $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-in", [
            'latitude' => self::FAR_LAT,
            'longitude' => self::CLIENT_LNG,
            'accuracy' => 10,
        ]);

        $response->assertUnprocessable();
        $this->assertNull($visit->fresh()->check_in_at);
    }

    public function test_check_in_succeeds_outside_the_geofence_with_an_override_reason(): void
    {
        ['carer' => $carer, 'visit' => $visit] = $this->makeVisit();

        $response = $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-in", [
            'latitude' => self::FAR_LAT,
            'longitude' => self::CLIENT_LNG,
            'accuracy' => 10,
            'override_reason' => 'GPS signal was inaccurate near the building entrance.',
        ]);

        $response->assertOk();
        $this->assertNotNull($visit->fresh()->check_in_at);
        $this->assertSame($carer->id, $visit->fresh()->overridden_by);
    }

    public function test_a_wider_configured_geofence_radius_allows_a_check_in_that_would_otherwise_fail(): void
    {
        ['tenant' => $tenant, 'carer' => $carer, 'visit' => $visit] = $this->makeVisit();
        $tenant->update(['settings' => ['geofence_radius_meters' => 600]]);

        $response = $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-in", [
            'latitude' => self::FAR_LAT,
            'longitude' => self::CLIENT_LNG,
            'accuracy' => 10,
        ]);

        $response->assertOk();
        $this->assertNotNull($visit->fresh()->check_in_at);
    }

    public function test_check_out_completes_the_visit(): void
    {
        ['carer' => $carer, 'visit' => $visit] = $this->makeVisit();

        $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-in", [
            'latitude' => self::NEARBY_LAT,
            'longitude' => self::CLIENT_LNG,
        ])->assertOk();

        $response = $this->actingAs($carer)->postJson("/api/v1/visits/{$visit->id}/check-out", [
            'latitude' => self::NEARBY_LAT,
            'longitude' => self::CLIENT_LNG,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($visit->fresh()->check_out_at);
    }
}
