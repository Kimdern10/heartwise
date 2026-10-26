<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\Seeking;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL TESTING ONLY. Log in with these to click around:
 *   admin@heartwise.test  / password   (moderator)
 *   member@heartwise.test / password   (member with a profile, no photo yet)
 *   newbie@heartwise.test / password   (new sign-up: goes through the onboarding wizard)
 */
class LocalTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Local Admin',
            'email' => 'admin@heartwise.test',
            'is_sample' => true,
        ]);

        $member = User::factory()->create([
            'name' => 'Local Member',
            'email' => 'member@heartwise.test',
            'is_sample' => true,
        ]);

        // Has verified email but has not answered the sign-up questions yet.
        User::factory()->notOnboarded()->create([
            'name' => 'newbie',
            'email' => 'newbie@heartwise.test',
            'date_of_birth' => null,
            'is_sample' => true,
        ]);

        Profile::factory()->for($member)->create([
            'display_name' => 'Tester',
            'gender' => Gender::Man,
            'seeking' => Seeking::Women,
            'bio' => 'Local test account.',
        ]);
    }
}
