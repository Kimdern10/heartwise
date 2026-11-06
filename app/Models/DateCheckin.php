<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member shares their date plans with a trusted friend. If they don't tap
 * "I'm safe" within 3 hours of the start time, the friend gets an alert.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $match_id
 * @property string $match_name
 * @property Carbon $meeting_at
 * @property string $timezone
 * @property string $place
 * @property string $friend_name
 * @property string $friend_email
 * @property string|null $note
 * @property Carbon|null $safe_at
 * @property Carbon|null $alert_sent_at
 * @property-read User $user
 */
#[Fillable(['match_id', 'match_name', 'meeting_at', 'timezone', 'place', 'friend_name', 'friend_email', 'note'])]
class DateCheckin extends Model
{
    public const ALERT_AFTER_HOURS = 3;

    protected function casts(): array
    {
        return [
            'meeting_at' => 'datetime',
            'safe_at' => 'datetime',
            'alert_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alertDueAt(): Carbon
    {
        return $this->meeting_at->copy()->addHours(self::ALERT_AFTER_HOURS);
    }
}
