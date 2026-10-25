<?php

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Models\Block;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use Livewire\Livewire;

test('a one-way like does not create a match', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();

    expect(app(LikeUser::class)->handle($a, $b))->toBeNull()
        ->and(UserMatch::count())->toBe(0);
});

test('a mutual like creates exactly one match', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();

    app(LikeUser::class)->handle($a, $b);
    $match = app(LikeUser::class)->handle($b, $a);

    expect($match)->toBeInstanceOf(UserMatch::class)
        ->and($match->includes($a))->toBeTrue()
        ->and($match->includes($b))->toBeTrue()
        ->and(UserMatch::count())->toBe(1);
});

test('a pass never creates a match', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();

    app(LikeUser::class)->handle($a, $b);
    expect(app(LikeUser::class)->handle($b, $a, liked: false))->toBeNull();
});

test('you cannot like someone when there is a block', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    Block::create(['user_id' => $b->id, 'blocked_id' => $a->id]);

    app(LikeUser::class)->handle($a, $b);
})->throws(DatingActionException::class);

test('you need an approved profile with a photo before liking', function () {
    $me = User::factory()->create();
    $other = User::factory()->discoverable()->create();

    app(LikeUser::class)->handle($me, $other);
})->throws(DatingActionException::class, 'approved photo');

test('daily like limit is enforced', function () {
    config(['dating.limits.likes_per_day' => 2]);
    $me = User::factory()->discoverable()->create();
    $others = User::factory()->discoverable()->count(3)->create();

    app(LikeUser::class)->handle($me, $others[0]);
    app(LikeUser::class)->handle($me, $others[1]);

    expect(fn () => app(LikeUser::class)->handle($me, $others[2]))
        ->toThrow(DatingActionException::class, 'used all your likes');
});

test('liking from discover shows a match toast when mutual', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    Like::create(['user_id' => $b->id, 'target_id' => $a->id, 'liked' => true]);

    $this->actingAs($a);

    Livewire::test('pages::discover')->call('react', $b->id, true);

    expect(UserMatch::between($a, $b)->exists())->toBeTrue();

    $this->get(route('matches'))->assertOk()->assertSee($b->displayName());
});
