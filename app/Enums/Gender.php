<?php

namespace App\Enums;

enum Gender: string
{
    case Man = 'man';
    case Woman = 'woman';
    case NonBinary = 'nonbinary';

    public function label(): string
    {
        return match ($this) {
            self::Man => 'Man',
            self::Woman => 'Woman',
            self::NonBinary => 'Non-binary',
        };
    }

    /**
     * The "seeking" values that include this gender.
     *
     * @return list<string>
     */
    public function seekingValuesThatInclude(): array
    {
        return match ($this) {
            self::Man => [Seeking::Men->value, Seeking::Everyone->value],
            self::Woman => [Seeking::Women->value, Seeking::Everyone->value],
            self::NonBinary => [Seeking::Everyone->value],
        };
    }
}
