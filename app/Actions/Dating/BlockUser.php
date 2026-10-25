<?php

namespace App\Actions\Dating;

use App\Exceptions\DatingActionException;
use App\Models\Block;
use App\Models\User;

class BlockUser
{
    /**
     * Blocking hides both people from each other everywhere and stops the chat.
     * The match and messages are kept (hidden) so moderators can still review reports.
     */
    public function handle(User $user, User $target): void
    {
        if ($user->is($target)) {
            throw new DatingActionException('You cannot block yourself.');
        }

        Block::query()->firstOrCreate(['user_id' => $user->id, 'blocked_id' => $target->id]);
    }

    public function unblock(User $user, User $target): void
    {
        Block::query()->where('user_id', $user->id)->where('blocked_id', $target->id)->delete();
    }
}
