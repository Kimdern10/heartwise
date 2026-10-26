<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable site settings (key/value), cached.
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public const DEFAULTS = [
        // off | flag | block
        'vpn_policy' => 'flag',
        // Comma-separated ISO country codes that may not sign up or log in.
        'blocked_countries' => '',
        // Only allow connections from the US (plus allowed_countries).
        'us_only' => '0',
        'allowed_countries' => '',
        // Flag accounts whose computer clock is set to a non-US timezone.
        'flag_timezone_mismatch' => '1',
        // Comma-separated IPs: always blocked, or never blocked (e.g. the owner's own IP).
        'blocked_ips' => '',
        'allowed_ips' => '',
    ];

    public static function get(string $key): string
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return (string) ($all[$key] ?? self::DEFAULTS[$key] ?? '');
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }

    /**
     * @return list<string>
     */
    public static function list(string $key, bool $upper = true): array
    {
        return array_values(array_filter(array_map(
            fn ($c) => $upper ? strtoupper(trim($c)) : trim($c),
            explode(',', static::get($key)),
        )));
    }
}
