<?php

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Models\Photo;
use App\Models\Profile;
use App\Support\ImageProcessor;
use App\Support\MessageScreener;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('My profile')] class extends Component {
    use WithFileUploads;

    public string $display_name = '';
    public string $gender = '';
    public string $seeking = '';
    public string $relationship_goal = '';
    public string $bio = '';
    public string $city = '';
    public string $state = '';
    public string $height_feet = '';
    public string $height_inches = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    public function mount(): void
    {
        $profile = Auth::user()->profile;

        if ($profile) {
            $this->fill([
                'display_name' => $profile->display_name,
                'gender' => $profile->gender->value,
                'seeking' => $profile->seeking->value,
                'relationship_goal' => $profile->relationship_goal?->value ?? '',
                'bio' => (string) $profile->bio,
                'city' => $profile->city,
                'state' => $profile->state,
            ]);

            if ($profile->height_cm) {
                $inches = (int) round($profile->height_cm / 2.54);
                $this->height_feet = (string) intdiv($inches, 12);
                $this->height_inches = (string) ($inches % 12);
            }
        } else {
            $this->display_name = str(Auth::user()->name)->before(' ')->limit(40, '')->toString();
        }
    }

    #[Computed]
    public function profile(): ?Profile
    {
        return Auth::user()->profile()->first();
    }

    #[Computed]
    public function photos()
    {
        return Auth::user()->photos()->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:40'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'seeking' => ['required', Rule::enum(Seeking::class)],
            'relationship_goal' => ['nullable', Rule::enum(RelationshipGoal::class)],
            'bio' => ['nullable', 'string', 'max:1500'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', Rule::in(array_keys(config('dating.us_states')))],
            'height_feet' => ['nullable', 'integer', 'between:4,7'],
            'height_inches' => ['nullable', 'integer', 'between:0,11'],
        ]);

        if (filled($this->bio) && (new MessageScreener)->flag($this->bio)) {
            $this->addError('bio', __('Please keep contact details, links, other apps and money talk out of your bio. You can share them in chat when you feel ready.'));

            return;
        }

        $data = [
            'display_name' => trim($validated['display_name']),
            'gender' => $validated['gender'],
            'seeking' => $validated['seeking'],
            'relationship_goal' => $validated['relationship_goal'] ?: null,
            'bio' => trim($validated['bio']) ?: null,
            'city' => trim($validated['city']),
            'state' => $validated['state'],
            'height_cm' => filled($this->height_feet)
                ? (int) round(((int) $this->height_feet * 12 + (int) $this->height_inches) * 2.54)
                : null,
        ];

        $profile = $this->profile ?? new Profile(['user_id' => Auth::id()]);
        $isNew = ! $profile->exists;
        $profile->fill($data);
        $profile->user_id = Auth::id();

        // Moving to another state means the old location check no longer applies.
        if ($profile->isDirty('state')) {
            $profile->forceFill(['latitude' => null, 'longitude' => null, 'location_verified_at' => null]);
        }

        // New profiles, and changes to name or bio, go to the moderators first.
        if ($isNew || $profile->isDirty(['display_name', 'bio'])) {
            $profile->status = ModerationStatus::Pending;
            $profile->rejection_reason = null;
        }

        $profile->save();
        unset($this->profile);

        Flux::toast(
            variant: 'success',
            text: $profile->status === ModerationStatus::Pending
                ? __('Saved. Our team will review your profile shortly.')
                : __('Profile updated.'),
        );
    }

    public function updatedUploads(): void
    {
        $max = config('dating.limits.max_photos');

        $this->validate([
            'uploads' => ['array', 'max:'.$max],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('dating.limits.max_photo_kb'), 'dimensions:min_width=300,min_height=300'],
        ], [
            'uploads.*.dimensions' => __('Photos must be at least 300 × 300 pixels.'),
        ]);

        $existing = $this->photos->count();
        $processor = app(ImageProcessor::class);

        foreach ($this->uploads as $upload) {
            if ($existing >= $max) {
                Flux::toast(variant: 'warning', text: __('You can have up to :max photos.', ['max' => $max]));
                break;
            }

            $paths = $processor->storePhoto($upload, 'photos/'.Auth::id());

            Auth::user()->photos()->create([...$paths, 'position' => $existing++]);
        }

        $this->uploads = [];
        unset($this->photos);

        Flux::toast(variant: 'success', text: __('Photo uploaded. It will appear on your profile once approved.'));
    }

    /**
     * Called from the browser with the device's GPS position.
     */
    public function verifyLocation(float $latitude, float $longitude): void
    {
        $profile = $this->profile;

        if (! $profile) {
            Flux::toast(variant: 'warning', text: __('Save your profile first.'));

            return;
        }

        if (! RateLimiter::attempt('location:'.Auth::id(), 10, fn () => true, 3600)) {
            Flux::toast(variant: 'warning', text: __('Too many tries. Please wait an hour.'));

            return;
        }

        if (abs($latitude) > 90 || abs($longitude) > 180 || ! $profile->verifyLocation($latitude, $longitude)) {
            $this->addError('location', __('Your device doesn’t seem to be in :state right now. If you moved, update your state above and save, then try again.', [
                'state' => config('dating.us_states.'.$profile->state),
            ]));

            return;
        }

        unset($this->profile);
        Flux::toast(variant: 'success', text: __('Location verified! Your profile now shows the green pin.'));
    }

    public function removeLocation(): void
    {
        $this->profile?->forceFill(['latitude' => null, 'longitude' => null, 'location_verified_at' => null])->save();
        unset($this->profile);
        Flux::toast(text: __('Location removed.'));
    }

    public function makePrimary(int $photoId): void
    {
        $photos = $this->photos;
        $chosen = $photos->firstWhere('id', $photoId);

        abort_unless($chosen, 404);

        $position = 1;
        foreach ($photos as $photo) {
            $photo->update(['position' => $photo->is($chosen) ? 0 : $position++]);
        }

        unset($this->photos);
    }

    public function deletePhoto(int $photoId): void
    {
        Photo::query()->where('user_id', Auth::id())->findOrFail($photoId)->delete();

        unset($this->photos);

        Flux::toast(text: __('Photo deleted.'));
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('My profile') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Be yourself. Real photos and an honest bio get the best matches.') }}</flux:subheading>
    </div>

    <x-auth-session-status :status="session('status')" />

    @if ($this->profile)
        @php($status = $this->profile->status)
        <flux:callout
            :icon="$status === \App\Enums\ModerationStatus::Approved ? 'check-circle' : ($status === \App\Enums\ModerationStatus::Pending ? 'clock' : 'exclamation-triangle')"
            :color="$status->color()"
        >
            <flux:callout.heading>{{ __('Profile status: :status', ['status' => $status->label()]) }}</flux:callout.heading>
            <flux:callout.text>
                @if ($status === \App\Enums\ModerationStatus::Approved)
                    {{ __('Your profile is live. Other members can see it while it is visible and has at least one approved photo.') }}
                @elseif ($status === \App\Enums\ModerationStatus::Pending)
                    {{ __('A moderator checks every new profile to keep fake accounts out. This usually takes less than a day.') }}
                @else
                    {{ __('Please update your profile: :reason', ['reason' => $this->profile->rejection_reason ?: __('it did not meet our guidelines.')]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Photos --}}
    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
            <flux:text>{{ __('Add 1–:max clear photos of yourself. No group shots as your first photo, no photos of other people, and nothing explicit. Location data is removed from every photo.', ['max' => config('dating.limits.max_photos')]) }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($this->photos as $photo)
                <div wire:key="photo-{{ $photo->id }}" class="group relative aspect-[3/4] overflow-hidden rounded-2xl shadow-md transition hover:shadow-xl">
                    <img src="{{ $photo->url(true) }}" alt="" class="size-full object-cover" />

                    <div class="absolute start-2 top-2 flex gap-1">
                        @if ($loop->first)
                            <flux:badge size="sm" color="rose">{{ __('Main') }}</flux:badge>
                        @endif
                        <flux:badge size="sm" :color="$photo->status->color()">{{ $photo->status->label() }}</flux:badge>
                    </div>

                    @if ($photo->status === \App\Enums\ModerationStatus::Rejected && $photo->rejection_reason)
                        <div class="absolute inset-x-0 bottom-12 bg-red-600/90 px-2 py-1 text-xs text-white">{{ $photo->rejection_reason }}</div>
                    @endif

                    <div class="absolute inset-x-2 bottom-2 flex justify-between gap-2">
                        @unless ($loop->first)
                            <flux:button size="xs" wire:click="makePrimary({{ $photo->id }})">{{ __('Make main') }}</flux:button>
                        @endunless
                        <flux:button size="xs" variant="danger" icon="trash" class="ms-auto"
                            wire:click="deletePhoto({{ $photo->id }})"
                            data-confirm-danger data-confirm="{{ __('Delete this photo?') }}"
                            :aria-label="__('Delete photo')" />
                    </div>
                </div>
            @endforeach

            @if ($this->photos->count() < config('dating.limits.max_photos'))
                <label class="flex aspect-[3/4] cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 text-zinc-500 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-600 dark:border-zinc-700 dark:hover:bg-white/5">
                    <flux:icon.camera class="size-8" />
                    <span class="text-sm" wire:loading.remove wire:target="uploads">{{ __('Add photos') }}</span>
                    <span class="text-sm" wire:loading wire:target="uploads">{{ __('Uploading…') }}</span>
                    <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" data-test="photo-upload" />
                </label>
            @endif
        </div>

        <flux:error name="uploads" />
        <flux:error name="uploads.*" />
    </flux:card>

    {{-- Location verification --}}
    @if ($this->profile)
        <div id="location" class="hw-card scroll-mt-24 p-6"
            x-data="{
                busy: false,
                problem: '',
                locate() {
                    this.problem = '';
                    if (! navigator.geolocation) { this.problem = @js(__('Your browser does not support location.')); return; }
                    this.busy = true;
                    navigator.geolocation.getCurrentPosition(
                        (pos) => { $wire.verifyLocation(pos.coords.latitude, pos.coords.longitude).then(() => this.busy = false) },
                        (err) => {
                            this.busy = false;
                            this.problem = err.code === 1
                                ? @js(__('Location is turned off for this site. Allow location in your browser settings (the icon next to the address bar), then try again.'))
                                : @js(__('We could not get your location. Move near a window or turn on Wi-Fi, then try again.'));
                        },
                        { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 },
                    );
                },
            }">
            <div class="flex flex-wrap items-start gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $this->profile->isLocationVerified() ? 'bg-emerald-500 text-white' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10' }}">
                    <flux:icon.map-pin :variant="$this->profile->isLocationVerified() ? 'solid' : 'outline'" class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ __('Verify your location') }}</flux:heading>
                    @if ($this->profile->isLocationVerified())
                        <flux:text>{{ __('Verified in :state on :date. Your profile shows a green pin, and matches see roughly how far away you are.', ['state' => config('dating.us_states.'.$this->profile->state), 'date' => $this->profile->location_verified_at->toFormattedDateString()]) }}</flux:text>
                    @else
                        <flux:text>{{ __('Turn on your device location once to prove you really live in :state. It helps stop scammers pretending to be local.', ['state' => config('dating.us_states.'.$this->profile->state)]) }}</flux:text>
                    @endif
                    <flux:text class="mt-1 text-xs">{{ __('We only keep a rounded position (about 1 km / half a mile) — never your exact address — and never show it to anyone.') }}</flux:text>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <flux:button variant="primary" icon="map-pin" x-on:click="locate()" x-bind:disabled="busy" data-test="verify-location">
                            <span x-show="! busy">{{ $this->profile->isLocationVerified() ? __('Check again') : __('Use my location') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Checking…') }}</span>
                        </flux:button>
                        @if ($this->profile->isLocationVerified())
                            <flux:button variant="ghost" size="sm" wire:click="removeLocation">{{ __('Remove') }}</flux:button>
                        @endif
                    </div>

                    <p x-show="problem" x-text="problem" x-cloak class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"></p>
                    <flux:error name="location" class="mt-3" />
                </div>
            </div>
        </div>
    @endif

    {{-- Details --}}
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ __('About you') }}</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="display_name" :label="__('Display name')" :description:trailing="__('Your first name or a nickname. No contact details.')" required maxlength="40" />

                <flux:select wire:model="relationship_goal" :label="__('Looking for')">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach (\App\Enums\RelationshipGoal::cases() as $goal)
                        <flux:select.option :value="$goal->value">{{ $goal->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="gender" :label="__('I am a')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach (\App\Enums\Gender::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="seeking" :label="__('Interested in')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach (\App\Enums\Seeking::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="city" :label="__('City')" required maxlength="80" />

                <flux:select wire:model="state" :label="__('State')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach (config('dating.us_states') as $code => $name)
                        <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-3">
                    <flux:select wire:model="height_feet" :label="__('Height (ft)')">
                        <flux:select.option value="">—</flux:select.option>
                        @foreach (range(4, 7) as $ft)
                            <flux:select.option :value="$ft">{{ $ft }} ft</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="height_inches" :label="__('(in)')">
                        <flux:select.option value="">—</flux:select.option>
                        @foreach (range(0, 11) as $in)
                            <flux:select.option :value="$in">{{ $in }} in</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <flux:textarea wire:model="bio" :label="__('About me')" rows="5" maxlength="1500"
                :placeholder="__('What do you enjoy? What are you hoping to find here?')" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="save-profile-button">{{ __('Save profile') }}</flux:button>
                @if ($this->profile && ! $this->profile->photo_verified_at)
                    <flux:link :href="route('my-profile.verify')" wire:navigate>{{ __('Get the verified badge') }}</flux:link>
                @endif
            </div>
        </form>
    </flux:card>

    @if ($this->profile)
        <livewire:profile-details />

        <div class="hw-card flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <flux:heading size="lg" class="font-display !text-2xl">{{ __('Dating preferences') }}</flux:heading>
                <flux:text>{{ __('Age range, distance and dealbreakers.') }}</flux:text>
            </div>
            <flux:button icon="adjustments-horizontal" :href="route('preferences')" wire:navigate>{{ __('Edit preferences') }}</flux:button>
        </div>

        <div class="text-center">
            <flux:button variant="ghost" icon="eye" :href="route('people.show', auth()->user())" wire:navigate>{{ __('Preview my profile') }}</flux:button>
        </div>
    @endif
</section>
