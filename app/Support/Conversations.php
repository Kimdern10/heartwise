<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Conversations
{
    /**
     * A member's matches, hiding blocked, banned or suspended people.
     *
     * @return Collection<int, UserMatch>
     */
    public static function for(User $user): Collection
    {
        $blocked = $user->blockedUserIds();

        return UserMatch::query()
            ->involving($user)
            ->whereNotIn('user_one_id', $blocked)
            ->whereNotIn('user_two_id', $blocked)
            ->whereHas('userOne', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('userTwo', fn (Builder $q) => $q->where('status', 'active'))
            ->with(['userOne.profile', 'userOne.approvedPhotos', 'userTwo.profile', 'userTwo.approvedPhotos', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get();
    }
}
