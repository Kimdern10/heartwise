<?php

namespace App\Enums;

enum RelationshipGoal: string
{
    case LongTerm = 'long_term';
    case Marriage = 'marriage';
    case Dating = 'dating';
    case Friendship = 'friendship';
    case NotSure = 'not_sure';

    public function label(): string
    {
        return match ($this) {
            self::LongTerm => 'Long-term relationship',
            self::Marriage => 'Marriage',
            self::Dating => 'Dating',
            self::Friendship => 'New friends',
            self::NotSure => 'Still figuring it out',
        };
    }
}
