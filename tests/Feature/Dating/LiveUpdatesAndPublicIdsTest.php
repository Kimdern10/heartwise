<?php

use App\Actions\Dating\LikeUser;
use App\Actions\Dating\SendMessage;
use App\Models\User;
use App\Models\UserMatch;
use App\Notifications\MemberAlertEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
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

test('when someone unmatches, the other person’s open chat closes and old bell links are removed', function () {
    $me = User::factory()->discoverable()->create();
    $her = User::factory()->discoverable()->create();
    $match = UserMatch::createBetween($me, $her);
    app(SendMessage::class)->handle($me, $match, 'Hi Isabella!');

    expect($her->notifications()->count())->toBe(1);

    $herChat = Livewire::actingAs($her)->test('pages::messages.show', ['match' => $match]);

    $match->unmatch($me);

    $herChat->call('poll')->assertRedirect(route('messages.index'));

    expect($her->notifications()->count())->toBe(0)
        ->and($her->unreadMessagesCount())->toBe(0);

    $this->actingAs($her)->get(route('messages.index'))->assertOk();
    $this->actingAs($her)->get(route('discover'))->assertOk()->assertDontSee($me->displayName());
});

test('the person whose like makes the match sees one celebration, not a second pop-up, and gets no email', function () {
    Notification::fake();

    $first = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $second = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();

    app(LikeUser::class)->handle($first, $second);
    app(LikeUser::class)->handle($second, $first);   // this like makes the match

    Notification::assertSentTo($first, MemberAlertEmail::class, fn ($n) => $n->kind === 'match');
    Notification::assertNotSentTo($second, MemberAlertEmail::class, fn ($n) => $n->kind === 'match');
    expect(new MemberAlertEmail('x', 'x', 'x', 'x'))->toBeInstanceOf(ShouldQueue::class);
});

test('the live checker does not pop up a match the member just made themselves', function () {
    $first = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $second = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();
    app(LikeUser::class)->handle($first, $second);

    $pulse = Livewire::actingAs($second)->test('live-pulse');
    $this->travel(2)->seconds();

    app(LikeUser::class)->handle($second, $first);   // match made by $second, celebrated on screen

    $pulse->call('check')->assertNotDispatched('live-alert');
    expect($second->notifications()->where('data->kind', 'match')->count())->toBe(1);   // still in the bell history
});

test('members can clear all their notifications from the bell', function () {
    $me = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $fan = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();
    app(LikeUser::class)->handle($fan, $me);

    expect($me->notifications()->count())->toBe(1);

    Livewire::actingAs($me)->test('notification-bell')
        ->assertSee('Clear all')
        ->call('clearAll')
        ->assertDontSee('Someone new likes you');

    expect($me->notifications()->count())->toBe(0);
});
