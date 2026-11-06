<?php

use App\Actions\Dating\LikeUser;
use App\Enums\Gender;
use App\Enums\Seeking;
use App\Exceptions\DatingActionException;
use App\Models\User;
use Livewire\Livewire;

function personIn(string $state, string $name, ?array $coords = null, string $gender = 'woman', string $city = 'Austin'): User
{
    $user = User::factory()->discoverable([
        'display_name' => $name,
        'gender' => $gender === 'woman' ? Gender::Woman : Gender::Man,
        'seeking' => $gender === 'woman' ? Seeking::Men : Seeking::Women,
        'state' => $state,
        'city' => $city,
    ])->create();

    if ($coords) {
        $user->profile->forceFill(['latitude' => $coords[0], 'longitude' => $coords[1], 'location_verified_at' => now()])->save();
    }

    return $user;
}

test('discover only shows people in my own city', function () {
    $me = personIn('TX', 'Me', gender: 'man', city: 'Austin');
    $local = personIn('TX', 'Austinite', city: ' austin ');   // different spacing/case still matches
    personIn('TX', 'Houstonian', city: 'Houston');            // same state, other city
    personIn('GA', 'OtherAustin', city: 'Austin');            // same city name, other state

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertSee('People in Austin, TX')
        ->assertSee('Austinite')
        ->assertDontSee('Houstonian')
        ->assertDontSee('OtherAustin')
        ->call('passOn', $local->id)
        ->assertSee('You’ve seen everyone in Austin');
});

test('the daily pick is only shown in discover when it is in my city', function () {
    $me = personIn('TX', 'Me', gender: 'man', city: 'Austin');
    personIn('TX', 'Houstonian', city: 'Houston');

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertDontSee('Houstonian')
        ->assertDontSee('Most Compatible today');
});

test('search can filter by distance or my state', function () {
    $me = personIn('TX', 'Me', [30.27, -97.74], 'man');
    personIn('TX', 'Close', [30.30, -97.70]);
    personIn('TX', 'FarTexan', [31.76, -106.49]);
    personIn('FL', 'Floridian', [25.76, -80.19]);

    $this->actingAs($me);

    Livewire::test('pages::search')->set('distance', '25')
        ->assertSee('Close')->assertDontSee('FarTexan')->assertDontSee('Floridian');
    Livewire::test('pages::search')->set('distance', 'state')
        ->assertSee('Close')->assertSee('FarTexan')->assertDontSee('Floridian');
});

test('free members get 3 likes a day and no sparks', function () {
    $me = personIn('TX', 'Me', gender: 'man');
    $others = collect(range(1, 4))->map(fn ($i) => personIn('TX', "P$i"));

    expect($me->likesLeftToday())->toBe(3)
        ->and($me->hasSparkLeftToday())->toBeFalse();

    foreach ($others->take(3) as $other) {
        app(LikeUser::class)->handle($me, $other);
    }

    expect($me->likesLeftToday())->toBe(0)
        ->and(fn () => app(LikeUser::class)->handle($me, $others[3]))->toThrow(DatingActionException::class, 'Premium');

    $this->actingAs($me)->get(route('discover'))->assertSee('No free likes left today');

    $premium = makePremium($me);
    expect($premium->likesLeftToday())->toBeGreaterThan(100)
        ->and($premium->hasSparkLeftToday())->toBeTrue();
});
