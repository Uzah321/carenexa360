<?php

namespace App\Modules\Tracking\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a raw sequence of GPS pings into a road-following path via a
 * self-hosted OSRM instance (see scripts/setup-osrm.sh), rather than the
 * straight lines you'd get connecting sparse pings directly — a carer whose
 * phone only reported twice in an hour would otherwise draw a line straight
 * through whatever buildings sit between those two points.
 *
 * Never lets OSRM being down, slow, or unable to route (e.g. a ping outside
 * the loaded map region) break the Live Map — callers get null back and are
 * expected to fall back to the raw trail.
 */
class RoadSnapper
{
    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $points  In visit order.
     * @return array<int, array{latitude: float, longitude: float}>|null
     */
    public static function snap(array $points): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $coordinates = collect($points)
            ->map(fn (array $p) => "{$p['longitude']},{$p['latitude']}")
            ->implode(';');

        try {
            $response = Http::timeout(3)->get(
                rtrim((string) config('services.osrm.url'), '/')."/route/v1/driving/{$coordinates}",
                ['overview' => 'full', 'geometries' => 'geojson'],
            );

            if (! $response->successful() || $response->json('code') !== 'Ok') {
                return null;
            }

            $geometry = $response->json('routes.0.geometry.coordinates');
            if (! is_array($geometry) || count($geometry) < 2) {
                return null;
            }

            // GeoJSON coordinates are [lng, lat] — flip to match latitude/longitude
            // everywhere else in this app.
            return collect($geometry)
                ->map(fn (array $pair) => ['latitude' => $pair[1], 'longitude' => $pair[0]])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('OSRM road-snap failed, falling back to the raw trail.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
