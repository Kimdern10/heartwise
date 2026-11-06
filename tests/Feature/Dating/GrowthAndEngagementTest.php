<?php

use App\Actions\Dating\LikeUser;
use App\Actions\Dating\SendMessage;
use App\Enums\AccountStatus;
use App\Models\ConnectionCheck;
use App\Models\DateCheckin;
use App\Models\Like;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMatch;
use App\Notifications\DateCheckinFriendMail;
use App\Notifications\MemberAlert;
use App\Notifications\WeeklyDigest;
use App\Rules\NotDisposableEmail;
use App\Support\ConnectionInspector;
use App\Support\Discovery;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function pairInTexas(): array
{
    return [
        User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women', 'state' => 'TX'])->create(),
        User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men', 'state' => 'TX'])->create(),
    ];
}

/* ---------- Notifications ---------- */

test('a like puts an anonymous alert in the bell and emails at most every 6 hours', function () {
    Notification::fake();
    [$a, $b] = pairInTexas();
    $c = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women', 'state' => 'TX'])->create();

    app(LikeUser::class)->handle($a, $b);
    app(LikeUser::class)->handle($c, $b);

    Notification::assertSentToTimes($b, MemberAlert::class, 3); // 2 bell + 1 email
    Notification::assertSentTo($b, MemberAlert::class, fn ($n) => $n->kind === 'like' && ! str_contains($n->title, $a->displayName()));
    expect($b->fresh()->last_like_email_at)->not->toBeNull();
});

test('a match notifies both people', function () {
    Notification::fake();
    [$a, $b] = pairInTexas();

    app(LikeUser::class)->handle($a, $b);
    app(LikeUser::class)->handle($b, $a);

    Notification::assertSentTo($a, MemberAlert::class, fn ($n) => $n->kind === 'match');
    Notification::assertSentTo($b, MemberAlert::class, fn ($n) => $n->kind === 'match');
});

test('message emails only go to people who are away, and are switchable', function () {
    Notification::fake();
    [$a, $b] = pairInTexas();
    $match = UserMatch::createBetween($a, $b);

    $b->forceFill(['last_active_at' => now()])->save();
    app(SendMessage::class)->handle($a, $match, 'Hi there!');
    Notification::assertSentTo($b, MemberAlert::class, fn ($n, $channels) => $channels === ['database']);
    Notification::assertNotSentTo($b, MemberAlert::class, fn ($n, $channels) => $channels === ['mail']);

    $b->forceFill(['last_active_at' => now()->subHour()])->save();
    app(SendMessage::class)->handle($a, $match->fresh(), 'Are you there?');
    Notification::assertSentTo($b, MemberAlert::class, fn ($n, $channels) => $channels === ['mail']);
});

test('the bell shows notifications and marks them read', function () {
    [$a, $b] = pairInTexas();
    app(LikeUser::class)->handle($a, $b);

    $this->actingAs($b);

    Livewire::test('notification-bell')
        ->assertSee('Someone new likes you')
        ->call('markAllRead');

    expect($b->unreadNotifications()->count())->toBe(0);
});

test('members can switch email notifications off', function () {
    $user = User::factory()->discoverable()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.privacy')->set('emails.notify_likes', false)->set('emails.notify_digest', false);

    expect($user->fresh())->notify_likes->toBeFalse()->notify_digest->toBeFalse()->notify_matches->toBeTrue();
});

/* ---------- Weekly digest ---------- */

