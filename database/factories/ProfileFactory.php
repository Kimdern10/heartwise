<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->firstName(),
            'gender' => Gender::Woman,
            'seeking' => Seeking::Everyone,
            'relationship_goal' => fake()->randomElement(RelationshipGoal::cases()),
            'bio' => fake()->sentence(12),
            'city' => fake()->city(),
            'state' => fake()->randomElement(array_keys(config('dating.us_states'))),
            'height_cm' => fake()->numberBetween(152, 193),
            'is_visible' => true,
            'show_last_active' => true,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ModerationStatus::Approved]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['photo_verified_at' => now()]);
    }
}
