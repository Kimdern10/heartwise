<?php

use App\Actions\Dating\LikeUser;
use App\Actions\Dating\SendMessage;
use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Exceptions\DatingActionException;
use App\Models\DateFeedback;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Message;
use App\Models\Photo;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\Compatibility;
use App\Support\Discovery;
use Livewire\Livewire;

function memberWithPrompt(array $profile = []): User
{
    $user = User::factory()->discoverable($profile)->create();
    $user->prompts()->create(['prompt' => 'My simple pleasures', 'answer' => 'Sunday pancakes', 'position' => 0]);

    return $user;
}

test('liking a prompt with a comment turns the comments into the first messages', function () {
    $a = memberWithPrompt();
    $b = memberWithPrompt();

    app(LikeUser::class)->handle($a, $b, true, 'Pancakes are elite', promptId: $b->prompts->first()->id);
    $match = app(LikeUser::class)->handle($b, $a, true, 'Ha, thank you!', photoId: $a->approvedPhotos->first()->id);

    expect($match)->not->toBeNull()
        ->and(Like::where('user_id', $a->id)->first()->prompt_id)->toBe($b->prompts->first()->id)
        ->and(Message::where('match_id', $match->id)->orderBy('id')->pluck('body')->all())
        ->toBe(['Pancakes are elite', 'Ha, thank you!']);
});

test('comments with contact details or money talk are refused', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();

    app(LikeUser::class)->handle($a, $b, true, 'Text me on WhatsApp');
})->throws(DatingActionException::class, 'Keep your first comment friendly');

test('you can only like items that belong to that person', function () {
    [$a, $b, $c] = User::factory()->discoverable()->count(3)->create();

    app(LikeUser::class)->handle($a, $b, true, photoId: $c->approvedPhotos->first()->id);
})->throws(DatingActionException::class, 'photo is not available');

test('one spark per day', function () {
    [$a, $b, $c] = User::factory()->discoverable()->count(3)->create();

    app(LikeUser::class)->handle($a, $b, true, spark: true);
    expect($a->hasSparkLeftToday())->toBeFalse();

    app(LikeUser::class)->handle($a, $c, true, spark: true);
})->throws(DatingActionException::class, 'Sparks');

test('the like modal sends a like from the profile page', function () {
    $me = User::factory()->discoverable()->create();
    $them = memberWithPrompt();
    $this->actingAs($me);

    Livewire::test('pages::people.show', ['user' => $them])
        ->call('openLike', $them->id, 'prompt', $them->prompts->first()->id)
        ->set('likeComment', 'Same!')
        ->call('sendLike')
        ->assertHasNoErrors();

    expect(Like::where('user_id', $me->id)->first())
        ->comment->toBe('Same!')
        ->prompt_id->toBe($them->prompts->first()->id);
});

test('likes you lists people waiting for an answer, sparks first', function () {
    $me = makePremium(User::factory()->discoverable()->create());
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    Like::create(['user_id' => $a->id, 'target_id' => $me->id, 'liked' => true, 'comment' => 'Hi from A']);
    Like::create(['user_id' => $b->id, 'target_id' => $me->id, 'liked' => true, 'is_spark' => true]);

    $this->actingAs($me);

    $page = Livewire::test('pages::activity', ['tab' => 'likes'])->assertSee('Hi from A');
    expect($page->instance()->likes->first()->user_id)->toBe($b->id);

    $page->call('likeBack', $a->id)->call('remove', $b->id);

    expect(UserMatch::between($me, $a)->exists())->toBeTrue()
        ->and(Like::where('user_id', $me->id)->where('target_id', $b->id)->value('liked'))->toBeFalse()
        ->and($page->instance()->likes)->toHaveCount(0);
});

