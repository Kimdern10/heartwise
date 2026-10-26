<?php

use App\Enums\ModerationStatus;
use App\Models\ConnectionCheck;
use App\Models\Like;
use App\Models\ProfileView;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\ConnectionInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Livewire\Livewire;

/* ---------------- Onboarding wizard ---------------- */

test('new members must finish onboarding before using the site', function () {
    $user = User::factory()->notOnboarded()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding'));
    $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee('What’s your first name?');
});

test('onboarded members are sent from the wizard to the dashboard', function () {
    $this->actingAs(User::factory()->discoverable()->create());

    Livewire::test('pages::onboarding')->assertRedirect(route('dashboard'));
});

test('the wizard walks through every question and creates the profile', function () {
    Storage::fake('local');
    $user = User::factory()->notOnboarded()->create();
    $this->actingAs($user);

    $wizard = Livewire::test('pages::onboarding')
        ->assertSet('step', 'name')
        ->set('displayName', 'Jordan')->call('next')->assertSet('step', 'gender')
        ->call('skip')->assertSet('step', 'gender') // required steps can't be skipped
        ->call('next')->assertHasErrors('gender')
        ->call('$set', 'gender', 'woman')->call('next')->assertSet('step', 'seeking')
        ->call('$set', 'seeking', 'men')->call('next')->assertSet('step', 'location')
        ->set('state', 'TX')->set('city', 'Austin')->call('next')->assertSet('step', 'goal')
        ->call('$set', 'goal', 'long_term')->call('next')->assertSet('step', 'height')
        ->set('heightFeet', '5')->set('heightInches', '6')->call('next')->assertSet('step', 'kids')
        ->call('skip')->assertSet('step', 'work')
        ->set('jobTitle', 'Nurse')->call('next')->assertSet('step', 'beliefs')
        ->call('skip')->call('skip')->assertSet('step', 'interests')
        ->call('toggleInterest', 'Hiking')->call('next')->assertSet('step', 'photos')
        ->call('next')->assertHasErrors('uploads')
        ->set('uploads', [UploadedFile::fake()->image('me.jpg', 800, 1000)])
        ->call('next')->assertSet('step', 'prompts')
        ->call('next')->assertHasErrors('prompts')
        ->set('prompts.0.prompt', 'My simple pleasures')->set('prompts.0.answer', 'Tacos on Tuesday')
        ->call('next')->assertSet('step', 'bio')
        ->call('skip')->assertSet('step', 'done')
        ->call('finish')
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->isOnboarded())->toBeTrue()
        ->and($user->profile->display_name)->toBe('Jordan')
        ->and($user->profile->status)->toBe(ModerationStatus::Pending)
        ->and($user->profile->height_cm)->toBe(168)
        ->and($user->profile->interests)->toBe(['Hiking'])
        ->and($user->prompts()->pluck('answer')->all())->toBe(['Tacos on Tuesday'])
        ->and($user->photos()->count())->toBe(1);
});

test('progress is saved between visits', function () {
    $user = User::factory()->notOnboarded()->create();
    $this->actingAs($user);

    Livewire::test('pages::onboarding')->set('displayName', 'Sam')->call('next');

    Livewire::test('pages::onboarding')->assertSet('step', 'gender')->assertSet('displayName', 'Sam');
});

test('members without a birthday (e.g. Google sign-up) must be 18+', function () {
    $user = User::factory()->notOnboarded()->create(['date_of_birth' => null, 'terms_accepted_at' => null]);
    $this->actingAs($user);

    Livewire::test('pages::onboarding')
        ->set('displayName', 'Kid')->call('next')
        ->assertSet('step', 'birthday')
        ->set('dob', now()->subYears(17)->toDateString())->call('next')
        ->assertHasErrors('dob')->assertSet('step', 'birthday')
        ->set('dob', now()->subYears(25)->toDateString())->call('next')
        ->assertSet('step', 'terms')
        ->call('next')->assertHasErrors('termsAccepted');
});

