<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Photos live on the private "local" disk and are served through
 * PhotoController so only allowed viewers can load them.
 *
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string $path_sm
 * @property int $position
 * @property ModerationStatus $status
 * @property string|null $rejection_reason
 * @property-read User $user
 */
#[Fillable(['path', 'path_sm', 'position'])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => ModerationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Photo $photo) {
            Storage::disk('local')->delete([$photo->path, $photo->path_sm]);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(bool $small = false): string
    {
        return route('photos.show', ['photo' => $this, 'size' => $small ? 'sm' : 'lg']);
    }
}
