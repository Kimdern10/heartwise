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

test('search shows only one city: mine by default, or the one I choose', function () {
    $me = personIn('TX', 'Me', gender: 'man', city: 'Austin');
    personIn('TX', 'Austinite', city: 'Austin');
    personIn('TX', 'Houstonian', city: 'Houston');
    personIn('FL', 'Floridian', city: 'Miami');

    $this->actingAs($me);

    Livewire::test('pages::search')
        ->assertSet('state', 'TX')->assertSet('city', 'Austin')
        ->assertSee('Austinite')->assertDontSee('Houstonian')->assertDontSee('Floridian')
        ->set('city', 'houston')
        ->assertSee('Houstonian')->assertDontSee('Austinite')
        ->set('state', 'FL')
        ->assertSet('city', '')
        ->set('city', 'Miami')
        ->assertSee('Floridian')->assertDontSee('Houstonian')
        ->call('useMyCity')
        ->assertSet('city', 'Austin')->assertSee('Austinite');
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
