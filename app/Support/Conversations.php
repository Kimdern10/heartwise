<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Conversations
{
    /**
     * A member's matches, hiding blocked, banned or suspended people,
     * newest conversation first. Optionally filtered by the other person's name.
     *
     * @return Builder<UserMatch>
     */
    public static function query(User $user, string $search = ''): Builder
    {
        $blocked = $user->blockedUserIds();
        $search = trim($search);

        return UserMatch::query()
            ->involving($user)
            ->active()
            ->whereNotIn('user_one_id', $blocked)
            ->whereNotIn('user_two_id', $blocked)
            ->whereHas('userOne', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('userTwo', fn (Builder $q) => $q->where('status', 'active'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('user_one_id', $user->id)
                    ->whereHas('userTwo.profile', fn (Builder $p) => $p->where('display_name', 'like', '%'.$search.'%')))
                ->orWhere(fn (Builder $q) => $q->where('user_two_id', $user->id)
                    ->whereHas('userOne.profile', fn (Builder $p) => $p->where('display_name', 'like', '%'.$search.'%')))))
            ->with(['userOne.profile', 'userOne.approvedPhotos', 'userTwo.profile', 'userTwo.approvedPhotos', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, UserMatch>
     */
    public static function for(User $user): Collection
    {
        return self::query($user)->get();
    }
}
