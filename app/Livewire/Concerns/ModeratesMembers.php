<?php

namespace App\Livewire\Concerns;

use App\Actions\Admin\ModerateUser;
use App\Exceptions\DatingActionException;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;

/**
 * Suspend / ban / reactivate actions shared by admin pages.
 */
trait ModeratesMembers
{
    public function suspend(int $userId, int $days): void
    {
        $this->moderate(fn (ModerateUser $m, User $u) => $m->suspend(Auth::user(), $u, in_array($days, [7, 30], true) ? $days : 7, __('Community guidelines')),
            $userId, __('Member suspended.'));
    }

    public function ban(int $userId): void
    {
        $this->moderate(fn (ModerateUser $m, User $u) => $m->ban(Auth::user(), $u, __('Community guidelines')),
            $userId, __('Member banned.'));
    }

    public function reactivate(int $userId): void
    {
        $this->moderate(fn (ModerateUser $m, User $u) => $m->reactivate($u), $userId, __('Member reactivated.'));
    }

    private function moderate(callable $action, int $userId, string $done): void
    {
        abort_unless(Auth::user()?->is_admin, 403);

        try {
            $action(app(ModerateUser::class), User::findOrFail($userId));
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->afterModeration();

        Flux::toast(variant: 'success', text: $done);
    }

    protected function afterModeration(): void
    {
        //
    }
}
