<?php

use App\Actions\Dating\LikeUser;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use Livewire\Livewire;

function nyMember(string $name, string $gender = 'woman'): User
{
    return User::factory()->discoverable([
        'display_name' => $name,
        'gender' => $gender,
        'seeking' => $gender === 'woman' ? 'men' : 'women',
        'state' => 'NY',
        'city' => 'New York',
    ])->create();
}

test('the chat accepts the text straight from the browser and says whether it worked', function () {
    $me = nyMember('Me', 'man');
    $her = nyMember('Her');
    $match = UserMatch::createBetween($me, $her);

    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])
        ->call('send', 'Hello there!')
        ->assertReturned(true)
        ->assertSee('Hello there!');

    expect(Message::where('match_id', $match->id)->value('body'))->toBe('Hello there!');

    // An empty message is refused, so the browser can put the text back.
    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])
        ->call('send', '   ')
        ->assertReturned(false);
});

test('people I am matched with never show in Discover or Search again', function () {
    $me = nyMember('Me', 'man');
    $matched = nyMember('Matched');
    nyMember('Stranger');
    UserMatch::createBetween($me, $matched);   // e.g. matched without a like row on my side

    $this->actingAs($me);

    Livewire::test('pages::search')->assertSee('Stranger')->assertDontSee('Matched');
    Livewire::test('pages::discover')->assertDontSee('Matched');
});

test('liked people disappear from Search', function () {
    $me = makePremium(nyMember('Me', 'man'));
    $liked = nyMember('LikedOne');

    $this->actingAs($me);
    Livewire::test('pages::search')->assertSee('LikedOne');

    app(LikeUser::class)->handle($me, $liked);

    Livewire::test('pages::search')->assertDontSee('LikedOne');
});

test('Premium members can search for other Premium members and see their badge', function () {
    $me = makePremium(nyMember('Me', 'man'));
    makePremium(nyMember('PaidMember'));
    nyMember('FreeMember');

    $this->actingAs($me);

    Livewire::test('pages::search')
        ->assertSee('PaidMember')->assertSee('FreeMember')
        ->assertSeeHtml('data-test="premium-badge"')
        ->set('premiumOnly', true)
        ->assertSee('PaidMember')->assertDontSee('FreeMember');
});

test('the Premium-only filter does nothing for free members', function () {
    $me = nyMember('Me', 'man');
    makePremium(nyMember('PaidMember'));
    nyMember('FreeMember');

    $this->actingAs($me);

    Livewire::test('pages::search')
        ->set('premiumOnly', true)
        ->assertSee('FreeMember')
        ->assertDontSeeHtml('data-test="premium-badge"');
});
