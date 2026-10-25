<?php

use App\Models\Profile;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Support\Geo;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('signing up emails a 6-digit code', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Code Person',
        'email' => 'code@example.com',
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $user = User::where('email', 'code@example.com')->first();

    Notification::assertSentTo($user, EmailVerificationCode::class, fn ($n) => preg_match('/^\d{6}$/', $n->code) === 1);
});

test('the right code verifies the email', function () {
    $user = User::factory()->unverified()->create();
    $code = $user->issueEmailCode();

    $this->actingAs($user);

    Livewire::test('email-code-form')
        ->set('code', $code)
        ->call('verify')
        ->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email_code_hash)->toBeNull();
});

test('a wrong code does not verify and five tries cancel the code', function () {
    $user = User::factory()->unverified()->create();
    $code = $user->issueEmailCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->actingAs($user);

    $form = Livewire::test('email-code-form');

    foreach (range(1, 5) as $try) {
        $form->set('code', $wrong)->call('verify')->assertHasErrors('code');
    }

    $form->set('code', $code)->call('verify')->assertHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('expired codes are rejected', function () {
    $user = User::factory()->unverified()->create();
    $code = $user->issueEmailCode();
    $this->travel(16)->minutes();

    expect($user->fresh()->verifyEmailCode($code))->toBeFalse();
});

test('members can ask for a new code', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);
    Livewire::test('email-code-form')->call('resend')->assertSet('notice', fn ($v) => str_contains($v, $user->email));

    Notification::assertSentTo($user, EmailVerificationCode::class);
});

test('the verify page shows the code form', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('6-digit code');
});

test('location inside the profile state is verified and rounded', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['state' => 'TX']);
    $this->actingAs($user);

    Livewire::test('pages::profile.edit')->call('verifyLocation', 30.267153, -97.743057)->assertHasNoErrors();

    $profile->refresh();
    expect($profile->isLocationVerified())->toBeTrue()
        ->and($profile->latitude)->toBe(30.27)
        ->and($profile->longitude)->toBe(-97.74);
});

test('location outside the profile state is refused', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['state' => 'TX']);
    $this->actingAs($user);

    // Lagos, Nigeria
    Livewire::test('pages::profile.edit')->call('verifyLocation', 6.5244, 3.3792)->assertHasErrors('location');

    expect($profile->fresh()->isLocationVerified())->toBeFalse();
});

test('changing state clears the location check', function () {
    $user = User::factory()->discoverable(['state' => 'TX'])->create();
    $user->profile->verifyLocation(30.27, -97.74);
    $this->actingAs($user);

    Livewire::test('pages::profile.edit')->set('state', 'CA')->call('save');

    expect($user->profile->fresh()->isLocationVerified())->toBeFalse();
});

test('distance between verified members is shown in miles', function () {
    $austin = Profile::factory()->make(['latitude' => 30.27, 'longitude' => -97.74]);
    $dallas = Profile::factory()->make(['latitude' => 32.78, 'longitude' => -96.80]);

    expect($austin->milesFrom($dallas))->toBeGreaterThan(170)->toBeLessThan(200)
        ->and(Geo::isInState(40.71, -74.01, 'NY'))->toBeTrue()
        ->and(Geo::isInState(40.71, -74.01, 'CA'))->toBeFalse();
});
