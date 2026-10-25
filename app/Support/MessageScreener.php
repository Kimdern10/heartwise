<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Basic scam/spam screening for chat messages. Flagged messages are still
 * delivered, but the receiver sees a safety warning and moderators can review them.
 */
class MessageScreener
{
    public const MONEY = 'money';

    public const OFF_PLATFORM = 'off_platform';

    public const LINK = 'link';

    public const CONTACT = 'contact';

    public const SPAM = 'spam';

    public function flag(string $body): ?string
    {
        $text = Str::lower($body);

        if ($this->containsAny($text, config('dating.scam_phrases'))) {
            return self::MONEY;
        }

        if ($this->containsAny($text, config('dating.off_platform_phrases'))) {
            return self::OFF_PLATFORM;
        }

        if (preg_match('~(https?://|www\.)\S+|\b[a-z0-9-]+\.(com|net|org|io|me|ly|xyz|app)\b~i', $body)) {
            return self::LINK;
        }

        if (preg_match('~(\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}~', $body)) {
            return self::CONTACT;
        }

        return null;
    }

    public static function warning(?string $flag): ?string
    {
        return match ($flag) {
            self::MONEY => 'This message mentions money, gift cards or payments. Never send money or gift cards to someone you met online, no matter the story.',
            self::OFF_PLATFORM => 'Scammers often ask to move the chat to another app quickly. Take your time and keep chatting here until you trust them.',
            self::LINK => 'This message contains a link. Do not enter your password or card details on a site a match sends you.',
            self::CONTACT => 'This message contains a phone number. Only share your number when you feel ready.',
            self::SPAM => 'This looks like a copy-and-paste message sent to many people. Be careful.',
            default => null,
        };
    }

    /**
     * @param  list<string>  $phrases
     */
    private function containsAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('~(?<![a-z])'.preg_quote(Str::lower($phrase), '~').'(?![a-z])~', $text)) {
                return true;
            }
        }

        return false;
    }
}
