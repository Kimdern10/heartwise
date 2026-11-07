<?php

use App\Actions\Dating\LikeUser;
use App\Models\User;
use App\Notifications\MemberAlertPush;
use Illuminate\Support\Facades\Notification;

test('members can turn phone notifications on and off', function () {
    $me = User::factory()->discoverable()->create();
    $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BPpublic', 'auth' => 'authsecret'], 'contentEncoding' => 'aes128gcm'];

    $this->actingAs($me)->postJson(route('push.subscribe'), $sub)->assertOk();
    expect($me->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($me)->postJson(route('push.unsubscribe'), ['endpoint' => $sub['endpoint']])->assertOk();
    expect($me->pushSubscriptions()->count())->toBe(0);
});

test('subscriptions must point to a real https push service', function () {
    $this->actingAs(User::factory()->discoverable()->create())
        ->postJson(route('push.subscribe'), ['endpoint' => 'http://evil.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
        ->assertUnprocessable();
});

test('a like sends a phone notification only to members who turned them on', function () {
    Notification::fake();

    $fan = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();
    $withPush = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $withoutPush = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men'])->create();
    $withPush->updatePushSubscription('https://fcm.googleapis.com/fcm/send/xyz', 'BPpublic', 'authsecret', 'aes128gcm');

    app(LikeUser::class)->handle($fan, $withPush);
    app(LikeUser::class)->handle($fan, $withoutPush);

    Notification::assertSentTo($withPush, MemberAlertPush::class, fn ($n) => $n->kind === 'like');
    Notification::assertNotSentTo($withoutPush, MemberAlertPush::class);

    $message = (new MemberAlertPush('like', 'Someone new likes you', 'Open your Likes', route('activity')))
        ->toWebPush($withPush, new MemberAlertPush('like', 'x', 'x', 'x'))->toArray();
    expect($message['title'])->toBe('Someone new likes you')
        ->and($message['data']['url'])->toBe(route('activity'));
});
