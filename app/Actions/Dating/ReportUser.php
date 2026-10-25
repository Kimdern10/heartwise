<?php

namespace App\Actions\Dating;

use App\Enums\ReportReason;
use App\Exceptions\DatingActionException;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class ReportUser
{
    public function __construct(private BlockUser $blocker) {}

    public function handle(
        User $reporter,
        User $reported,
        ReportReason $reason,
        ?string $details = null,
        ?Message $message = null,
        bool $alsoBlock = true,
    ): Report {
        if ($reporter->is($reported)) {
            throw new DatingActionException('You cannot report yourself.');
        }

        if (! RateLimiter::attempt('reports:'.$reporter->id, config('dating.limits.reports_per_day'), fn () => true, 86400)) {
            throw new DatingActionException('You have sent many reports today. Our team will review them shortly.');
        }

        if ($message && ($message->sender_id !== $reported->id || ! $message->match->includes($reporter))) {
            $message = null;
        }

        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'message_id' => $message?->id,
            'reason' => $reason,
            'details' => $details ? mb_substr(trim($details), 0, 2000) : null,
        ]);

        if ($alsoBlock) {
            $this->blocker->handle($reporter, $reported);
        }

        return $report;
    }
}
