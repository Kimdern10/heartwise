<?php

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Notifications\MemberAlertEmail;
use Illuminate\Auth\Notifications\ResetPassword;

function renderedMail(object $notification, User $user): string
{
    return (string) $notification->toMail($user)->render();
}

test('every email has the Heartwise logo, colours and safety footer', function (object $notification) {
    $user = User::factory()->discoverable()->create();
    $html = renderedMail($notification, $user);

    expect($html)
        ->toContain('/icons/icon-192.png')
        ->toContain('class="logo"')
        ->toContain(config('app.name'))
        ->toContain('Never send money, gift cards or crypto')
        ->toContain('#f7f4ef')            // warm background from the Heartwise theme
        ->not->toContain('laravel.com/img');
})->with([
    'verification code' => fn () => new EmailVerificationCode('482913'),
    'password reset' => fn () => new ResetPassword('token123'),
    'new match' => fn () => new MemberAlertEmail('match', 'It’s a match!', 'Say hello', 'https://example.com', true, ['mail']),
]);

test('the verification code stands out in its own box', function () {
    $html = renderedMail(new EmailVerificationCode('482913'), User::factory()->create());

    expect($html)->toContain('class="code"')->toContain('482913');
});

test('the password reset email uses Heartwise wording', function () {
    $html = renderedMail(new ResetPassword('token123'), User::factory()->create());

    expect($html)->toContain('Choose a new password')->toContain('Forgot your password?');
});
