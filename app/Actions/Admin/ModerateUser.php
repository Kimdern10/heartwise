<?php

namespace App\Actions\Admin;

use App\Enums\AccountStatus;
use App\Exceptions\DatingActionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ModerateUser
{
    public function suspend(User $moderator, User $user, int $days, string $reason): void
    {
        $this->guard($moderator, $user);

        $user->forceFill([
            'status' => AccountStatus::Suspended,
            'suspended_until' => now()->addDays($days),
            'status_reason' => $reason,
        ])->save();

        $this->endSessions($user);
    }

    public function ban(User $moderator, User $user, string $reason): void
    {
        $this->guard($moderator, $user);

        $user->forceFill([
            'status' => AccountStatus::Banned,
            'suspended_until' => null,
            'status_reason' => $reason,
        ])->save();

        $user->profile?->forceFill(['is_visible' => false])->save();

        $this->endSessions($user);
    }

    public function reactivate(User $user): void
    {
        $user->forceFill([
            'status' => AccountStatus::Active,
            'suspended_until' => null,
            'status_reason' => null,
        ])->save();
    }

    private function guard(User $moderator, User $user): void
    {
        if ($moderator->is($user) || $user->is_admin) {
            throw new DatingActionException('Admins cannot be suspended or banned here.');
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
