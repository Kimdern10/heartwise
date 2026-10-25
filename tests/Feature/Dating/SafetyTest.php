<?php

use App\Enums\AccountStatus;
use App\Enums\ReportReason;
use App\Models\Block;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

test('a member can report and block someone', function () {
    [$me, $other] = User::factory()->discoverable()->count(2)->create();

    $this->actingAs($me);

    Livewire::test('report-block', ['userId' => $other->id])
        ->set('reason', ReportReason::Scam->value)
        ->set('details', 'Asked me for gift cards')
        ->call('report')
        ->assertRedirect(route('discover'));

    expect(Report::where('reporter_id', $me->id)->where('reported_id', $other->id)->first())
        ->reason->toBe(ReportReason::Scam)
        ->and(Block::where('user_id', $me->id)->where('blocked_id', $other->id)->exists())->toBeTrue();
});

test('a report needs a reason', function () {
    [$me, $other] = User::factory()->discoverable()->count(2)->create();

    $this->actingAs($me);

    Livewire::test('report-block', ['userId' => $other->id])
        ->call('report')
        ->assertHasErrors('reason');
});

test('a member can block and unblock someone', function () {
    [$me, $other] = User::factory()->discoverable()->count(2)->create();

    $this->actingAs($me);
    Livewire::test('report-block', ['userId' => $other->id])->call('block');
    expect($me->hasBlockBetween($other))->toBeTrue();

    Livewire::test('pages::settings.privacy')->call('unblock', $other->id);
    expect($me->hasBlockBetween($other))->toBeFalse();
});

test('banned members are logged out', function () {
    $user = User::factory()->create(['status' => AccountStatus::Banned]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('suspensions end automatically', function () {
    $user = User::factory()->create(['status' => AccountStatus::Suspended, 'suspended_until' => now()->subMinute()]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    expect($user->fresh()->status)->toBe(AccountStatus::Active);
});

test('members can hide their profile from discover', function () {
    $me = User::factory()->discoverable()->create();
    $this->actingAs($me);

    Livewire::test('pages::settings.privacy')->set('isVisible', false);

    expect(User::query()->discoverable()->whereKey($me->id)->exists())->toBeFalse();
});

test('members can download their data', function () {
    $me = User::factory()->discoverable()->create();

    $this->actingAs($me)->get(route('privacy.export'))
        ->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertJsonPath('account.email', $me->email);
});

test('legal pages are public', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['home', 'legal.terms', 'legal.privacy', 'legal.cookies', 'legal.safety', 'legal.guidelines']);
