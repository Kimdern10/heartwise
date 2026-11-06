<?php

namespace App\Console\Commands;

use Database\Seeders\TestAccountsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:remove-test-accounts')]
#[Description('Delete the test accounts (test1–test26@heartwise.test) and everything they created')]
class RemoveTestAccounts extends Command
{
    public function handle(): int
    {
        $count = TestAccountsSeeder::removeAll();

        $this->components->info("Removed {$count} test accounts and their photos, likes, matches and messages.");

        return self::SUCCESS;
    }
}