test('the device location fills in the state', function () {
    $this->actingAs(User::factory()->notOnboarded()->create());

    Livewire::test('pages::onboarding')
        ->call('useLocation', 30.27, -97.74)->assertSet('state', 'TX')
        ->call('useLocation', 6.52, 3.38)->assertHasErrors('state');
});

/* ---------------- Google / Apple sign-in ---------------- */

function fakeGoogleUser(string $id, ?string $email, string $name = 'Riley Smith'): void
{
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

    $social = (new SocialUser)->setRaw([])->map(['id' => $id, 'name' => $name, 'email' => $email]);

    Socialite::shouldReceive('driver->user')->andReturn($social);
}

test('google sign-up creates a verified member who then onboards', function () {
    fakeGoogleUser('g-123', 'riley@example.com');

    $this->get(route('social.callback', 'google'))->assertRedirect(route('onboarding'));

    $user = User::where('email', 'riley@example.com')->first();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->date_of_birth)->toBeNull()
        ->and(SocialAccount::where('provider', 'google')->where('provider_id', 'g-123')->value('user_id'))->toBe($user->id);
    $this->assertAuthenticatedAs($user);
});

test('google sign-in links to an existing account with the same email', function () {
    $existing = User::factory()->discoverable()->create(['email' => 'same@example.com']);
    fakeGoogleUser('g-999', 'same@example.com');

    $this->get(route('social.callback', 'google'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($existing);
    expect(User::where('email', 'same@example.com')->count())->toBe(1);
});

test('social buttons only work when configured', function () {
    config(['services.google.client_id' => null, 'services.apple.client_id' => null]);

    $this->get(route('social.redirect', 'google'))->assertNotFound();
    $this->get(route('login'))->assertOk()->assertDontSee('Continue with Google');
});

/* ---------------- Premium ---------------- */

test('the premium page shows plans and is honest when payments are not set up', function () {
    config(['cashier.key' => null, 'cashier.secret' => null]);
    $user = User::factory()->discoverable()->create();

    $this->actingAs($user)->get(route('premium'))->assertOk()->assertSee('Payments coming soon')->assertSee('Cancel any time');
    $this->actingAs($user)->post(route('premium.checkout'), ['plan' => 'monthly'])->assertSessionHas('status');
});

test('premium members get more likes and sparks', function () {
    $free = User::factory()->create();
    $premium = makePremium(User::factory()->create());

    expect($free->likesPerDay())->toBe(config('premium.free.likes_per_day'))
        ->and($premium->likesPerDay())->toBe(config('premium.premium.likes_per_day'))
        ->and($premium->sparksPerDay())->toBeGreaterThan($free->sparksPerDay())
        ->and($premium->isPremium())->toBeTrue();
});

test('free members see one like at a time; the rest are locked', function () {
    $me = User::factory()->discoverable()->create();
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    Like::create(['user_id' => $a->id, 'target_id' => $me->id, 'liked' => true]);
    Like::create(['user_id' => $b->id, 'target_id' => $me->id, 'liked' => true]);

    $this->actingAs($me);
    $html = $this->get(route('activity'))->assertOk()->assertSee('See with Premium')->getContent();

    expect(substr_count($html, 'data-test="like-back-'))->toBe(1);
});

test('free members cannot see who viewed them', function () {
    [$me, $viewer] = User::factory()->discoverable()->count(2)->create();
    ProfileView::record($viewer, $me);

    $this->actingAs($me)->get(route('activity', 'views'))->assertOk()
        ->assertSee('Get Premium to see who')
        ->assertDontSee($viewer->displayName().', ');

    $this->actingAs(makePremium($me))->get(route('activity', 'views'))->assertSee($viewer->displayName());
});

test('advanced search filters are ignored for free members', function () {
    $me = User::factory()->discoverable(['gender' => 'man', 'seeking' => 'women'])->create();
    User::factory()->discoverable(['display_name' => 'Drinker', 'gender' => 'woman', 'seeking' => 'men', 'drinking' => 'yes'])->create();
    User::factory()->discoverable(['display_name' => 'NonDrinker', 'gender' => 'woman', 'seeking' => 'men', 'drinking' => 'no'])->create();

    $this->actingAs($me);

    Livewire::test('pages::search')->set('drinking', 'yes')->assertSee('Drinker')->assertSee('NonDrinker');
});

/* ---------------- VPN / location checks ---------------- */

test('a non-US device timezone on a US IP is a mismatch', function () {
    $inspector = app(ConnectionInspector::class);

    expect($inspector->timezoneMismatch('Africa/Lagos', 'US'))->toBeTrue()
        ->and($inspector->timezoneMismatch('America/Chicago', 'US'))->toBeFalse()
        ->and($inspector->timezoneMismatch('Europe/London', 'GB'))->toBeFalse();
});

test('admin rules decide what gets blocked', function () {
    $inspector = app(ConnectionInspector::class);
    $us = ['country' => 'US', 'vpn' => false, 'hosting' => false];
    $vpn = ['country' => 'US', 'vpn' => true, 'hosting' => false];
    $ng = ['country' => 'NG', 'vpn' => false, 'hosting' => false];

    expect($inspector->decide('1.1.1.1', $vpn, false))->toBe([false, null]); // default policy: flag only

    Setting::put('vpn_policy', 'block');
    Setting::put('blocked_countries', 'NG');
    expect($inspector->decide('1.1.1.1', $vpn, false))->toBe([true, 'vpn'])
        ->and($inspector->decide('1.1.1.1', $ng, false))->toBe([true, 'country'])
        ->and($inspector->decide('1.1.1.1', $us, false))->toBe([false, null]);

    Setting::put('blocked_countries', '');
    Setting::put('us_only', '1');
    Setting::put('allowed_ips', '1.1.1.1');
    expect($inspector->decide('2.2.2.2', $ng, false))->toBe([true, 'country'])
        ->and($inspector->decide('1.1.1.1', $ng, false))->toBe([false, null]);
});

test('vpn visitors see the blocked page when the admin blocks vpns', function () {
    Http::fake(['proxycheck.io/*' => Http::response([
        'status' => 'ok',
        '8.8.4.4' => ['proxy' => 'yes', 'type' => 'VPN', 'isocode' => 'US', 'country' => 'United States', 'city' => 'Dallas'],
    ])]);
    Setting::put('vpn_policy', 'block');
    Cache::flush();

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
        ->get(route('login'))
        ->assertForbidden()
        ->assertSee('Please turn off your VPN');

    expect(ConnectionCheck::where('blocked', true)->where('is_vpn', true)->exists())->toBeTrue();
});

test('signed-in members on a vpn are flagged for review', function () {
    Http::fake(['proxycheck.io/*' => Http::response([
        'status' => 'ok',
        '8.8.4.4' => ['proxy' => 'yes', 'type' => 'VPN', 'isocode' => 'US'],
    ])]);
    $user = User::factory()->discoverable()->create();

    $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
        ->withUnencryptedCookie('tz', 'Africa/Lagos')
        ->get(route('dashboard'))->assertOk();

    $check = ConnectionCheck::where('user_id', $user->id)->first();
    expect($check->is_vpn)->toBeTrue()
        ->and($check->timezone_mismatch)->toBeTrue()
        ->and($user->fresh()->flag_reason)->toContain('VPN');
});

test('admins manage connection rules and are never blocked', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.security')
        ->set('vpnPolicy', 'block')
        ->set('blockedCountries', 'ng, gh')
        ->set('allowedIps', 'not-an-ip')
        ->call('save')->assertHasErrors('allowedIps')
        ->set('allowedIps', '203.0.113.5')
        ->call('save')->assertHasNoErrors();

    expect(Setting::get('vpn_policy'))->toBe('block')
        ->and(Setting::list('blocked_countries'))->toBe(['NG', 'GH']);

    Setting::put('blocked_ips', '127.0.0.1');
    $this->get(route('admin.security'))->assertOk();
});
