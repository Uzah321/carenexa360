<?php

namespace App\Modules\Tracking\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a raw sequence of GPS pings into a road-following path via a
 * self-hosted OSRM instance (see scripts/setup-osrm.sh), rather than the
 * straight lines you'd get connecting sparse pings directly — a carer whose
 * phone only reported twice in an hour would otherwise draw a line straight
 * through whatever buildings sit between those two points.
 *
 * Uses OSRM's `/match` (map-matching) service, not `/route`: `/route` treats
 * every ping as a waypoint the path *must* pass through in order, so a
 * cluster of noisy pings around a stationary carer (GPS jitter, no real
 * movement) forced it to route out to each jittered point and back —
 * drawing the small looping "boxes" this exists to avoid. `/match`
 * probabilistically snaps a noisy trace onto the road network instead,
 * using per-point accuracy and timestamps to tell real movement from drift.
 *
 * Never lets OSRM being down, slow, or unable to match (e.g. a ping outside
 * the loaded map region) break the Live Map — callers get null back and are
 * expected to fall back to the raw trail.
 */
class RoadSnapper
{
    /**
     * The osrm-routed container is started with `--max-matching-size 100`
     * (see docker-compose.yml) — a single /match request over more points
     * than that is rejected outright, so a long shift's trail is matched in
     * batches instead of one request.
     */
    private const CHUNK_SIZE = 100;

    /** Typical smartphone GPS accuracy (metres), used when a ping has none. */
    private const DEFAULT_RADIUS = 15.0;

    /**
     * @param  array<int, array{latitude: float, longitude: float, accuracy?: float|null, recorded_at?: mixed}>  $points  In visit order.
     * @return array<int, array{latitude: float, longitude: float}>|null
     */
    public static function snap(array $points): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $matched = collect($points)
            ->values()
            ->chunk(self::CHUNK_SIZE)
            ->flatMap(function ($chunk) {
                $chunkPoints = $chunk->values()->all();

                return self::matchChunk($chunkPoints) ?? collect($chunkPoints)
                    ->map(fn (array $p) => ['latitude' => $p['latitude'], 'longitude' => $p['longitude']])
                    ->all();
            })
            ->all();

        return count($matched) >= 2 ? $matched : null;
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float, accuracy?: float|null, recorded_at?: mixed}>  $points
     * @return array<int, array{latitude: float, longitude: float}>|null
     */
    private static function matchChunk(array $points): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $coordinates = collect($points)->map(fn (array $p) => "{$p['longitude']},{$p['latitude']}")->implode(';');
        $timestamps = collect($points)->map(fn (array $p) => self::timestampFor($p))->implode(';');
        $radiuses = collect($points)->map(fn (array $p) => self::radiusFor($p))->implode(';');

        try {
            $response = Http::timeout(3)->get(
                rtrim((string) config('services.osrm.url'), '/')."/match/v1/driving/{$coordinates}",
                [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'timestamps' => $timestamps,
                    'radiuses' => $radiuses,
                ],
            );

            if (! $response->successful() || $response->json('code') !== 'Ok') {
                return null;
            }

            $matchings = $response->json('matchings');
            if (! is_array($matchings) || count($matchings) === 0) {
                return null;
            }

            // A noisy trace can come back as several disconnected legs when
            // OSRM isn't confident enough to bridge a gap (e.g. a long
            // stationary pause) — concatenating them in order is still far
            // more precise than the old /route-based line through every raw
            // ping, and never loops back on itself the way that did.
            $coordinatePairs = collect($matchings)
                ->flatMap(fn (array $matching) => $matching['geometry']['coordinates'] ?? [])
                ->all();

            if (count($coordinatePairs) < 2) {
                return null;
            }

            // GeoJSON coordinates are [lng, lat] — flip to match latitude/longitude
            // everywhere else in this app.
            return collect($coordinatePairs)
                ->map(fn (array $pair) => ['latitude' => $pair[1], 'longitude' => $pair[0]])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('OSRM map-match failed for a chunk, falling back to its raw points.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function timestampFor(array $point): int
    {
        $recordedAt = $point['recorded_at'] ?? null;

        return $recordedAt ? Carbon::parse($recordedAt)->timestamp : Carbon::now()->timestamp;
    }

    private static function radiusFor(array $point): float
    {
        $accuracy = $point['accuracy'] ?? null;

        return $accuracy && $accuracy > 0 ? max(5.0, (float) $accuracy) : self::DEFAULT_RADIUS;
    }
}
