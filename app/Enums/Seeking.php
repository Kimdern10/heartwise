<?php

namespace App\Enums;

enum Seeking: string
{
    case Men = 'men';
    case Women = 'women';
    case Everyone = 'everyone';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Men',
            self::Women => 'Women',
            self::Everyone => 'Everyone',
        };
    }

    /**
     * The genders this preference is interested in.
     *
     * @return list<string>
     */
    public function genders(): array
    {
        return match ($this) {
            self::Men => [Gender::Man->value],
            self::Women => [Gender::Woman->value],
            self::Everyone => array_column(Gender::cases(), 'value'),
        };
    }
}
