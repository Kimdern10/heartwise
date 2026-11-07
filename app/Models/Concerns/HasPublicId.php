<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Page addresses use a random code (/people/k3v9q2mz7xwp) instead of the
 * database number (/people/149), so nobody can guess or count members.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= static::newPublicId();
        });
    }

    public static function newPublicId(): string
    {
        do {
            $id = Str::lower(Str::random(12));
        } while (static::query()->where('public_id', $id)->exists());

        return $id;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
