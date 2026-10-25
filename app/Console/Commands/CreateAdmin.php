<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin')]
#[Description('Create a moderator/admin account (or promote an existing member)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = text('Admin email', required: true, validate: ['email' => 'email']);

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        $name = text('Full name', required: true);
        $password = password('Password (min 12 characters)', required: true);

        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)]]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'terms_accepted_at' => now(),
        ]);

        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Admin {$email} created. Log in at ".route('login'));

        return self::SUCCESS;
    }
}
