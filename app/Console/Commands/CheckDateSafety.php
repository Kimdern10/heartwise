<?php

namespace App\Console\Commands;

use App\Models\DateCheckin;
use App\Notifications\DateCheckinFriendMail;
use App\Notifications\MemberAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Signature('app:check-date-safety')]
#[Description('Alert trusted friends when a member has not checked in after their date (runs every 5 minutes)')]
class CheckDateSafety extends Command
{
    public function handle(): int
    {
        $due = DateCheckin::query()
            ->whereNull('safe_at')
            ->whereNull('alert_sent_at')
            ->where('meeting_at', '<=', now()->subHours(DateCheckin::ALERT_AFTER_HOURS))
            ->where('meeting_at', '>=', now()->subDays(2))
            ->with('user.profile')
            ->get();

        foreach ($due as $checkin) {
            try {
                Notification::route('mail', [$checkin->friend_email => $checkin->friend_name])
                    ->notify(new DateCheckinFriendMail($checkin, 'alert'));
            } catch (Throwable $e) {
                report($e);
            }

            $checkin->forceFill(['alert_sent_at' => now()])->save();

            $checkin->user->notify(new MemberAlert(
                'checkin',
                __('We let :friend know you haven’t checked in', ['friend' => $checkin->friend_name]),
                __('Tap here to tell them you’re safe.'),
                route('dashboard'),
            ));
        }

        $this->components->info("Sent {$due->count()} safety alerts.");

        return self::SUCCESS;
    }
}