test('profile views are recorded unless browsing privately', function () {
    [$me, $viewer, $private] = User::factory()->discoverable()->count(3)->create();
    $private->profile->update(['browse_privately' => true]);
    makePremium($private);

    $this->actingAs($viewer)->get(route('people.show', $me))->assertOk();
    $this->actingAs($private)->get(route('people.show', $me))->assertOk();

    expect(ProfileView::where('viewed_id', $me->id)->pluck('viewer_id')->all())->toBe([$viewer->id]);

    $this->actingAs(makePremium($me))->get(route('activity', 'views'))->assertOk()->assertSee($viewer->displayName());
});

test('members can favorite someone', function () {
    [$me, $them] = User::factory()->discoverable()->count(2)->create();
    $this->actingAs($me);

    Livewire::test('pages::people.show', ['user' => $them])->call('toggleFavorite');
    expect(Favorite::where('user_id', $me->id)->where('favorite_id', $them->id)->exists())->toBeTrue();

    $this->get(route('activity', 'favorites'))->assertOk()->assertSee($them->displayName());

    Livewire::test('pages::activity', ['tab' => 'favorites'])->call('unfavorite', $them->id);
    expect(Favorite::count())->toBe(0);
});

test('unmatching hides the chat and stops messages', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    $match = UserMatch::createBetween($a, $b);
    $this->actingAs($a);

    Livewire::test('pages::messages.show', ['match' => $match])->call('unmatch')->assertRedirect(route('messages.index'));

    $this->actingAs($b)->get(route('messages.show', $match))->assertNotFound();
    expect(fn () => app(SendMessage::class)->handle($b, $match->fresh(), 'hello?'))->toThrow(DatingActionException::class);
});

test('members can say whether they met', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    $match = UserMatch::createBetween($a, $b);
    $match->forceFill(['created_at' => now()->subDays(5)])->save();
    foreach (range(1, 6) as $i) {
        $match->messages()->create(['sender_id' => $i % 2 ? $a->id : $b->id, 'body' => "msg $i"]);
    }

    $this->actingAs($a);

    Livewire::test('pages::messages.show', ['match' => $match])
        ->assertSee('Have you met')
        ->call('weMet', true, 5)
        ->assertDontSee('Have you met');

    expect(DateFeedback::first())->met->toBeTrue()->rating->toBe(5);
});

test('members can save prompts, details and interests', function () {
    $user = User::factory()->discoverable()->create();
    $this->actingAs($user);

    Livewire::test('profile-details')
        ->set('prompts', [['prompt' => 'My simple pleasures', 'answer' => 'Good coffee']])
        ->set('job_title', 'Nurse')
        ->set('vitals.religion', 'christian')
        ->set('vitals.wants_kids', 'want')
        ->call('toggleInterest', 'Hiking')
        ->call('save')
        ->assertHasNoErrors();

    $profile = $user->profile->fresh();

    expect($user->prompts()->pluck('answer')->all())->toBe(['Good coffee'])
        ->and($profile->religion)->toBe('christian')
        ->and($profile->interests)->toBe(['Hiking'])
        ->and($profile->status)->toBe(ModerationStatus::Pending);
});

test('prompt answers with contact details are refused', function () {
    $this->actingAs(User::factory()->discoverable()->create());

    Livewire::test('profile-details')
        ->set('prompts', [['prompt' => 'My simple pleasures', 'answer' => 'Call me at 555-123-4567']])
        ->call('save')
        ->assertHasErrors('prompts.0.answer');
});

test('dealbreakers filter who you see', function () {
    $me = User::factory()->discoverable(['gender' => Gender::Man, 'seeking' => Seeking::Women])->create();
    User::factory()->discoverable(['display_name' => 'Christian', 'gender' => Gender::Woman, 'seeking' => Seeking::Men, 'religion' => 'christian'])->create();
    User::factory()->discoverable(['display_name' => 'Atheist', 'gender' => Gender::Woman, 'seeking' => Seeking::Men, 'religion' => 'atheist'])->create();

    $this->actingAs($me);

    Livewire::test('pages::preferences')
        ->set('values.religion', ['christian'])
        ->set('dealbreakers', ['religion'])
        ->call('save')
        ->assertHasNoErrors();

    $names = Discovery::for($me->fresh())->get()->map->displayName()->all();

    expect($names)->toBe(['Christian']);
});