test('the weekly digest tells members about new people in their state', function () {
    Notification::fake();
    [$me, $newbie] = pairInTexas();
    $me->forceFill(['created_at' => now()->subMonth()])->save();
    $optedOut = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women', 'state' => 'TX'])->create(['notify_digest' => false]);

    $this->artisan('app:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($me, WeeklyDigest::class, fn ($n) => $n->newPeople === 1 && $n->state === 'Texas');
    Notification::assertNotSentTo($optedOut, WeeklyDigest::class);
});

/* ---------- Phone app ---------- */

test('the app can be installed', function () {
    expect(file_exists(public_path('manifest.webmanifest')))->toBeTrue()
        ->and(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(file_exists(public_path('icons/icon-512.png')))->toBeTrue();

    $this->get(route('home'))->assertSee('manifest.webmanifest', false);
});

/* ---------- Chat extras ---------- */

test('icebreakers show until I send my first message', function () {
    [$a, $b] = pairInTexas();
    $b->prompts()->create(['prompt' => 'My simple pleasures', 'answer' => 'Rain', 'position' => 0]);
    $match = UserMatch::createBetween($a, $b);
    $this->actingAs($a);

    Livewire::test('pages::messages.show', ['match' => $match])
        ->assertSee('My simple pleasures')
        ->set('body', 'Hello!')->call('send')
        ->assertDontSee('data-test="icebreakers"', false);
});

test('read receipts are only shown to premium members', function () {
    [$a, $b] = pairInTexas();
    $match = UserMatch::createBetween($a, $b);
    $match->messages()->create(['sender_id' => $a->id, 'body' => 'Hi'])->forceFill(['read_at' => now()])->save();

    $this->actingAs($a);
    Livewire::test('pages::messages.show', ['match' => $match])->assertDontSee('Seen');

    $this->actingAs(makePremium($a));
    Livewire::test('pages::messages.show', ['match' => $match])->assertSee('Seen');
});

/* ---------- Premium: trial, boost, rewind ---------- */

test('only first-time subscribers get the free trial', function () {
    $new = User::factory()->create();
    $returning = makePremium(User::factory()->create());

    expect($new->eligibleForTrial())->toBeTrue()
        ->and($returning->eligibleForTrial())->toBeFalse();
});

test('premium members can boost once a week and appear first', function () {
    [$me, $her] = pairInTexas();
    $other = User::factory()->discoverable(['gender' => 'woman', 'seeking' => 'men', 'state' => 'TX'])->create(['last_active_at' => now()]);
    $her->forceFill(['last_active_at' => now()->subDays(3)])->save();
    $her = makePremium($her);

    $this->actingAs($her);
    Livewire::test('boost-button')->call('boost');
    expect($her->fresh()->isBoosted())->toBeTrue()
        ->and($her->fresh()->canBoost())->toBeFalse();

    expect(Discovery::for($me)->first()->id)->toBe($her->id);

    // After boosting, pages show "Next Boost …" (this used to crash).
    $this->get(route('dashboard'))->assertOk()->assertSee('Boosted');
    $her->profile->forceFill(['boosted_until' => now()->subMinute()])->save();
    $this->get(route('dashboard'))->assertOk()->assertSee('Next Boost');

    $this->actingAs($free = User::factory()->discoverable()->create());
    Livewire::test('boost-button')->call('boost');
    expect($free->fresh()->isBoosted())->toBeFalse();
});

test('rewind brings back the last pass for premium members only', function () {
    [$me, $her] = pairInTexas();
    app(LikeUser::class)->handle($me, $her, false);

    $this->actingAs($me);
    Livewire::test('pages::discover')->call('rewind')->assertRedirect(route('premium'));

    $this->actingAs($me = makePremium($me));
    Livewire::test('pages::discover')
        ->call('rewind')
        ->assertSet('rewoundId', $her->id)
        ->assertSee($her->displayName());

    expect(Like::where('user_id', $me->id)->count())->toBe(0);
});

/* ---------- Safety ---------- */

test('throwaway email addresses cannot sign up', function () {
    expect(NotDisposableEmail::isDisposable('a@mailinator.com'))->toBeTrue()
        ->and(NotDisposableEmail::isDisposable('a@x.mailinator.com'))->toBeTrue()
        ->and(NotDisposableEmail::isDisposable('a@gmail.com'))->toBeFalse();

    Setting::put('blocked_email_domains', 'sketchy.example');
    expect(NotDisposableEmail::isDisposable('a@sketchy.example'))->toBeTrue();

    $this->post(route('register.store'), [
        'email' => 'scammer@yopmail.com', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1',
    ])->assertSessionHasErrors('email');
});

test('a new account on a banned member’s device is flagged, or suspended if the admin chooses', function () {
    $banned = User::factory()->create(['status' => AccountStatus::Banned]);
    ConnectionCheck::create(['user_id' => $banned->id, 'ip' => '203.0.113.9', 'device_id' => str_repeat('d', 40)]);

    $newcomer = User::factory()->create();
    app(ConnectionInspector::class)->checkBanEvasion($newcomer, '198.51.100.1', str_repeat('d', 40));
    expect($newcomer->fresh()->flag_reason)->toContain('Same device')
        ->and($newcomer->fresh()->isActive())->toBeTrue();

    Setting::put('ban_evasion', 'suspend');
    $another = User::factory()->create();
    app(ConnectionInspector::class)->checkBanEvasion($another, '198.51.100.2', str_repeat('d', 40));
    expect($another->fresh()->status)->toBe(AccountStatus::Suspended);

    $sameIpOnly = User::factory()->create();
    app(ConnectionInspector::class)->checkBanEvasion($sameIpOnly, '203.0.113.9', null);
    expect($sameIpOnly->fresh()->flag_reason)->toContain('IP')
        ->and($sameIpOnly->fresh()->isActive())->toBeTrue();
});

test('date check-in emails the friend, alerts them if no check-in, and confirms when safe', function () {
    Notification::fake();
    [$a, $b] = pairInTexas();
    $match = UserMatch::createBetween($a, $b);
    $this->actingAs($a);

    Livewire::test('pages::messages.show', ['match' => $match])
        ->set('planWhen', now()->addHour()->format('Y-m-d\TH:i'))
        ->set('planPlace', 'Jo’s Coffee, Austin')
        ->set('planFriendName', 'Kemi')
        ->set('planFriendEmail', 'kemi@example.com')
        ->call('shareDatePlan')
        ->assertHasNoErrors();

    $checkin = DateCheckin::first();
    Notification::assertSentOnDemand(DateCheckinFriendMail::class, fn ($n, $c, $notifiable) => $n->kind === 'plan' && isset($notifiable->routes['mail']['kemi@example.com']));

    // The form reads the time in the member's timezone (Chicago by default), so go well past the start.
    $this->travel(12)->hours();
    $this->artisan('app:check-date-safety')->assertSuccessful();
    Notification::assertSentOnDemand(DateCheckinFriendMail::class, fn ($n) => $n->kind === 'alert');
    expect($checkin->fresh()->alert_sent_at)->not->toBeNull();

    Livewire::test('pages::dashboard')->assertSee('I’m safe')->call('markSafe', $checkin->id);
    Notification::assertSentOnDemand(DateCheckinFriendMail::class, fn ($n) => $n->kind === 'safe');
    expect($checkin->fresh()->safe_at)->not->toBeNull();
});

/* ---------- Growth ---------- */

test('city and state pages exist for search engines and show no member details', function () {
    User::factory()->discoverable(['state' => 'TX', 'display_name' => 'SecretName'])->create();

    $this->get('/dating')->assertOk()->assertSee('Dating in Texas');
    $this->get('/dating/texas')->assertOk()->assertSee('Real dating in')->assertDontSee('SecretName')->assertDontSee('members in Texas');
    $this->get('/dating/texas/houston')->assertOk()->assertSee('Houston, TX');
    $this->get('/dating/texas/miami')->assertNotFound();
    $this->get('/dating/narnia')->assertNotFound();
    $this->get('/sitemap.xml')->assertOk()->assertSee('/dating/texas/houston', false);
});

test('admins see charts on the overview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('New sign-ups')
        ->assertSee('Est. monthly revenue')
        ->assertSee('Show as table');
});
