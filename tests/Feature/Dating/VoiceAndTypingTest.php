<?php

use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\Discovery;
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

test('city pages have questions, structured data and sharing previews for Google', function () {
    $html = $this->get(route('areas.city', ['new-york', 'new-york']))
        ->assertOk()
        ->assertSee('Questions about dating in New York, NY')
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('property="og:title"', false)
        ->getContent();

    expect(json_decode(Str::betweenFirst($html, '<script type="application/ld+json">', '</script>'), true))
        ->toHaveKey('@graph');
});

test('robots.txt points at the real sitemap and keeps member pages private', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'))
        ->assertSee('Disallow: /messages')
        ->assertSee('Disallow: /voice/');
});

test('the home checklist shows only steps still to do, and disappears when all are done', function () {
    $me = User::factory()->discoverable()->create();

    $this->actingAs($me)->get(route('dashboard'))
        ->assertSee('data-test="checklist"', false)
        ->assertSee('Verify your location')
        ->assertDontSee('Create your profile')
        ->assertDontSee('Verify your email');

    $me->profile->forceFill(['latitude' => 40.7, 'longitude' => -74.0, 'location_verified_at' => now(), 'photo_verified_at' => now()])->save();

    $this->actingAs($me->fresh())->get(route('dashboard'))
        ->assertDontSee('data-test="checklist"', false)
        ->assertDontSee('Get the most out of');
});

test('today’s picks only come from my own city', function () {
    $me = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women', 'state' => 'NY', 'city' => 'New York'])->create();
    User::factory()->discoverable(['display_name' => 'CityGirl', 'gender' => 'woman', 'seeking' => 'men', 'state' => 'NY', 'city' => 'New York'])->create();
    User::factory()->discoverable(['display_name' => 'YonkersGirl', 'gender' => 'woman', 'seeking' => 'men', 'state' => 'NY', 'city' => 'Yonkers'])->create();

    $picks = Discovery::dailyPicks($me);
    $names = collect([$picks['pick']])->merge($picks['standouts'])->filter()->map->displayName();

    expect($names)->toContain('CityGirl')->not->toContain('YonkersGirl');
});
