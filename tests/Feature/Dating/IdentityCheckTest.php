<?php

use App\Models\User;
use App\Support\IdentityCheck;
use Stripe\Identity\VerificationSession;

function stripeSession(string $status, ?array $dob = null): VerificationSession
{
    return VerificationSession::constructFrom([
        'id' => 'vs_test_123',
        'object' => 'identity.verification_session',
        'status' => $status,
        'verified_outputs' => $dob ? ['dob' => $dob] : null,
    ]);
}

test('the ID check is off unless switched on', function () {
    config(['services.identity.enabled' => false]);

    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('my-profile.verify-id'))->assertNotFound();

    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('my-profile.verify'))->assertOk()->assertDontSee('Verify my ID');
});

test('a verified adult gets the ID badge', function () {
    $user = User::factory()->discoverable()->create();

    expect(IdentityCheck::apply($user, stripeSession('verified', ['year' => 1995, 'month' => 4, 'day' => 12])))->toBe('verified')
        ->and($user->fresh()->isIdVerified())->toBeTrue();

    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('people.show', $user))->assertSee('data-test="id-badge"', false);
});

test('an ID showing someone under 18 is refused and flagged for moderators', function () {
    $user = User::factory()->discoverable()->create();

    expect(IdentityCheck::apply($user, stripeSession('verified', ['year' => now()->year - 16, 'month' => 1, 'day' => 1])))->toBe('too_young')
        ->and($user->fresh()->isIdVerified())->toBeFalse()
        ->and($user->fresh()->flag_reason)->toContain('under 18');
});

test('an unfinished check gives no badge', function () {
    $user = User::factory()->discoverable()->create();

    expect(IdentityCheck::apply($user, stripeSession('requires_input')))->toBe('requires_input')
        ->and($user->fresh()->isIdVerified())->toBeFalse();
});
