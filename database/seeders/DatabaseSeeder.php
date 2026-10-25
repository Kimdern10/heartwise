<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Nothing is seeded on the live site: create the first admin there with
     * `php artisan app:create-admin`. Locally we add test accounts and
     * clearly-labelled sample members.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->call([
            LocalTestAccountsSeeder::class,
            SampleMembersSeeder::class,
        ]);
    }
}
