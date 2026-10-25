<?php

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\Seeking;
use App\Models\Block;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

test('a member can create a profile that goes to review', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.edit')
        ->set('display_name', 'Alex')
        ->set('gender', Gender::Man->value)
        ->set('seeking', Seeking::Women->value)
        ->set('city', 'Austin')
        ->set('state', 'TX')
        ->set('height_feet', '5')
        ->set('height_inches', '11')
        ->set('bio', 'I love tacos and live music.')
        ->call('save')
        ->assertHasNoErrors();

    $profile = $user->fresh()->profile;

    expect($profile->status)->toBe(ModerationStatus::Pending)
        ->and($profile->height_cm)->toBe(180)
        ->and($profile->heightLabel())->toBe('5′ 11″');
});

test('contact details and payment apps are not allowed in a bio', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::profile.edit')
        ->set('display_name', 'Alex')
        ->set('gender', Gender::Man->value)
        ->set('seeking', Seeking::Women->value)
        ->set('city', 'Austin')
        ->set('state', 'TX')
        ->set('bio', 'Text me on WhatsApp')
        ->call('save')
        ->assertHasErrors('bio');
});

test('an approved profile goes back to review when the bio changes', function () {
    $user = User::factory()->discoverable()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.edit')->set('bio', 'A brand new bio')->call('save');

    expect($user->profile->fresh()->status)->toBe(ModerationStatus::Pending);
});

test('uploaded photos are stored privately as pending', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.edit')
        ->set('uploads', [UploadedFile::fake()->image('me.jpg', 800, 1000)])
        ->assertHasNoErrors();

    $photo = $user->photos()->first();

    expect($photo->status)->toBe(ModerationStatus::Pending)
        ->and($photo->path)->toEndWith('.webp');

    Storage::disk('local')->assertExists([$photo->path, $photo->path_sm]);
});

test('tiny images are rejected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::profile.edit')
        ->set('uploads', [UploadedFile::fake()->image('tiny.jpg', 100, 100)])
        ->assertHasErrors('uploads.0');
});

test('photo access follows moderation and blocking', function () {
    $owner = User::factory()->discoverable()->create();
    $approved = $owner->photos()->first();
    Storage::disk('local')->put($approved->path, 'x');

    $pending = Photo::factory()->for($owner)->create();
    Storage::disk('local')->put($pending->path, 'x');

    $viewer = User::factory()->discoverable()->create();

    $this->actingAs($owner)->get($pending->url())->assertOk();
    $this->actingAs($viewer)->get($approved->url())->assertOk();
    $this->actingAs($viewer)->get($pending->url())->assertNotFound();

    Block::create(['user_id' => $owner->id, 'blocked_id' => $viewer->id]);
    $this->actingAs($viewer)->get($approved->url())->assertNotFound();

    auth()->logout();
    $this->get($approved->url())->assertRedirect(route('login'));
});

test('deleting an account removes photo files', function () {
    $user = User::factory()->discoverable()->create();
    $photo = $user->photos()->first();
    Storage::disk('local')->put($photo->path, 'x');

    $user->delete();

    Storage::disk('local')->assertMissing($photo->path);
    expect(Photo::count())->toBe(0);
});

test('a member can send a selfie for verification', function () {
    $user = User::factory()->discoverable()->create();
    $this->actingAs($user);

    Livewire::test('pages::profile.verify')
        ->set('selfie', UploadedFile::fake()->image('selfie.jpg', 600, 800))
        ->call('submit')
        ->assertHasNoErrors();

    $request = VerificationRequest::first();
    expect($request->status)->toBe(ModerationStatus::Pending);
    Storage::disk('local')->assertExists($request->selfie_path);
});
