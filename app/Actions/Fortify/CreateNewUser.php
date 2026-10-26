<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Sign-up only needs an email, a password and the 18+ / rules confirmation.
     * Name, date of birth (checked for 18+) and the profile are collected in
     * the onboarding wizard straight after the email code.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please confirm you are :age or older and agree to the Terms and Privacy Policy.', ['age' => config('dating.minimum_age')]),
        ])->validate();

        return User::create([
            'name' => Str::of($input['email'])->before('@')->limit(40, '')->toString(),
            'email' => $input['email'],
            'password' => $input['password'],
            'terms_accepted_at' => now(),
        ]);
    }
}
