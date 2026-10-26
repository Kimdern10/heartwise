<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "like" (liked = true) or a "pass" (liked = false). A like can point at a
 * specific photo or prompt and carry a short comment; a Spark is a stand-out like.
 *
 * @property int $id
 * @property int $user_id
 * @property int $target_id
 * @property bool $liked
 * @property string|null $comment
 * @property int|null $photo_id
 * @property int|null $prompt_id
 * @property bool $is_spark
 * @property-read User $user
 * @property-read User $target
 * @property-read Photo|null $photo
 * @property-read ProfilePrompt|null $prompt
 */
#[Fillable(['user_id', 'target_id', 'liked', 'comment', 'photo_id', 'prompt_id', 'is_spark'])]
class Like extends Model
{
    protected function casts(): array
    {
        return ['liked' => 'boolean', 'is_spark' => 'boolean'];
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

    /** @return BelongsTo<Photo, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /** @return BelongsTo<ProfilePrompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(ProfilePrompt::class);
    }
}
