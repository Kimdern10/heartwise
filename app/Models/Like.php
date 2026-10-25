<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "like" (liked = true) or a "pass" (liked = false).
 *
 * @property int $id
 * @property int $user_id
 * @property int $target_id
 * @property bool $liked
 * @property-read User $user
 * @property-read User $target
 */
#[Fillable(['user_id', 'target_id', 'liked'])]
class Like extends Model
{
    protected function casts(): array
    {
        return ['liked' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
