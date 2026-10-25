<?php

use App\Enums\AccountStatus;
use App\Enums\Gender;
use App\Enums\Seeking;
use App\Models\Block;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use Livewire\Livewire;

function seeker(): User
{
    return User::factory()->discoverable(['gender' => Gender::Man, 'seeking' => Seeking::Women])->create();
}

function woman(array $profile = [], array $user = []): User
{
    return User::factory()->discoverable(['gender' => Gender::Woman, 'seeking' => Seeking::Men, ...$profile])->create($user);
}

test('members without a profile are sent to create one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('discover'))
        ->assertRedirect(route('my-profile.edit'));
});

test('discover shows compatible members and hides everyone else', function () {
    $me = seeker();
    $match = woman(['display_name' => 'Compatible']);
    woman(['display_name' => 'SeeksWomen', 'seeking' => Seeking::Women]);
    User::factory()->discoverable(['display_name' => 'AnotherMan', 'gender' => Gender::Man, 'seeking' => Seeking::Women])->create();
    woman(['display_name' => 'Hidden', 'is_visible' => false]);
    woman(['display_name' => 'Banned'], ['status' => AccountStatus::Banned]);
    $blocked = woman(['display_name' => 'BlockedMe']);
    Block::create(['user_id' => $blocked->id, 'blocked_id' => $me->id]);

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertSee('Compatible')
        ->assertDontSee('SeeksWomen')
        ->assertDontSee('AnotherMan')
        ->assertDontSee('Hidden')
        ->assertDontSee('Banned')
        ->assertDontSee('BlockedMe');

    $this->get(route('discover'))->assertOk();
});

test('pending profiles and members without approved photos are not shown', function () {
    $me = seeker();
    $pending = User::factory()->create();
    Profile::factory()->for($pending)->create(['display_name' => 'PendingPerson', 'gender' => Gender::Woman, 'seeking' => Seeking::Men]);
    Photo::factory()->for($pending)->approved()->create();

    $noPhoto = User::factory()->create();
    Profile::factory()->for($noPhoto)->approved()->create(['display_name' => 'NoPhotoPerson', 'gender' => Gender::Woman, 'seeking' => Seeking::Men]);

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertDontSee('PendingPerson')
        ->assertDontSee('NoPhotoPerson');
});

test('age and state filters work', function () {
    $me = seeker();
    woman(['display_name' => 'Young', 'state' => 'TX'], ['date_of_birth' => now()->subYears(22)->toDateString()]);
    woman(['display_name' => 'Older', 'state' => 'CA'], ['date_of_birth' => now()->subYears(45)->toDateString()]);

    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->set('ageMin', 40)
        ->assertSee('Older')
        ->assertDontSee('Young')
        ->set('ageMin', 18)
        ->set('state', 'TX')
        ->assertSee('Young')
        ->assertDontSee('Older');
});

test('a blocked member profile page returns 404', function () {
    $me = seeker();
    $other = woman();
    Block::create(['user_id' => $me->id, 'blocked_id' => $other->id]);

    $this->actingAs($me)->get(route('people.show', $other))->assertNotFound();
});

test('a visible member profile page renders', function () {
    $me = seeker();
    $other = woman(['display_name' => 'Jamie']);

    $this->actingAs($me)->get(route('people.show', $other))->assertOk()->assertSee('Jamie');
});
