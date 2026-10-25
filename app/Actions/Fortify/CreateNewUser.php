<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $minAge = config('dating.minimum_age');

        Validator::make($input, [
            ...$this->profileRules(),
            'date_of_birth' => [
                'required',
                'date',
                'before_or_equal:'.now()->subYears($minAge)->toDateString(),
                'after:'.now()->subYears(config('dating.maximum_age') + 1)->toDateString(),
            ],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'date_of_birth.before_or_equal' => __('You must be at least :age years old to join.', ['age' => $minAge]),
            'date_of_birth.after' => __('Please enter your real date of birth.'),
            'terms.accepted' => __('Please confirm you are :age or older and agree to the Terms and Privacy Policy.', ['age' => $minAge]),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'date_of_birth' => $input['date_of_birth'],
            'terms_accepted_at' => now(),
        ]);
    }
}
