<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyDigest extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $names  A few first names of new members (never photos or surnames).
     */
    public function __construct(public int $newPeople, public array $names, public string $state, public int $likesWaiting) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice(':count new person joined near you this week|:count new people joined near you this week', $this->newPeople))
            ->greeting(__('Hi :name,', ['name' => $notifiable->displayName()]))
            ->line(trans_choice(':count new member in :state joined :app this week.|:count new members in :state joined :app this week.', $this->newPeople, [
                'state' => $this->state,
                'app' => config('app.name'),
            ]));

        if ($this->names) {
            $mail->line(__('Say hi to :names and more.', ['names' => implode(', ', $this->names)]));
        }

        if ($this->likesWaiting) {
            $mail->line(trans_choice('You also have :count like waiting for you.|You also have :count likes waiting for you.', $this->likesWaiting));
        }

        return $mail
            ->action(__('See who’s new'), route('discover'))
            ->salutation(__('You can turn off this weekly email in Settings → Privacy & notifications.'));
    }
}
