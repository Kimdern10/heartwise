<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

test('admins can make a member a moderator and back', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->discoverable()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users')->call('changeRole', $member->id, 'moderator');
    expect($member->fresh())->role->toBe(Role::Moderator)->isStaff()->toBeTrue()->isAdmin()->toBeFalse();

    Livewire::test('pages::admin.users')->call('changeRole', $member->id, 'admin');
    expect($member->fresh()->isAdmin())->toBeTrue();

    Livewire::test('pages::admin.users')->call('changeRole', $member->id, 'member');
    expect($member->fresh())->role->toBe(Role::Member)->is_admin->toBeFalse();
});

test('admins cannot change their own role or remove the last admin', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.users')->call('changeRole', $admin->id, 'member');
    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('moderators can moderate but not ban, give roles, or open security', function () {
    $moderator = User::factory()->moderator()->create();
    $member = User::factory()->discoverable()->create();
    $this->actingAs($moderator);

    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Moderator panel')->assertDontSee('Security / VPN');
    $this->get(route('admin.profiles'))->assertOk();
    $this->get(route('admin.reports'))->assertOk();
    $this->get(route('admin.security'))->assertForbidden();

    Livewire::test('pages::admin.users')->call('suspend', $member->id, 7);
    expect($member->fresh()->status)->toBe(AccountStatus::Suspended);

    Livewire::test('pages::admin.users')->call('reactivate', $member->id)->call('ban', $member->id)->assertForbidden();
    expect($member->fresh()->status)->toBe(AccountStatus::Active);

    Livewire::test('pages::admin.users')->call('changeRole', $member->id, 'admin')->assertForbidden();
});

test('staff are sent to the panel, not the dating pages', function () {
    $this->actingAs(User::factory()->moderator()->create())
        ->get(route('discover'))
        ->assertRedirect(route('admin.dashboard'));
});

test('staff cannot be suspended', function () {
    $moderator = User::factory()->moderator()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.users')->call('suspend', $moderator->id, 7);
    expect($moderator->fresh()->isActive())->toBeTrue();
});
