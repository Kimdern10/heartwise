<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Who viewed me". One row per viewer/viewed pair; viewed_at is the latest visit.
 *
 * @property int $viewer_id
 * @property int $viewed_id
 * @property Carbon $viewed_at
 * @property-read User $viewer
 */
#[Fillable(['viewer_id', 'viewed_id', 'viewed_at'])]
class ProfileView extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public static function record(User $viewer, User $viewed): void
    {
        if ($viewer->is($viewed) || $viewer->is_admin || $viewer->profile?->browse_privately) {
            return;
        }

        static::query()->updateOrCreate(
            ['viewer_id' => $viewer->id, 'viewed_id' => $viewed->id],
            ['viewed_at' => now()],
        );
    }

    /** @return BelongsTo<User, $this> */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}
