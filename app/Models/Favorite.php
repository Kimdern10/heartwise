<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $favorite_id
 * @property-read User $favorite
 */
#[Fillable(['user_id', 'favorite_id'])]
class Favorite extends Model
{
    /** @return BelongsTo<User, $this> */
    public function favorite(): BelongsTo
    {
        return $this->belongsTo(User::class, 'favorite_id');
    }
}
