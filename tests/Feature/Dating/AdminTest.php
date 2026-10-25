<?php

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\SampleMembersSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('members cannot open the admin area', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});

test('admins can open every moderation page', function (string $route) {
    User::factory()->discoverable()->create();

    $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
})->with(['admin.dashboard', 'admin.profiles', 'admin.photos', 'admin.verifications', 'admin.reports', 'admin.messages', 'admin.users']);

test('admins approve or send back profiles', function () {
    $admin = User::factory()->admin()->create();
    $profile = Profile::factory()->create();
    $other = Profile::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.profiles')
        ->call('approve', $profile->id)
        ->call('reject', $other->id)
        ->assertHasErrors('reasons.'.$other->id)
        ->set('reasons.'.$other->id, 'Please remove your phone number')
        ->call('reject', $other->id);

    expect($profile->fresh()->status)->toBe(ModerationStatus::Approved)
        ->and($other->fresh()->status)->toBe(ModerationStatus::Rejected)
        ->and($other->fresh()->rejection_reason)->toBe('Please remove your phone number');
});

test('admins approve or reject photos', function () {
    $this->actingAs(User::factory()->admin()->create());
    [$a, $b] = Photo::factory()->count(2)->create();

    Livewire::test('pages::admin.photos')->call('approve', $a->id)->call('reject', $b->id, 2);

    expect($a->fresh()->status)->toBe(ModerationStatus::Approved)
        ->and($b->fresh()->status)->toBe(ModerationStatus::Rejected)
        ->and($b->fresh()->rejection_reason)->toContain('nudity');
});

test('approving a selfie gives the badge and deletes the selfie', function () {
    Storage::fake('local');
    $member = User::factory()->discoverable()->create();
    Storage::disk('local')->put('selfies/x.webp', 'x');
    $request = $member->verificationRequests()->create(['selfie_path' => 'selfies/x.webp', 'gesture' => 'Thumbs up']);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test('pages::admin.verifications')->call('approve', $request->id);

    expect($member->fresh()->isPhotoVerified())->toBeTrue()
        ->and($request->fresh()->selfie_path)->toBeNull();
    Storage::disk('local')->assertMissing('selfies/x.webp');
});

test('admins can ban a reported member and resolve the report', function () {
    $admin = User::factory()->admin()->create();
    [$reporter, $reported] = User::factory()->discoverable()->count(2)->create();
    $report = Report::create(['reporter_id' => $reporter->id, 'reported_id' => $reported->id, 'reason' => ReportReason::Scam]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.reports')
        ->assertSee($reported->email)
        ->call('ban', $reported->id)
        ->call('close', $report->id, 'resolved');

    expect($reported->fresh()->status)->toBe(AccountStatus::Banned)
        ->and($reported->fresh()->profile->is_visible)->toBeFalse()
        ->and($report->fresh()->status->value)->toBe('resolved');
});

test('admins cannot ban other admins', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    $this->actingAs($admin);
    Livewire::test('pages::admin.users')->call('ban', $other->id);

    expect($other->fresh()->status)->toBe(AccountStatus::Active);
});

test('admins can suspend and reactivate members', function () {
    $member = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.users')->call('suspend', $member->id, 7);
    expect($member->fresh()->status)->toBe(AccountStatus::Suspended)
        ->and($member->fresh()->suspended_until->isFuture())->toBeTrue();

    Livewire::test('pages::admin.users')->call('reactivate', $member->id);
    expect($member->fresh()->status)->toBe(AccountStatus::Active);
});

test('the sample seeder refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    (new SampleMembersSeeder)->run();
})->throws(RuntimeException::class);
