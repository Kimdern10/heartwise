<?php

use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use Database\Seeders\TestAccountsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('the seeder creates 14 ready-to-use test accounts with matches and chats', function () {
    $this->seed(TestAccountsSeeder::class);

    $test1 = User::where('email', 'test1@heartwise.test')->first();
    $test2 = User::where('email', 'test2@heartwise.test')->first();

    expect(User::where('is_sample', true)->count())->toBe(14)
        ->and($test1->isOnboarded())->toBeTrue()
        ->and($test1->isPremium())->toBeFalse()
        ->and($test2->isPremium())->toBeTrue()
        ->and(UserMatch::involving($test1)->count())->toBe(3)
        ->and(Like::where('target_id', $test1->id)->where('liked', true)->count())->toBe(6);

    $this->actingAs($test1)->get(route('messages.index'))->assertOk()->assertSee('Sophia')->assertSee('Mia');
    $this->actingAs($test1)->get(route('discover'))->assertOk();
});

test('running it again resets instead of duplicating', function () {
    $this->seed(TestAccountsSeeder::class);
    $this->seed(TestAccountsSeeder::class);

    expect(User::where('is_sample', true)->count())->toBe(14);
});

test('one command removes every test account', function () {
    $real = User::factory()->discoverable()->create();
    $this->seed(TestAccountsSeeder::class);

    $this->artisan('app:remove-test-accounts')->assertSuccessful();

    expect(User::where('is_sample', true)->count())->toBe(0)
        ->and(UserMatch::count())->toBe(0)
        ->and($real->fresh())->not->toBeNull();
});

test('the test accounts can never be created on the live site', function () {
    app()->detectEnvironment(fn () => 'production');

    (new TestAccountsSeeder)->run();
})->throws(RuntimeException::class);
