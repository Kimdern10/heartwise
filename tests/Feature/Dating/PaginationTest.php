<?php

use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use Livewire\Livewire;

test('long lists are split into pages', function () {
    $me = makePremium(User::factory()->discoverable()->create());
    $others = User::factory()->discoverable()->count(14)->create();

    foreach ($others as $other) {
        UserMatch::createBetween($me, $other);
    }

    $this->actingAs($me);

    $matches = Livewire::test('pages::matches');
    expect($matches->instance()->matches->total())->toBe(14)
        ->and($matches->instance()->matches->count())->toBe(12)
        ->and($matches->instance()->matches->hasPages())->toBeTrue();

    $messages = Livewire::test('pages::messages.index');
    expect($messages->instance()->conversations->total())->toBe(14);
});

test('the likes tab pages through everyone who liked you', function () {
    $me = makePremium(User::factory()->discoverable()->create());

    foreach (User::factory()->discoverable()->count(13)->create() as $fan) {
        Like::create(['user_id' => $fan->id, 'target_id' => $me->id, 'liked' => true]);
    }

    $this->actingAs($me);

    $likes = Livewire::test('pages::activity', ['tab' => 'likes'])->instance()->likes;
    expect($likes->total())->toBe(13)->and($likes->count())->toBe(12);
});

test('message search still works with pages', function () {
    $me = User::factory()->discoverable()->create();
    UserMatch::createBetween($me, User::factory()->discoverable(['display_name' => 'Zelda'])->create());
    UserMatch::createBetween($me, User::factory()->discoverable(['display_name' => 'Bob'])->create());

    $this->actingAs($me);

    Livewire::test('pages::messages.index')->set('search', 'zel')->assertSee('Zelda')->assertDontSee('Bob');
});
