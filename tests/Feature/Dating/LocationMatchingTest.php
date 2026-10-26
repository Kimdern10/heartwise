<?php

use App\Actions\Dating\LikeUser;
use App\Enums\Gender;
use App\Enums\Seeking;
use App\Exceptions\DatingActionException;
use App\Models\User;
use Livewire\Livewire;

function personIn(string $state, string $name, ?array $coords = null, string $gender = 'woman'): User
{
    $user = User::factory()->discoverable([
        'display_name' => $name,
        'gender' => $gender === 'woman' ? Gender::Woman : Gender::Man,
        'seeking' => $gender === 'woman' ? Seeking::Men : Seeking::Women,
        'state' => $state,
    ])->create();

    if ($coords) {
        $user->profile->forceFill(['latitude' => $coords[0], 'longitude' => $coords[1], 'location_verified_at' => now()])->save();
    }

    return $user;
}

test('discover shows people in my state by default', function () {
    $me = personIn('TX', 'Me', gender: 'man');
    personIn('TX', 'Texan');
    personIn('CA', 'Californian');

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertSet('area', 'state')
        ->assertSee('Texan')
        ->assertDontSee('Californian')
        ->call('setArea', 'anywhere')
        ->call('passOn', User::where('email', '!=', $me->email)->whereHas('profile', fn ($q) => $q->where('display_name', 'Texan'))->first()->id)
        ->assertSee('Californian');
});

test('verified members see people near them first', function () {
    $me = personIn('TX', 'Me', [30.27, -97.74], 'man'); // Austin
    personIn('TX', 'RoundRock', [30.51, -97.68]);        // ~17 miles
    personIn('TX', 'ElPaso', [31.76, -106.49]);          // ~530 miles, same state

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertSet('area', 'near')
        ->assertSee('RoundRock')
        ->assertDontSee('ElPaso')
        ->call('setArea', 'state')
        ->call('passOn', User::whereHas('profile', fn ($q) => $q->where('display_name', 'RoundRock'))->first()->id)
        ->assertSee('ElPaso');
});

test('near me without a verified location falls back to my state', function () {
    $me = personIn('TX', 'Me', gender: 'man');
    personIn('TX', 'Texan');
    personIn('NY', 'NewYorker');

    $this->actingAs($me);

    Livewire::withQueryParams(['area' => 'near'])->test('pages::discover')
        ->assertSee('Turn on your location')
        ->assertSee('Texan')
        ->assertDontSee('NewYorker');
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
