<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The phone/desktop notification copy of a MemberAlert, for members who
 * switched notifications on. Queued so the push services never slow a
 * like or a message down.
 */
class MemberAlertPush extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('icons/icon-192.png'))
            // One notification per chat/kind replaces the previous one instead of piling up.
            ->tag($this->kind === 'message' ? 'chat:'.$this->url : $this->kind)
            ->data(['url' => $this->url]);
    }
}
