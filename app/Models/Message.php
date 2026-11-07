<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property int $sender_id
 * @property string $body
 * @property string|null $audio_path
 * @property int|null $audio_seconds
 * @property string|null $flag
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property-read UserMatch $match
 * @property-read User $sender
 */
#[Fillable(['match_id', 'sender_id', 'body', 'audio_path', 'audio_seconds', 'flag'])]
class Message extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'audio_seconds' => 'integer'];
    }

    public function isVoice(): bool
    {
        return $this->audio_path !== null;
    }

    /** @return BelongsTo<UserMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
