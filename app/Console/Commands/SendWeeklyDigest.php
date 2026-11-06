<?php

namespace App\Console\Commands;

use App\Models\Like;
use App\Models\User;
use App\Notifications\WeeklyDigest;
use App\Support\Discovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:send-weekly-digest')]
#[Description('Email members about new people who joined in their state this week (runs every Monday)')]
class SendWeeklyDigest extends Command
{
    public function handle(): int
    {
        $sent = 0;

        User::query()
            ->where('notify_digest', true)
            ->where('is_admin', false)
            ->where('status', 'active')
            ->whereNotNull('onboarded_at')
            ->whereNotNull('email_verified_at')
            ->whereHas('profile')
            ->with('profile')
            ->chunkById(200, function ($members) use (&$sent) {
                foreach ($members as $member) {
                    $new = Discovery::for($member, ['state' => $member->profile->state])
                        ->where('users.created_at', '>=', now()->subWeek())
                        ->limit(50)
                        ->get();

                    if ($new->isEmpty()) {
                        continue;
                    }

                    $likes = Like::query()->where('target_id', $member->id)->where('liked', true)
                        ->whereNotIn('user_id', Like::query()->where('user_id', $member->id)->select('target_id'))
                        ->count();

                    try {
                        $member->notify(new WeeklyDigest(
                            $new->count(),
                            $new->take(3)->map->displayName()->all(),
                            config('dating.us_states.'.$member->profile->state),
                            $likes,
                        ));
                        $sent++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->components->info("Weekly digest sent to {$sent} members.");

        return self::SUCCESS;
    }
}
