<?php

namespace Tests\Feature;

use App\Modules\Tracking\Support\RoadSnapper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoadSnapperTest extends TestCase
{
    protected function points(): array
    {
        return [
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ];
    }

    public function test_it_returns_the_snapped_route_when_osrm_succeeds(): void
    {
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

        $route = RoadSnapper::snap($this->points());

        $this->assertSame([
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8270, 'longitude' => 31.0400],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ], $route);
    }

    public function test_it_returns_null_when_osrm_is_unreachable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('connection refused'));

        $this->assertNull(RoadSnapper::snap($this->points()));
    }

    public function test_it_returns_null_when_osrm_cannot_find_a_route(): void
    {
        Http::fake([
            '*/route/v1/driving/*' => Http::response(['code' => 'NoRoute'], 200),
        ]);

        $this->assertNull(RoadSnapper::snap($this->points()));
    }

    public function test_it_returns_null_for_fewer_than_two_points(): void
    {
        Http::fake();

        $this->assertNull(RoadSnapper::snap([]));
        $this->assertNull(RoadSnapper::snap([['latitude' => -17.8, 'longitude' => 31.0]]));
        Http::assertNothingSent();
    }
}
