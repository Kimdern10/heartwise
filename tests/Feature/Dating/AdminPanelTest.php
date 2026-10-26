<?php

use App\Models\Profile;
use App\Models\User;

test('admins are sent to the admin panel instead of member pages', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertRedirect(route('admin.dashboard'));
})->with(['dashboard', 'discover', 'matches', 'messages.index', 'premium', 'my-profile.edit', 'onboarding']);

test('admins see the admin menu, not the dating menu', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin panel')
        ->assertSee('Security / VPN')
        ->assertDontSee('Discover')
        ->assertDontSee('Get Premium');
});

test('members still see the dating menu', function () {
    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Discover')
        ->assertDontSee('Admin panel');
});

test('admins can review any profile, even one waiting for approval, without like buttons', function () {
    $pending = User::factory()->create();
    Profile::factory()->for($pending)->create(['display_name' => 'Waiting']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('people.show', $pending))
        ->assertOk()
        ->assertSee('Admin review')
        ->assertSee('Waiting')
        ->assertDontSee('data-test="like-button"', false)
        ->assertDontSee('data-test="favorite-button"', false);
});

test('members cannot open pending profiles', function () {
    $pending = User::factory()->create();
    Profile::factory()->for($pending)->create();

    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('people.show', $pending))
        ->assertNotFound();
});
