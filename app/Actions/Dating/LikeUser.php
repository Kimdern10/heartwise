<?php

namespace App\Actions\Dating;

use App\Enums\ModerationStatus;
use App\Exceptions\DatingActionException;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\MessageScreener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class LikeUser
{
    public function __construct(private MessageScreener $screener) {}

    /**
     * Like (or pass on) someone. A like can point at one of their photos or
     * prompts and carry a short comment; a Spark makes it stand out.
     * Returns the match when the like is mutual.
     */
    public function handle(
        User $user,
        User $target,
        bool $liked = true,
        ?string $comment = null,
        ?int $photoId = null,
        ?int $promptId = null,
        bool $spark = false,
    ): ?UserMatch {
        if ($user->is($target)) {
            throw new DatingActionException('You cannot like yourself.');
        }

        if (! User::query()->discoverable()->whereKey($target->id)->exists() || $user->hasBlockBetween($target)) {
            throw new DatingActionException('This profile is not available.');
        }

        if ($liked && ! User::query()->discoverable()->whereKey($user->id)->exists()) {
            throw new DatingActionException('Your profile needs an approved photo and must be visible before you can like people.');
        }

        $comment = $liked ? $this->cleanComment($comment) : null;

        if ($liked) {
            $this->checkLimits($user, $spark);
            $this->checkTargetItems($target, $photoId, $promptId);
        }

        return DB::transaction(function () use ($user, $target, $liked, $comment, $photoId, $promptId, $spark) {
            Like::query()->updateOrCreate(
                ['user_id' => $user->id, 'target_id' => $target->id],
                [
                    'liked' => $liked,
                    'comment' => $comment,
                    'photo_id' => $liked ? $photoId : null,
                    'prompt_id' => $liked ? $promptId : null,
                    'is_spark' => $liked && $spark,
                ],
            );

            if (! $liked) {
                return null;
            }

            $theirLike = Like::query()
                ->where('user_id', $target->id)
                ->where('target_id', $user->id)
                ->where('liked', true)
                ->first();

            if (! $theirLike) {
                return null;
            }

            $match = UserMatch::createBetween($user, $target);

            // Comments sent with the likes become the first messages of the chat.
            if ($match->wasRecentlyCreated) {
                foreach ([[$target, $theirLike->comment], [$user, $comment]] as [$sender, $text]) {
                    if ($text) {
                        $match->messages()->create(['sender_id' => $sender->id, 'body' => $text]);
                    }
                }

                if ($match->messages()->exists()) {
                    $match->forceFill(['last_message_at' => now()])->save();
                }
            }

            return $match;
        });
    }

    private function cleanComment(?string $comment): ?string
    {
        $comment = trim((string) $comment);

        if ($comment === '') {
            return null;
        }

        if (mb_strlen($comment) > 300) {
            throw new DatingActionException('Comments can be up to 300 characters.');
        }

        if ($this->screener->flag($comment)) {
            throw new DatingActionException('Keep your first comment friendly — no contact details, links, other apps or money talk.');
        }

        return $comment;
    }

    private function checkTargetItems(User $target, ?int $photoId, ?int $promptId): void
    {
        if ($photoId && ! $target->photos()->whereKey($photoId)->where('status', ModerationStatus::Approved)->exists()) {
            throw new DatingActionException('That photo is not available.');
        }

        if ($promptId && ! $target->prompts()->whereKey($promptId)->exists()) {
            throw new DatingActionException('That prompt is not available.');
        }
    }

    private function checkLimits(User $user, bool $spark): void
    {
        if ($spark && ! $user->hasSparkLeftToday()) {
            throw new DatingActionException('You have used today’s Spark. You get a new one tomorrow.');
        }

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
