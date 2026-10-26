<?php

namespace App\Support;

class Geo
{
    /** Small margin (degrees) so people near a border are not rejected. */
    private const MARGIN = 0.25;

    public static function isInState(float $lat, float $lng, string $state): bool
    {
        $box = config('us_state_bounds.'.$state);

        if (! $box) {
            return false;
        }

        [$minLat, $maxLat, $minLng, $maxLng] = $box;

        return $lat >= $minLat - self::MARGIN && $lat <= $maxLat + self::MARGIN
            && $lng >= $minLng - self::MARGIN && $lng <= $maxLng + self::MARGIN;
    }

    /**
     * Best guess of the US state for a point (smallest matching box wins,
     * which resolves most border overlaps). Null when outside the US.
     */
    public static function stateFor(float $lat, float $lng): ?string
    {
        $best = null;
        $bestArea = INF;

        foreach (config('us_state_bounds') as $code => [$minLat, $maxLat, $minLng, $maxLng]) {
            if ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng) {
                $area = ($maxLat - $minLat) * ($maxLng - $minLng);

                if ($area < $bestArea) {
                    [$best, $bestArea] = [$code, $area];
                }
            }
        }

        return $best;
    }

    public static function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 3958.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
