<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A member notification (new match, message or like). Always shown in the
 * bell; also emailed when App\Support\Notifier decides it is worth it.
 */
class MemberAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public bool $withEmail = false,
        /** @var list<string> */
        public array $channels = ['database'],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting($this->title)
            ->line($this->body)
            ->action(__('Open :app', ['app' => config('app.name')]), $this->url)
            ->line(__('Never send money or gift cards to someone you met online.'))
            ->salutation(__('You can turn these emails off in Settings → Notifications.'));
    }
}
