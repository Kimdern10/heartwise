<?php

use App\Actions\Dating\LikeUser;
use App\Actions\Dating\SendMessage;
use App\Models\User;
use App\Models\UserMatch;
use Livewire\Livewire;

test('page addresses use random codes, not database numbers', function () {
    $me = User::factory()->discoverable()->create();
    $her = User::factory()->discoverable()->create();
    $match = UserMatch::createBetween($me, $her);

    expect($her->public_id)->toMatch('/^[a-z0-9]{12}$/')
        ->and(route('people.show', $her))->toEndWith('/people/'.$her->public_id)
        ->and(route('messages.show', $match))->toEndWith('/messages/'.$match->public_id);

    $this->actingAs($me)->get(route('people.show', $her))->assertOk();
    $this->actingAs($me)->get('/people/'.$her->id)->assertNotFound();
    $this->actingAs($me)->get('/messages/'.$match->id)->assertNotFound();
});

test('the live checker pops up new messages and updates the badges', function () {
    $me = User::factory()->discoverable()->create();
    $her = User::factory()->discoverable()->create();
    $match = UserMatch::createBetween($me, $her);

    $pulse = Livewire::actingAs($me)->test('live-pulse');

    $this->travel(2)->seconds();
    app(SendMessage::class)->handle($her, $match, 'Hey you!');

    $pulse->call('check')
        ->assertDispatched('live-alert', fn ($event, $params) => $params['kind'] === 'message'
            && str_contains($params['title'], $her->displayName())
            && $params['url'] === route('messages.show', $match))
        ->assertDispatched('live-counts', fn ($event, $params) => $params['messages'] === 1);
});

test('the live checker announces a new match and a new like', function () {
    $me = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();
    $fan = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $crush = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    app(LikeUser::class)->handle($me, $crush);

    $pulse = Livewire::actingAs($me)->test('live-pulse');
    $this->travel(2)->seconds();

    app(LikeUser::class)->handle($fan, $me);
    app(LikeUser::class)->handle($crush, $me);   // likes me back → match

    $pulse->call('check')
        ->assertDispatched('live-alert', fn ($e, $p) => $p['kind'] === 'match')
        ->assertDispatched('live-alert', fn ($e, $p) => $p['kind'] === 'like')
        ->assertDispatched('live-counts', fn ($e, $p) => $p['likes'] === 1);

    // Nothing new: nothing is sent again.
    $pulse->call('check')->assertNotDispatched('live-alert')->assertNotDispatched('live-counts');
});
