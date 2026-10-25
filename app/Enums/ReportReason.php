<?php

namespace App\Enums;

enum ReportReason: string
{
    case Scam = 'scam';
    case FakeProfile = 'fake_profile';
    case Underage = 'underage';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Spam = 'spam';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Scam => 'Scam or asking for money',
            self::FakeProfile => 'Fake profile or stolen photos',
            self::Underage => 'May be under 18',
            self::Harassment => 'Harassment or threats',
            self::Inappropriate => 'Inappropriate photos or content',
            self::Spam => 'Spam or advertising',
            self::Other => 'Something else',
        };
    }
}
