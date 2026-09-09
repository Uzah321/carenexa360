<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithVisits(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'country' => 'Zimbabwe']);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $carer = User::factory()->create(['tenant_id' => $tenant->id]);

        $stop1 = ServiceUser::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Ruth', 'last_name' => 'Chikafu',
            'latitude' => -17.8252, 'longitude' => 31.0335,
        ]);
        $stop2 = ServiceUser::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Josiah', 'last_name' => 'Ndlovu',
            'latitude' => -17.8292, 'longitude' => 31.0522,
        ]);

        Visit::create([
            'tenant_id' => $tenant->id, 'service_user_id' => $stop1->id, 'carer_id' => $carer->id,
            'visit_date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '09:30',
        ]);
        Visit::create([
            'tenant_id' => $tenant->id, 'service_user_id' => $stop2->id, 'carer_id' => $carer->id,
            'visit_date' => '2026-09-10', 'start_time' => '10:00', 'end_time' => '10:30',
        ]);

        return compact('tenant', 'manager', 'carer');
    }

    public function test_route_is_road_snapped_when_osrm_succeeds(): void
    {
        ['manager' => $manager, 'carer' => $carer] = $this->makeTenantWithVisits();

        Http::fake([
            '*/route/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['geometry' => ['coordinates' => [
                        [31.0335, -17.8252],
                        [31.0400, -17.8270],
                        [31.0522, -17.8292],
                    ]]],
                ],
            ]),
        ]);

        $response = $this->actingAs($manager)->getJson("/api/v1/visits/route?carer_id={$carer->id}&date=2026-09-10");

        $response->assertOk()
            ->assertJsonCount(2, 'stops')
            ->assertJsonPath('stops.0.label', 'Ruth Chikafu')
            ->assertJsonPath('route', [
                ['latitude' => -17.8252, 'longitude' => 31.0335],
                ['latitude' => -17.8270, 'longitude' => 31.0400],
                ['latitude' => -17.8292, 'longitude' => 31.0522],
            ]);
    }

    public function test_route_falls_back_to_null_when_osrm_is_unreachable(): void
    {
        ['manager' => $manager, 'carer' => $carer] = $this->makeTenantWithVisits();

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('connection refused'));

        $response = $this->actingAs($manager)->getJson("/api/v1/visits/route?carer_id={$carer->id}&date=2026-09-10");

        $response->assertOk()
            ->assertJsonCount(2, 'stops')
            ->assertJsonPath('route', null);
    }
}
