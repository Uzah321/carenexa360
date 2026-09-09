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
            ['latitude' => -17.8252, 'longitude' => 31.0335, 'accuracy' => 12.0, 'recorded_at' => '2026-09-08T10:00:00Z'],
            ['latitude' => -17.8292, 'longitude' => 31.0522, 'accuracy' => 12.0, 'recorded_at' => '2026-09-08T10:05:00Z'],
        ];
    }

    public function test_it_returns_the_matched_route_when_osrm_succeeds(): void
    {
        Http::fake([
            '*/match/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'matchings' => [
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

    public function test_it_concatenates_multiple_matchings_when_osrm_splits_the_trace(): void
    {
        // OSRM splits a noisy trace into disconnected legs when it isn't
        // confident enough to bridge a gap — the fix under test is that this
        // no longer collapses to nothing or forces a straight line through
        // the gap, it just joins the legs it *is* confident about.
        Http::fake([
            '*/match/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'matchings' => [
                    ['geometry' => ['coordinates' => [[31.0335, -17.8252], [31.0360, -17.8260]]]],
                    ['geometry' => ['coordinates' => [[31.0500, -17.8285], [31.0522, -17.8292]]]],
                ],
            ]),
        ]);

        $route = RoadSnapper::snap($this->points());

        $this->assertSame([
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8260, 'longitude' => 31.0360],
            ['latitude' => -17.8285, 'longitude' => 31.0500],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ], $route);
    }

    public function test_it_chunks_requests_to_stay_under_the_osrm_matching_size_limit(): void
    {
        $points = collect(range(0, 149))
            ->map(fn (int $i) => [
                'latitude' => -17.8252 + $i * 0.0001,
                'longitude' => 31.0335 + $i * 0.0001,
                'accuracy' => 10.0,
                'recorded_at' => now()->addSeconds($i * 25)->toIso8601String(),
            ])
            ->all();

        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;

            return Http::response([
                'code' => 'Ok',
                'matchings' => [
                    ['geometry' => ['coordinates' => [[31.0335, -17.8252], [31.0522, -17.8292]]]],
                ],
            ]);
        });

        $route = RoadSnapper::snap($points);

        $this->assertSame(2, $requestCount);
        $this->assertNotNull($route);
    }

    public function test_it_falls_back_to_raw_points_for_a_chunk_osrm_cannot_match(): void
    {
        Http::fake([
            '*/match/v1/driving/*' => Http::response(['code' => 'NoMatch'], 200),
        ]);

        $route = RoadSnapper::snap($this->points());

        $this->assertSame([
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ], $route);
    }

    public function test_it_falls_back_to_raw_points_when_osrm_is_unreachable(): void
    {
        // A transient OSRM outage degrades to the raw trail rather than
        // dropping the route entirely — snap() only returns null when there
        // aren't enough points to draw a line at all.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('connection refused'));

        $route = RoadSnapper::snap($this->points());

        $this->assertSame([
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ], $route);
    }

    public function test_it_returns_null_for_fewer_than_two_points(): void
    {
        Http::fake();

        $this->assertNull(RoadSnapper::snap([]));
        $this->assertNull(RoadSnapper::snap([['latitude' => -17.8, 'longitude' => 31.0]]));
        Http::assertNothingSent();
    }

    public function test_route_waypoints_returns_the_road_following_path_when_osrm_succeeds(): void
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

        $route = RoadSnapper::routeWaypoints($this->points());

        $this->assertSame([
            ['latitude' => -17.8252, 'longitude' => 31.0335],
            ['latitude' => -17.8270, 'longitude' => 31.0400],
            ['latitude' => -17.8292, 'longitude' => 31.0522],
        ], $route);
    }

    public function test_route_waypoints_returns_null_when_osrm_cannot_find_a_route(): void
    {
        Http::fake([
            '*/route/v1/driving/*' => Http::response(['code' => 'NoRoute'], 200),
        ]);

        $this->assertNull(RoadSnapper::routeWaypoints($this->points()));
    }

    public function test_route_waypoints_returns_null_when_osrm_is_unreachable(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('connection refused'));

        $this->assertNull(RoadSnapper::routeWaypoints($this->points()));
    }

    public function test_route_waypoints_returns_null_for_fewer_than_two_points(): void
    {
        Http::fake();

        $this->assertNull(RoadSnapper::routeWaypoints([]));
        $this->assertNull(RoadSnapper::routeWaypoints([['latitude' => -17.8, 'longitude' => 31.0]]));
        Http::assertNothingSent();
    }
}
