<?php

namespace App\Support;

use App\Models\ConnectionCheck;
use App\Models\Setting;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Works out where a visitor is really connecting from.
 *
 * A VPN hides the real IP address, so no website can read someone's true
 * location through one. What we can do — like large dating sites — is:
 *  1. detect that the IP belongs to a VPN, proxy or data centre (proxycheck.io), and
 *  2. compare the device's own timezone (which a VPN does not change) with
 *     the IP's location. A computer set to Africa/Lagos time on a "Texas" IP
 *     is a strong romance-scam signal.
 * Device GPS (location verification on the profile) is a third, separate check.
 */
class ConnectionInspector
{
    /**
     * @return array{blocked: bool, reason: string|null, check: ConnectionCheck|null}
     */
    public function inspect(string $ip, ?string $browserTimezone, ?User $user): array
    {
        $browserTimezone = $this->cleanTimezone($browserTimezone);
        // Settings changes bump policy_version, so new rules apply straight away.
        $key = 'conn:'.Setting::get('policy_version').'|'.$ip.'|'.$browserTimezone.'|'.($user?->id ?? 'guest');

        return Cache::remember($key, now()->addHours(6), function () use ($ip, $browserTimezone, $user) {
            $info = $this->lookup($ip);
            $mismatch = $this->timezoneMismatch($browserTimezone, $info['country']);

            [$blocked, $reason] = $this->decide($ip, $info, $mismatch);

            $suspicious = $info['vpn'] || $info['hosting'] || $mismatch || $blocked;

            // Keep a record for signed-in members (once per IP/timezone per day) and for anything blocked.
            $check = null;
            if ($user || $blocked) {
                $check = ConnectionCheck::query()->create([
                    'user_id' => $user?->id,
                    'ip' => $ip,
                    'country' => $info['country'],
                    'region' => $info['region'],
                    'city' => $info['city'],
                    'ip_timezone' => $info['timezone'],
                    'browser_timezone' => $browserTimezone,
                    'isp' => $info['isp'],
                    'is_vpn' => $info['vpn'],
                    'is_hosting' => $info['hosting'],
                    'timezone_mismatch' => $mismatch,
                    'blocked' => $blocked,
                    'reason' => $reason ?? ($suspicious ? $this->describe($info, $mismatch) : null),
                ]);
            }

            if ($user && $suspicious && ! $user->is_admin) {
                $user->forceFill(['flag_reason' => $reason ?? $this->describe($info, $mismatch)])->saveQuietly();
            }

            return ['blocked' => $blocked, 'reason' => $reason, 'check' => $check];
        });
    }

    /**
     * @param  array{country: string|null, vpn: bool, hosting: bool}  $info
     * @return array{0: bool, 1: string|null}
     */
    public function decide(string $ip, array $info, bool $mismatch): array
    {
        if (in_array($ip, Setting::list('allowed_ips', false), true)) {
            return [false, null];
        }

        if (in_array($ip, Setting::list('blocked_ips', false), true)) {
            return [true, 'ip'];
        }

        $country = $info['country'];

        if ($country && in_array($country, Setting::list('blocked_countries'), true)) {
            return [true, 'country'];
        }

        if ($country && Setting::get('us_only') === '1' && $country !== 'US' && ! in_array($country, Setting::list('allowed_countries'), true)) {
            return [true, 'country'];
        }

        if (Setting::get('vpn_policy') === 'block' && ($info['vpn'] || $info['hosting'])) {
            return [true, 'vpn'];
        }

        return [false, null];
    }

    /**
     * True when the device clock is set to a non-US timezone while the IP claims the US
     * (typical when someone abroad uses a US VPN).
     */
    public function timezoneMismatch(?string $browserTimezone, ?string $ipCountry): bool
    {
        if (! $browserTimezone || Setting::get('flag_timezone_mismatch') !== '1') {
            return false;
        }

        $usZones = Cache::rememberForever('us-timezones', fn () => DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US'));

        return ($ipCountry === 'US' || $ipCountry === null) && ! in_array($browserTimezone, $usZones, true) && $browserTimezone !== 'UTC';
    }

    /**
     * @return array{country: string|null, region: string|null, city: string|null, timezone: string|null, isp: string|null, vpn: bool, hosting: bool}
     */
    public function lookup(string $ip): array
    {
        $empty = ['country' => null, 'region' => null, 'city' => null, 'timezone' => null, 'isp' => null, 'vpn' => false, 'hosting' => false];

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty; // localhost / private network (e.g. local development)
        }

        if (config('services.ip_intel.driver') !== 'proxycheck') {
            return $empty;
        }

        return Cache::remember('ipintel:'.$ip, now()->addHours(12), function () use ($ip, $empty) {
            try {
                $response = Http::timeout(4)->get('https://proxycheck.io/v2/'.$ip, array_filter([
                    'vpn' => 1,
                    'asn' => 1,
                    'key' => config('services.ip_intel.proxycheck_key'),
                ]));

                // Read the key directly: an IP contains dots, which json() would treat as a path.
                $data = ($response->json() ?? [])[$ip] ?? null;

                if (! is_array($data)) {
                    return $empty;
                }

                $type = strtolower((string) ($data['type'] ?? ''));

                return [
                    'country' => isset($data['isocode']) ? strtoupper($data['isocode']) : null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'isp' => isset($data['provider']) ? mb_substr($data['provider'], 0, 120) : null,
                    'vpn' => ($data['proxy'] ?? 'no') === 'yes' || in_array($type, ['vpn', 'tor', 'socks', 'socks4', 'socks5', 'http', 'https', 'compromised server'], true),
                    'hosting' => $type === 'hosting',
                ];
            } catch (Throwable) {
                return $empty;
            }
        });
    }

    /**
     * @param  array{vpn: bool, hosting: bool, country: string|null}  $info
     */
    private function describe(array $info, bool $mismatch): string
    {
        return collect([
            $info['vpn'] ? 'VPN/proxy' : null,
            $info['hosting'] ? 'data centre IP' : null,
            $mismatch ? 'device timezone outside US' : null,
        ])->filter()->implode(', ');
    }

    private function cleanTimezone(?string $timezone): ?string
    {
        return $timezone && in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : null;
    }
}
