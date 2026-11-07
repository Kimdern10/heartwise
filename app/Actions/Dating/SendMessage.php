<?php

namespace App\Actions\Dating;

use App\Exceptions\DatingActionException;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\MessageScreener;
use App\Support\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SendMessage
{
    public function __construct(private MessageScreener $screener, private Notifier $notifier) {}

    public function handle(User $sender, UserMatch $match, string $body): Message
    {
        $body = trim($body);

        if (! $match->includes($sender)) {
            throw new DatingActionException('You can only message your matches.');
        }

        $receiver = $match->otherUser($sender);

        if (! $match->isActive() || ! $receiver->isActive() || $sender->hasBlockBetween($receiver)) {
            throw new DatingActionException('This conversation is no longer available.');
        }

        if ($body === '' || mb_strlen($body) > 1000) {
            throw new DatingActionException('Messages must be between 1 and 1,000 characters.');
        }

        $this->checkLimits($sender);

        $message = $match->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
            'flag' => $this->isCopyPasted($sender, $match, $body) ? MessageScreener::SPAM : $this->screener->flag($body),
        ]);

        $match->forceFill(['last_message_at' => $message->created_at])->save();

        $this->notifier->message($receiver, $sender, $match, $body);

        return $message;
    }

    /**
     * A voice note (up to a minute). Audio can't be screened like text, so it
     * goes through the same match, block and rate-limit checks, and stays
     * reportable like any message.
     */
    public function handleVoice(User $sender, UserMatch $match, UploadedFile $audio, int $seconds): Message
    {
        if (! $match->includes($sender)) {
            throw new DatingActionException('You can only message your matches.');
        }

        $receiver = $match->otherUser($sender);

        if (! $match->isActive() || ! $receiver->isActive() || $sender->hasBlockBetween($receiver)) {
            throw new DatingActionException('This conversation is no longer available.');
        }

        if ($seconds < 1 || $seconds > (int) config('dating.limits.voice_seconds', 60)) {
            throw new DatingActionException('Voice notes can be up to a minute long.');
        }

        $this->checkLimits($sender);

        $extension = match (true) {
            str_contains((string) $audio->getMimeType(), 'ogg') => 'ogg',
            str_contains((string) $audio->getMimeType(), 'mp4'), str_contains((string) $audio->getMimeType(), 'aac') => 'm4a',
            str_contains((string) $audio->getMimeType(), 'mpeg') => 'mp3',
            str_contains((string) $audio->getMimeType(), 'wav') => 'wav',
            default => 'webm',
        };

        $path = $audio->storeAs('voice/'.$match->id, Str::random(32).'.'.$extension, 'local');

        if ($path === false) {
            throw new DatingActionException('The voice note could not be saved. Please try again.');
        }

        $message = $match->messages()->create([
            'sender_id' => $sender->id,
            'body' => __('🎤 Voice message'),
            'audio_path' => $path,
            'audio_seconds' => $seconds,
        ]);

        $match->forceFill(['last_message_at' => $message->created_at])->save();

        $this->notifier->message($receiver, $sender, $match, __('🎤 Voice message'));

        return $message;
    }

    private function checkLimits(User $sender): void
    {
        if (! RateLimiter::attempt('messages:'.$sender->id, config('dating.limits.messages_per_minute'), fn () => true)) {
            throw new DatingActionException('You are sending messages too quickly. Please wait a minute.');
        }

        if ($sender->created_at?->gt(now()->subDays(7))) {
            $sentToday = Message::query()
                ->where('sender_id', $sender->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            if ($sentToday >= config('dating.limits.messages_per_day_new_account')) {
                throw new DatingActionException('New accounts have a daily message limit. Please try again tomorrow.');
            }
        }
    }

    /**
     * The same text sent to three or more other matches within an hour looks like spam.
     */
    private function isCopyPasted(User $sender, UserMatch $match, string $body): bool
    {
        if (mb_strlen($body) < 20) {
            return false;
        }

        return Message::query()
            ->where('sender_id', $sender->id)
            ->where('match_id', '!=', $match->id)
            ->where('body', $body)
            ->where('created_at', '>=', now()->subHour())
            ->distinct()
            ->count('match_id') >= 3;
    }
}
