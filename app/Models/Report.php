<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $reporter_id
 * @property int $reported_id
 * @property int|null $message_id
 * @property ReportReason $reason
 * @property string|null $details
 * @property ReportStatus $status
 * @property string|null $resolution
 * @property Carbon $created_at
 * @property-read User|null $reporter
 * @property-read User $reported
 * @property-read Message|null $message
 */
#[Fillable(['reporter_id', 'reported_id', 'message_id', 'reason', 'details'])]
class Report extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function close(ReportStatus $status, User $moderator, string $resolution): void
    {
        $this->forceFill([
            'status' => $status,
            'resolution' => $resolution,
            'resolved_by' => $moderator->id,
            'resolved_at' => now(),
        ])->save();
    }
}
