<?php

namespace App\Livewire\Concerns;

use App\Actions\Admin\ModerateUser;
use App\Enums\Role;
use App\Exceptions\DatingActionException;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;

/**
 * Suspend / ban / reactivate / role actions shared by admin pages.
 * Moderators can suspend and reactivate; only admins can ban or change roles.
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
            $userId, __('Member banned.'), adminOnly: true);
    }

    public function reactivate(int $userId): void
    {
        $this->moderate(fn (ModerateUser $m, User $u) => $m->reactivate($u), $userId, __('Member reactivated.'));
    }

    /**
     * Admins give or take away staff roles (member / moderator / admin).
     */
    public function changeRole(int $userId, string $role): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $target = User::findOrFail($userId);
        $newRole = Role::tryFrom($role);

        if (! $newRole || $target->is(Auth::user())) {
            Flux::toast(variant: 'warning', text: __('You can’t change your own role.'));

            return;
        }

        if ($target->isAdmin() && $newRole !== Role::Admin && User::query()->where('is_admin', true)->count() <= 1) {
            Flux::toast(variant: 'warning', text: __('There must always be at least one admin.'));

            return;
        }

        $target->setRole($newRole);
        $this->afterModeration();

        Flux::toast(variant: 'success', text: __(':name is now a :role.', ['name' => $target->displayName(), 'role' => strtolower($newRole->label())]));
    }

    private function moderate(callable $action, int $userId, string $done, bool $adminOnly = false): void
    {
        abort_unless($adminOnly ? Auth::user()?->isAdmin() : Auth::user()?->isStaff(), 403);

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
