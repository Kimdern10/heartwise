<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One look at where a member is connecting from: IP location, VPN / data
 * centre flags, and whether their device timezone matches the IP.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $ip
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property string|null $ip_timezone
 * @property string|null $browser_timezone
 * @property string|null $isp
 * @property bool $is_vpn
 * @property bool $is_hosting
 * @property bool $timezone_mismatch
 * @property bool $blocked
 * @property string|null $reason
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'ip', 'country', 'region', 'city', 'ip_timezone', 'browser_timezone', 'isp',
    'is_vpn', 'is_hosting', 'timezone_mismatch', 'blocked', 'reason',
])]
class ConnectionCheck extends Model
{
    protected function casts(): array
    {
        return [
            'is_vpn' => 'boolean',
            'is_hosting' => 'boolean',
            'timezone_mismatch' => 'boolean',
            'blocked' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuspicious(): bool
    {
        return $this->is_vpn || $this->is_hosting || $this->timezone_mismatch || $this->blocked;
    }

    public function location(): string
    {
        return collect([$this->city, $this->region, $this->country])->filter()->implode(', ') ?: __('Unknown');
    }
}
