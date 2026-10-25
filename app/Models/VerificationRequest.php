<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $selfie_path
 * @property string $gesture
 * @property ModerationStatus $status
 * @property string|null $notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property-read User $user
 */
#[Fillable(['selfie_path', 'gesture'])]
class VerificationRequest extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => ModerationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (VerificationRequest $request) {
            if ($request->selfie_path) {
                Storage::disk('local')->delete($request->selfie_path);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record the decision and delete the selfie: we only keep the result.
     */
    public function decide(ModerationStatus $status, User $moderator, ?string $notes = null): void
    {
        if ($this->selfie_path) {
            Storage::disk('local')->delete($this->selfie_path);
        }

        $this->forceFill([
            'status' => $status,
            'notes' => $notes,
            'selfie_path' => null,
            'reviewed_by' => $moderator->id,
            'reviewed_at' => now(),
        ])->save();

        if ($status === ModerationStatus::Approved) {
            $this->user->profile?->forceFill(['photo_verified_at' => now()])->save();
        }
    }
}
