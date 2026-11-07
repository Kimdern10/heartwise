<?php

use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

function chatPair(): array
{
    $me = User::factory()->discoverable()->create();
    $her = User::factory()->discoverable()->create();

    return [$me, $her, UserMatch::createBetween($me, $her)];
}

test('members can send a voice note and only the two of them (and moderators) can play it', function () {
    [$me, $her, $match] = chatPair();

    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])
        ->set('voice', UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))
        ->call('sendVoice', 12)
        ->assertReturned(true)
        ->assertSeeHtml('data-test="voice-note"');

    $message = Message::where('match_id', $match->id)->first();
    expect($message->isVoice())->toBeTrue()
        ->and($message->audio_seconds)->toBe(12);
    Storage::disk('local')->assertExists($message->audio_path);

    $this->actingAs($her)->get(route('voice.show', $message))->assertOk();
    $this->actingAs(User::factory()->moderator()->create())->get(route('voice.show', $message))->assertOk();
    $this->actingAs(User::factory()->discoverable()->create())->get(route('voice.show', $message))->assertNotFound();

    $match->unmatch($me);
    $this->actingAs($her)->get(route('voice.show', $message))->assertNotFound();
});

test('voice notes must be audio and at most a minute', function () {
    [$me, , $match] = chatPair();

    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])
        ->set('voice', UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'))
        ->call('sendVoice', 5)
        ->assertHasErrors('voice');

    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])
        ->set('voice', UploadedFile::fake()->create('voice.webm', 40, 'audio/webm'))
        ->call('sendVoice', 95)
        ->assertReturned(false);

    expect(Message::count())->toBe(0);
});

test('the other person sees "is typing…" while I type', function () {
    [$me, $her, $match] = chatPair();

    Livewire::actingAs($me)->test('pages::messages.show', ['match' => $match])->call('typing');

    Livewire::actingAs($her)->test('pages::messages.show', ['match' => $match])
        ->assertSee($me->displayName().' is typing…');
});

test('home shows who is online now in my city', function () {
    $me = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women', 'state' => 'NY', 'city' => 'New York'])->create();
    User::factory()->discoverable(['display_name' => 'OnlineNina', 'gender' => 'woman', 'seeking' => 'men', 'state' => 'NY', 'city' => 'New York'])
        ->create(['last_active_at' => now()]);
    User::factory()->discoverable(['display_name' => 'AwayAmy', 'gender' => 'woman', 'seeking' => 'men', 'state' => 'NY', 'city' => 'New York'])
        ->create(['last_active_at' => now()->subDay()]);

    $html = $this->actingAs($me)->get(route('dashboard'))->assertSee('Online now in New York')->getContent();
    $strip = Str::between($html, 'data-test="online-now"', 'data-test="online-now-end"');

    expect($strip)->toContain('OnlineNina')->not->toContain('AwayAmy');
});
