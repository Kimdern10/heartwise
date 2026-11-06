<?php

namespace App\Notifications;

use App\Models\DateCheckin;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the member's trusted friend: the date plan, a "they're safe"
 * update, or an alert when they haven't checked in.
 */
class DateCheckinFriendMail extends Notification
{
    use Queueable;

    public function __construct(public DateCheckin $checkin, public string $kind) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->checkin;
        $member = $c->user->displayName();
        // Show times in the member's own timezone (stored when they made the plan).
        $when = $c->meeting_at->copy()->setTimezone($c->timezone)->format('l, M j \a\t g:i A T');

        return match ($this->kind) {
            'plan' => (new MailMessage)
                ->subject(__(':name shared their date plans with you', ['name' => $member]))
                ->greeting(__('Hi :friend,', ['friend' => $c->friend_name]))
                ->line(__(':name is going on a date and asked you to be their safety contact.', ['name' => $member]))
                ->line(__('**Who:** :match', ['match' => $c->match_name]))
                ->line(__('**When:** :when', ['when' => $when]))
                ->line(__('**Where:** :place', ['place' => $c->place]))
                ->lineIf((bool) $c->note, __('**Note:** :note', ['note' => (string) $c->note]))
                ->line(__('If :name doesn’t tell us they’re safe by :time, we’ll email you again so you can check on them.', ['name' => $member, 'time' => $c->alertDueAt()->setTimezone($c->timezone)->format('g:i A T')]))
                ->salutation(__('— :app safety team', ['app' => config('app.name')])),

            'safe' => (new MailMessage)
                ->subject(__(':name is safe ✓', ['name' => $member]))
                ->greeting(__('Good news, :friend!', ['friend' => $c->friend_name]))
                ->line(__(':name checked in and says they’re safe after their date.', ['name' => $member]))
                ->salutation(__('— :app safety team', ['app' => config('app.name')])),

            default => (new MailMessage)
                ->subject(__('Please check on :name', ['name' => $member]))
                ->greeting(__('Hi :friend,', ['friend' => $c->friend_name]))
                ->line(__(':name had a date at :place (:when) and hasn’t checked in yet.', ['name' => $member, 'place' => $c->place, 'when' => $when]))
                ->line(__('It may be nothing — they might just have forgotten — but please try to reach them.'))
                ->line(__('If you believe they are in danger, call 911.'))
                ->salutation(__('— :app safety team', ['app' => config('app.name')])),
        };
    }
}