test('preferences reject an impossible age range', function () {
    $this->actingAs(User::factory()->discoverable()->create());

    Livewire::test('pages::preferences')->set('ageMin', 50)->set('ageMax', 30)->call('save')->assertHasErrors('ageMin');
});

test('advanced search filters by details and online status', function () {
    $me = makePremium(User::factory()->discoverable(['gender' => Gender::Man, 'seeking' => Seeking::Women])->create());
    User::factory()->discoverable(['display_name' => 'TallOnline', 'gender' => Gender::Woman, 'seeking' => Seeking::Men, 'height_cm' => 178, 'drinking' => 'no'])
        ->create(['last_active_at' => now()]);
    User::factory()->discoverable(['display_name' => 'ShortAway', 'gender' => Gender::Woman, 'seeking' => Seeking::Men, 'height_cm' => 155, 'drinking' => 'yes'])
        ->create(['last_active_at' => now()->subDays(3)]);

    $this->actingAs($me);

    Livewire::test('pages::search')->set('online', true)->assertSee('TallOnline')->assertDontSee('ShortAway');
    Livewire::test('pages::search')->set('heightMin', '66')->assertSee('TallOnline')->assertDontSee('ShortAway');
    Livewire::test('pages::search')->set('drinking', 'yes')->assertSee('ShortAway')->assertDontSee('TallOnline');
});

test('shared goals and plans raise compatibility', function () {
    $me = User::factory()->discoverable(['relationship_goal' => RelationshipGoal::Marriage, 'wants_kids' => 'want', 'interests' => ['Hiking', 'Coffee']])->create();
    $good = User::factory()->discoverable(['relationship_goal' => RelationshipGoal::Marriage, 'wants_kids' => 'want', 'interests' => ['Hiking', 'Coffee']])->create();
    $bad = User::factory()->discoverable(['relationship_goal' => RelationshipGoal::Dating, 'wants_kids' => 'dont_want', 'interests' => []])->create();

    expect(Compatibility::score($me->profile, $good->profile))
        ->toBeGreaterThan(Compatibility::score($me->profile, $bad->profile));
});

test('the new pages render', function (string $route, array $params = []) {
    $me = User::factory()->discoverable(['gender' => Gender::Man, 'seeking' => Seeking::Women])->create();
    memberWithPrompt(['gender' => Gender::Woman, 'seeking' => Seeking::Men]);

    $this->actingAs($me)->get(route($route, $params))->assertOk();
})->with([
    ['discover'],
    ['discover', ['view' => 'swipe']],
    ['search'],
    ['activity'],
    ['activity', ['tab' => 'views']],
    ['activity', ['tab' => 'favorites']],
    ['preferences'],
    ['dashboard'],
    ['my-profile.edit'],
]);

test('discover feed passes and moves on', function () {
    $me = User::factory()->discoverable(['gender' => Gender::Man, 'seeking' => Seeking::Women])->create();
    $her = memberWithPrompt(['gender' => Gender::Woman, 'seeking' => Seeking::Men, 'display_name' => 'Feedy']);
    $this->actingAs($me);

    Livewire::test('pages::discover')
        ->assertSee('Feedy')
        ->assertSee('Sunday pancakes')
        ->call('passOn', $her->id)
        ->assertSee('You’ve seen everyone for now');
});

test('the photo the like points at is not deleted with the like', function () {
    [$a, $b] = User::factory()->discoverable()->count(2)->create();
    $photo = $b->approvedPhotos->first();
    app(LikeUser::class)->handle($a, $b, true, photoId: $photo->id);

    Like::query()->delete();

    expect(Photo::find($photo->id))->not->toBeNull();
});
