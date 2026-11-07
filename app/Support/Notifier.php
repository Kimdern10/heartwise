<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserMatch;
use App\Notifications\MemberAlert;
use App\Notifications\MemberAlertEmail;
use App\Notifications\MemberAlertPush;
use Throwable;

/**
 * Sends member notifications. Everything goes to the bell; emails are sent
 * sparingly so members aren't spammed:
 *  - new match: always (if the member allows match emails)
 *  - new like: at most one email every 6 hours, and it never says who
 *  - new message: only when they've been away 10+ minutes, at most one an hour
 */
class Notifier
{
    /**
     * @param  bool  $alreadyShown  true for the person whose like just made the match: they saw the
     *                              celebration on screen, so the bell entry is marked read and no email is sent
     */
    public function match(User $to, User $other, UserMatch $match, bool $alreadyShown = false): void
    {
        $this->send($to, new MemberAlert(
            'match',
            __('It’s a match with :name! 🎉', ['name' => $other->displayName()]),
            __('You and :name liked each other. Say hello!', ['name' => $other->displayName()]),
            route('messages.show', $match),
            $to->notify_matches && ! $alreadyShown,
        ));

        if ($alreadyShown) {
            $to->unreadNotifications()->where('data->url', route('messages.show', $match))->update(['read_at' => now()]);
        }
    }

    public function like(User $to, bool $spark = false): void
    {
        $email = $to->notify_likes && (! $to->last_like_email_at || $to->last_like_email_at->lt(now()->subHours(6)));

        $this->send($to, new MemberAlert(
            'like',
            $spark ? __('Someone sent you a Spark ✨') : __('Someone new likes you'),
            __('Open your Likes to see who — and like them back to match.'),
            route('activity'),
            $email,
        ));

        if ($email) {
            $to->forceFill(['last_like_email_at' => now()])->saveQuietly();
        }
    }

    public function message(User $to, User $from, UserMatch $match, string $preview): void
    {
        // One bell entry per conversation: replace the previous unread one.
        $to->unreadNotifications()
            ->where('data->kind', 'message')
            ->where('data->url', route('messages.show', $match))
            ->delete();

        $away = ! $to->last_active_at || $to->last_active_at->lt(now()->subMinutes(10));
        $email = $to->notify_messages && $away
            && (! $to->last_message_email_at || $to->last_message_email_at->lt(now()->subHour()));

        $this->send($to, new MemberAlert(
            'message',
            __('New message from :name', ['name' => $from->displayName()]),
            mb_strimwidth($preview, 0, 90, '…'),
            route('messages.show', $match),
            $email,
        ));

        if ($email) {
            $to->forceFill(['last_message_email_at' => now()])->saveQuietly();
        }
    }

    private function send(User $to, MemberAlert $alert): void
    {
        $to->notify($alert);

        // Phone/desktop notification for members who turned them on (queued, never slows anything).
        if ($to->isActive() && $to->pushSubscriptions()->exists()) {
            try {
                $to->notify(new MemberAlertPush($alert->kind, $alert->title, $alert->body, $alert->url));
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (! $alert->withEmail || ! $to->isActive()) {
            return;
        }

        try {
            // Queued, so a slow mail server never makes the like or message wait.
            $to->notify(new MemberAlertEmail($alert->kind, $alert->title, $alert->body, $alert->url, true, ['mail']));
        } catch (Throwable $e) {
            // A mail problem must never stop a like or a message going through.
            report($e);
        }
    }
}
