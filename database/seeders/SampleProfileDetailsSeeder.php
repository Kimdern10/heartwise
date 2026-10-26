<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * LOCAL TESTING ONLY. Adds prompts, lifestyle details and interests to the
 * sample members (is_sample = true) so profiles can be clicked through.
 * Safe to run again: php artisan db:seed --class=SampleProfileDetailsSeeder
 */
class SampleProfileDetailsSeeder extends Seeder
{
    private const ANSWERS = [
        'Sample answer — farmers market, a long walk and an early night.',
        'Sample answer — tell me your best restaurant recommendation.',
        'Sample answer — road trip playlists and gas station snacks.',
        'Sample answer — someone kind who also likes a good debate.',
        'Sample answer — pineapple belongs on pizza. Fight me.',
        'Sample answer — I can name every state capital.',
        'Sample answer — learning to make fresh pasta.',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample data must never be created on the live site.');
        }

        User::query()->where('is_sample', true)->whereHas('profile')->with('profile')->get()
            ->each(function (User $user) {
                $vitals = collect(config('profile_options.vitals'))
                    ->map(fn ($vital) => Arr::random(array_keys($vital['options'])))
                    ->all();

                $user->profile->forceFill([
                    ...$vitals,
                    'job_title' => Arr::random(['Nurse', 'Teacher', 'Software developer', 'Chef', 'Accountant', 'Designer']),
                    'interests' => Arr::random(config('profile_options.interests'), 5),
                ])->save();

                $user->prompts()->delete();

                foreach (Arr::random(config('profile_options.prompts'), 3) as $i => $prompt) {
                    $user->prompts()->create(['prompt' => $prompt, 'answer' => Arr::random(self::ANSWERS), 'position' => $i]);
                }
            });
    }
}
