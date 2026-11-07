<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns what people type ("nyc", "brooklyn", "st paul") or where their phone
 * is (GPS) into one official city name ("New York", "Saint Paul"), so members
 * in the same city really end up in the same city and can match.
 *
 * Uses OpenStreetMap's free Nominatim service (no key needed). Answers are
 * cached for a long time, so each city is only looked up once. If the service
 * can't be reached, the city is kept as typed (tidied up) instead of failing.
 */
class CityResolver
{
    /** Looked up and not found. */
    public const NOT_FOUND = false;

    /**
     * Official city name for what the member typed, in the given state.
     *
     * @return string|false|null the official name, false when the place doesn't exist in that state,
     *                           or null when the lookup service isn't available
     */
    public static function normalize(string $city, string $state): string|false|null
    {
        $city = trim(preg_replace('/\s+/', ' ', $city));

        if ($city === '' || ! static::enabled()) {
            return null;
        }

        $stateName = config('dating.us_states.'.$state);
        $key = 'city:'.$state.':'.Str::lower($city);

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === '' ? self::NOT_FOUND : $cached;
        }

        $result = static::request('search', [
            'q' => "{$city}, {$stateName}, USA",
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 1,
            'countrycodes' => 'us',
            'layer' => 'address',
        ]);

        if ($result === null) {
            return null;
        }

        $place = static::cityFrom($result[0]['address'] ?? [], $state);
        Cache::put($key, $place ?? '', now()->addDays(90));

        return $place ?? self::NOT_FOUND;
    }

    /**
     * For forms: the city to save, or an error message when the place can't be found in that state.
     *
     * @return array{city: string, error: string|null}
     */
    public static function clean(string $city, string $state): array
    {
        $typed = Str::title(trim(preg_replace('/\s+/', ' ', $city)));
        $official = static::normalize($city, $state);

        if ($official === self::NOT_FOUND) {
            return ['city' => $typed, 'error' => __('We couldn’t find :city in :state. Check the spelling, or use the nearest town.', [
                'city' => $typed, 'state' => config('dating.us_states.'.$state),
            ])];
        }

        return ['city' => $official ?? $typed, 'error' => null];
    }

    /**
     * City and state for a GPS position.
     *
     * @return array{city: string, state: string}|null
     */
    public static function fromCoordinates(float $lat, float $lng): ?array
    {
        if (! static::enabled()) {
            return null;
        }

        // Rounded to ~1 km: enough to know the city, and the same key for neighbours.
        $lat = round($lat, 2);
        $lng = round($lng, 2);

        return Cache::remember("city-at:{$lat},{$lng}", now()->addDays(90), function () use ($lat, $lng) {
            $result = static::request('reverse', [
                'lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 10, 'addressdetails' => 1,
            ]);

            $address = $result['address'] ?? [];
            $state = Str::after((string) ($address['ISO3166-2-lvl4'] ?? ''), 'US-');

            if (! array_key_exists($state, config('dating.us_states'))) {
                return null;
            }

            $city = static::cityFrom($address, $state);

            return $city ? ['city' => $city, 'state' => $state] : null;
        });
    }

    public static function enabled(): bool
    {
        return (bool) config('services.geocoder.enabled');
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected static function cityFrom(array $address, string $state): ?string
    {
        if (($address['ISO3166-2-lvl4'] ?? null) !== 'US-'.$state) {
            return null;
        }

        // Boroughs and neighbourhoods roll up into their city (Brooklyn → New York).
        $name = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? $address['municipality'] ?? null;

        return $name ? trim(preg_replace('/^City of /i', '', (string) $name)) : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null null when the service could not be reached
     */
    protected static function request(string $endpoint, array $query): ?array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.geocoder.url'), '/'))
                ->withHeaders(['User-Agent' => config('app.name').'/1.0 (+'.config('app.url').')'])
                ->acceptJson()
                ->timeout(5)
                ->get($endpoint, $query + ['accept-language' => 'en']);

            return $response->successful() ? (array) $response->json() : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
