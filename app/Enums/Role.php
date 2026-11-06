<?php

namespace App\Enums;

enum Role: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Member => 'Uses the dating site.',
            self::Moderator => 'Reviews profiles, photos and selfies, handles reports and flagged messages, can suspend members.',
            self::Admin => 'Everything moderators can do, plus bans, security settings and staff roles.',
        };
    }
}
