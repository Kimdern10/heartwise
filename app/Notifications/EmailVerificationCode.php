<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__(':code is your :app verification code', ['code' => $this->code, 'app' => $app]))
            ->greeting(__('Welcome to :app!', ['app' => $app]))
            ->line(__('Enter this code to verify your email address:'))
            ->line('**'.implode(' ', str_split($this->code)).'**')
            ->line(__('The code expires in 15 minutes.'))
            ->line(__('If you did not create an account, you can ignore this email. We will never ask you for this code by phone or chat.'));
    }
}
