<?php

use App\Actions\Dating\SendMessage;
use App\Enums\AccountStatus;
use App\Exceptions\DatingActionException;
use App\Models\Block;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\MessageScreener;
use Livewire\Livewire;

function matchedPair(): array
{
    [$a, $b] = User::factory()->discoverable()->count(2)->create();

    return [$a, $b, UserMatch::createBetween($a, $b)];
}

test('matched members can chat', function () {
    [$a, $b, $match] = matchedPair();

    $this->actingAs($a);

    Livewire::test('pages::messages.show', ['match' => $match])
        ->set('body', 'Hi! How was your weekend?')
        ->call('send')
        ->assertSet('body', '')
        ->assertSee('How was your weekend?');

    expect(Message::where('match_id', $match->id)->where('sender_id', $a->id)->count())->toBe(1);

    $this->actingAs($b)->get(route('messages.index'))->assertOk()->assertSee('How was your weekend?');
});

test('opening a chat marks messages as read', function () {
    [$a, $b, $match] = matchedPair();
    $message = app(SendMessage::class)->handle($a, $match, 'Hello');

    $this->actingAs($b);
    Livewire::test('pages::messages.show', ['match' => $match]);

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('people outside the match cannot open the chat', function () {
    [, , $match] = matchedPair();

    $this->actingAs(User::factory()->discoverable()->create())
        ->get(route('messages.show', $match))
        ->assertNotFound();
});

test('you cannot message after a block', function () {
    [$a, $b, $match] = matchedPair();
    Block::create(['user_id' => $b->id, 'blocked_id' => $a->id]);

    app(SendMessage::class)->handle($a, $match, 'Hello?');
})->throws(DatingActionException::class);

test('you cannot message a banned member', function () {
    [$a, $b, $match] = matchedPair();
    $b->forceFill(['status' => AccountStatus::Banned])->save();

    app(SendMessage::class)->handle($a, $match, 'Hello?');
})->throws(DatingActionException::class);

test('money requests are flagged and the receiver sees a warning', function () {
    [$a, $b, $match] = matchedPair();

    $message = app(SendMessage::class)->handle($a, $match, 'Can you buy me a gift card? I will pay you back');

    expect($message->flag)->toBe(MessageScreener::MONEY);

    $this->actingAs($b);
    Livewire::test('pages::messages.show', ['match' => $match])
        ->assertSee('Never send money or gift cards');
});

test('the screener catches common scam and off-platform phrases', function (string $text, ?string $flag) {
    expect((new MessageScreener)->flag($text))->toBe($flag);
})->with([
    ['Send it via Western Union please', MessageScreener::MONEY],
    ['I only use Cash App', MessageScreener::MONEY],
    ['Let us talk on WhatsApp instead', MessageScreener::OFF_PLATFORM],
    ['Check out www.example.com', MessageScreener::LINK],
    ['Call me at (555) 123-4567', MessageScreener::CONTACT],
    ['I love hiking and good coffee', null],
    ['My subtle humor is great', null],
]);

test('copy-pasted messages to many matches are flagged as spam', function () {
    $sender = User::factory()->discoverable()->create();
    $text = 'Hey there gorgeous, you seem amazing, tell me about you';

    foreach (User::factory()->discoverable()->count(3)->create() as $other) {
        app(SendMessage::class)->handle($sender, UserMatch::createBetween($sender, $other), $text);
    }

    $fourth = UserMatch::createBetween($sender, User::factory()->discoverable()->create());

    expect(app(SendMessage::class)->handle($sender, $fourth, $text)->flag)->toBe(MessageScreener::SPAM);
});

test('messages are rate limited', function () {
    config(['dating.limits.messages_per_minute' => 2]);
    [$a, , $match] = matchedPair();

    app(SendMessage::class)->handle($a, $match, 'one');
    app(SendMessage::class)->handle($a, $match, 'two');

    expect(fn () => app(SendMessage::class)->handle($a, $match, 'three'))
        ->toThrow(DatingActionException::class, 'too quickly');
});
