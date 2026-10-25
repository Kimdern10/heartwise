<?php

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates photo rows only (no file). Tests that need a file should upload one.
 *
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        $name = 'photos/test/'.Str::random(20);

        return [
            'user_id' => User::factory(),
            'path' => $name.'.webp',
            'path_sm' => $name.'-sm.webp',
            'position' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ModerationStatus::Approved]);
    }
}
