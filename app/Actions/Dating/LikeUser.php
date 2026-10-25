<?php

namespace App\Actions\Dating;

use App\Exceptions\DatingActionException;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class LikeUser
{
    /**
     * Like (or pass on) someone. Returns the match when the like is mutual.
     */
    public function handle(User $user, User $target, bool $liked = true): ?UserMatch
    {
        if ($user->is($target)) {
            throw new DatingActionException('You cannot like yourself.');
        }

        if (! User::query()->discoverable()->whereKey($target->id)->exists() || $user->hasBlockBetween($target)) {
            throw new DatingActionException('This profile is not available.');
        }

        if ($liked && ! User::query()->discoverable()->whereKey($user->id)->exists()) {
            throw new DatingActionException('Your profile needs an approved photo and must be visible before you can like people.');
        }

        if ($liked) {
            $this->checkLimits($user);
        }

        return DB::transaction(function () use ($user, $target, $liked) {
            Like::query()->updateOrCreate(
                ['user_id' => $user->id, 'target_id' => $target->id],
                ['liked' => $liked],
            );

            if (! $liked) {
                return null;
            }

            $likedBack = Like::query()
                ->where('user_id', $target->id)
                ->where('target_id', $user->id)
                ->where('liked', true)
                ->exists();

            return $likedBack ? UserMatch::createBetween($user, $target) : null;
        });
    }

    private function checkLimits(User $user): void
    {
        $perMinute = config('dating.limits.likes_per_minute');

        if (! RateLimiter::attempt('likes:'.$user->id, $perMinute, fn () => true)) {
            throw new DatingActionException('Slow down a little — try again in a minute.');
        }

        $today = Like::query()
            ->where('user_id', $user->id)
            ->where('liked', true)
            ->where('updated_at', '>=', now()->startOfDay())
            ->count();

        if ($today >= config('dating.limits.likes_per_day')) {
            throw new DatingActionException('You have used all your likes for today. They reset at midnight.');
        }
    }
}
