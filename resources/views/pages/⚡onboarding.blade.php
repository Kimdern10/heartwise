<?php

use App\Enums\Gender;
use App\Enums\ModerationStatus;
use App\Enums\RelationshipGoal;
use App\Enums\Seeking;
use App\Models\Photo;
use App\Models\Profile;
use App\Support\Geo;
use App\Support\ImageProcessor;
use App\Support\MessageScreener;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::onboarding')] #[Title('Create your profile')] class extends Component {
    use WithFileUploads;

    public string $step = '';

    // Answers
    public string $displayName = '';
    public string $dob = '';
    public bool $termsAccepted = false;
    public string $gender = '';
    public string $seeking = '';
    public string $state = '';
    public string $city = '';
    public ?float $lat = null;
    public ?float $lng = null;
    public string $goal = '';
    public string $heightFeet = '';
    public string $heightInches = '';
    public string $hasKids = '';
    public string $wantsKids = '';
    public string $jobTitle = '';
    public string $school = '';
    public string $education = '';
    public string $religion = '';
    public string $politics = '';
    public string $drinking = '';
    public string $smoking = '';

    /** @var list<string> */
    public array $interests = [];

    /** @var list<array{prompt: string, answer: string}> */
    public array $prompts = [['prompt' => '', 'answer' => ''], ['prompt' => '', 'answer' => ''], ['prompt' => '', 'answer' => '']];

    public string $bio = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** Steps that may be skipped. */
    private const OPTIONAL = ['height', 'kids', 'work', 'beliefs', 'habits', 'interests', 'bio'];

    private const SAVED = [
        'step', 'displayName', 'dob', 'termsAccepted', 'gender', 'seeking', 'state', 'city', 'lat', 'lng', 'goal',
        'heightFeet', 'heightInches', 'hasKids', 'wantsKids', 'jobTitle', 'school', 'education', 'religion',
        'politics', 'drinking', 'smoking', 'interests', 'prompts', 'bio',
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->isOnboarded()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        // Only prefill a real name (e.g. from Google), not the email-based placeholder.
        if (str_contains($user->name, ' ')) {
            $this->displayName = str($user->name)->before(' ')->limit(40, '')->toString();
        }
        $this->fill(Cache::get($this->draftKey(), []));

        if (! in_array($this->step, $this->steps, true)) {
            $this->step = $this->steps[0];
        }
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function steps(): array
    {
        $user = Auth::user();

        return array_values(array_filter([
            'name',
            $user->date_of_birth ? null : 'birthday',
            $user->terms_accepted_at ? null : 'terms',
            'gender', 'seeking', 'location', 'goal', 'height', 'kids', 'work', 'beliefs', 'habits',
            'interests', 'photos', 'prompts', 'bio', 'done',
        ]));
    }

    #[Computed]
    public function photos()
    {
        return Auth::user()->photos()->get();
    }

    public function isOptional(): bool
    {
        return in_array($this->step, self::OPTIONAL, true);
    }

    public function next(): void
    {
        if (! $this->validateStep()) {
            return;
        }

        $this->move(1);
    }

    public function skip(): void
    {
        if ($this->isOptional()) {
            $this->resetErrorBag();
            $this->move(1);
        }
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->move(-1);
    }

    private function move(int $by): void
    {
        $index = array_search($this->step, $this->steps, true);
        $this->step = $this->steps[max(0, min(count($this->steps) - 1, $index + $by))];
        $this->saveDraft();
        $this->dispatch('step-changed');
    }

    private function validateStep(): bool
    {
        $vitals = config('profile_options.vitals');
        $minAge = config('dating.minimum_age');

        $rules = match ($this->step) {
            'name' => ['displayName' => ['required', 'string', 'min:2', 'max:40']],
            'birthday' => ['dob' => ['required', 'date', 'before_or_equal:'.now()->subYears($minAge)->toDateString(), 'after:'.now()->subYears(100)->toDateString()]],
            'terms' => ['termsAccepted' => ['accepted']],
            'gender' => ['gender' => ['required', Rule::enum(Gender::class)]],
            'seeking' => ['seeking' => ['required', Rule::enum(Seeking::class)]],
            'location' => ['state' => ['required', Rule::in(array_keys(config('dating.us_states')))], 'city' => ['required', 'string', 'max:80']],
            'goal' => ['goal' => ['required', Rule::enum(RelationshipGoal::class)]],
            'height' => ['heightFeet' => ['nullable', 'integer', 'between:4,7'], 'heightInches' => ['nullable', 'integer', 'between:0,11']],
            'kids' => ['hasKids' => ['nullable', Rule::in(array_keys($vitals['has_kids']['options']))], 'wantsKids' => ['nullable', Rule::in(array_keys($vitals['wants_kids']['options']))]],
            'work' => ['jobTitle' => ['nullable', 'string', 'max:80'], 'school' => ['nullable', 'string', 'max:80'], 'education' => ['nullable', Rule::in(array_keys($vitals['education']['options']))]],
            'beliefs' => ['religion' => ['nullable', Rule::in(array_keys($vitals['religion']['options']))], 'politics' => ['nullable', Rule::in(array_keys($vitals['politics']['options']))]],
            'habits' => ['drinking' => ['nullable', Rule::in(array_keys($vitals['drinking']['options']))], 'smoking' => ['nullable', Rule::in(array_keys($vitals['smoking']['options']))]],
            'interests' => ['interests' => ['array', 'max:'.config('profile_options.max_interests')], 'interests.*' => [Rule::in(config('profile_options.interests'))]],
            'prompts' => [
                'prompts.*.prompt' => ['nullable', 'required_with:prompts.*.answer', Rule::in(config('profile_options.prompts')), 'distinct'],
                'prompts.*.answer' => ['nullable', 'required_with:prompts.*.prompt', 'string', 'max:300'],
            ],
            'bio' => ['bio' => ['nullable', 'string', 'max:1500']],
            default => [],
        };

        // Steps like "photos" have no form fields; Livewire refuses an empty rule list.
        if ($rules !== []) $this->validate($rules, [
            'dob.before_or_equal' => __('You must be at least :age to join.', ['age' => $minAge]),
            'termsAccepted.accepted' => __('Please confirm to continue.'),
            'prompts.*.prompt.distinct' => __('Pick a different prompt for each answer.'),
            'prompts.*.answer.required_with' => __('Write an answer, or clear the prompt.'),
            'prompts.*.prompt.required_with' => __('Choose a prompt for this answer.'),
        ]);

        // Keep contact details, links and money talk out of public text.
        $screener = new MessageScreener;
        $texts = match ($this->step) {
            'name' => ['displayName' => $this->displayName],
            'work' => ['jobTitle' => $this->jobTitle, 'school' => $this->school],
            'bio' => ['bio' => $this->bio],
            'prompts' => collect($this->prompts)->mapWithKeys(fn ($p, $i) => ["prompts.$i.answer" => $p['answer']])->all(),
            default => [],
        };

        foreach ($texts as $field => $text) {
            if (filled($text) && $screener->flag($text)) {
                $this->addError($field, __('Please leave out contact details, links, other apps and money talk.'));

                return false;
            }
        }

        if ($this->step === 'photos' && $this->photos->isEmpty()) {
            $this->addError('uploads', __('Add at least one photo of yourself to continue.'));

            return false;
        }

        if ($this->step === 'prompts' && collect($this->prompts)->filter(fn ($p) => filled($p['prompt']) && filled($p['answer']))->isEmpty()) {
            $this->addError('prompts', __('Answer at least one prompt — it’s how people start conversations.'));

            return false;
        }

        return true;
    }

    /**
     * Called from the browser with the device's GPS position.
     */
    public function useLocation(float $lat, float $lng): void
    {
        $state = Geo::stateFor($lat, $lng);

        if (! $state) {
            $this->addError('state', __('Your device location appears to be outside the US. Heartwise is for people living in the United States.'));

            return;
        }

        $this->state = $state;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->resetErrorBag('state');
        $this->saveDraft();
    }

    public function toggleInterest(string $interest): void
    {
        if (in_array($interest, $this->interests, true)) {
            $this->interests = array_values(array_diff($this->interests, [$interest]));
        } elseif (count($this->interests) < config('profile_options.max_interests') && in_array($interest, config('profile_options.interests'), true)) {
            $this->interests[] = $interest;
        }
    }

    public function updatedUploads(): void
    {
        $max = config('dating.limits.max_photos');

        $this->validate([
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('dating.limits.max_photo_kb'), 'dimensions:min_width=300,min_height=300'],
        ], ['uploads.*.dimensions' => __('Photos must be at least 300 × 300 pixels.')]);

        $count = $this->photos->count();

        foreach ($this->uploads as $upload) {
            if ($count >= $max) {
                break;
            }

            Auth::user()->photos()->create([...app(ImageProcessor::class)->storePhoto($upload, 'photos/'.Auth::id()), 'position' => $count++]);
        }

        $this->uploads = [];
        unset($this->photos);
        $this->resetErrorBag('uploads');
    }

    public function deletePhoto(int $photoId): void
    {
        Photo::query()->where('user_id', Auth::id())->findOrFail($photoId)->delete();
        unset($this->photos);
    }

    public function finish(): void
    {
        $user = Auth::user();

        // Re-check every required step in case someone skipped ahead.
        foreach ($this->steps as $step) {
            if ($step === 'done' || in_array($step, self::OPTIONAL, true)) {
                continue;
            }

            $this->step = $step;

            if (! $this->validateStep()) {
                return;
            }
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(array_filter([
                'name' => str_contains($user->name, ' ') ? null : trim($this->displayName),
                'date_of_birth' => $this->dob ?: null,
                'terms_accepted_at' => $this->termsAccepted && ! $user->terms_accepted_at ? now() : null,
            ]))->save();

            $profile = $user->profile ?? new Profile;
            $profile->user_id = $user->id;
            $profile->fill([
                'display_name' => trim($this->displayName),
                'gender' => $this->gender,
                'seeking' => $this->seeking,
                'relationship_goal' => $this->goal,
                'bio' => trim($this->bio) ?: null,
                'city' => trim($this->city),
                'state' => $this->state,
                'height_cm' => filled($this->heightFeet) ? (int) round(((int) $this->heightFeet * 12 + (int) $this->heightInches) * 2.54) : null,
                'job_title' => trim($this->jobTitle) ?: null,
                'school' => trim($this->school) ?: null,
                'education' => $this->education ?: null,
                'religion' => $this->religion ?: null,
                'politics' => $this->politics ?: null,
                'has_kids' => $this->hasKids ?: null,
                'wants_kids' => $this->wantsKids ?: null,
                'drinking' => $this->drinking ?: null,
                'smoking' => $this->smoking ?: null,
                'interests' => $this->interests ?: null,
            ]);
            $profile->status = ModerationStatus::Pending;
            $profile->save();

            $user->prompts()->delete();
            collect($this->prompts)
                ->filter(fn ($p) => filled($p['prompt']) && filled($p['answer']))
                ->values()
                ->each(fn ($p, $i) => $user->prompts()->create(['prompt' => $p['prompt'], 'answer' => trim($p['answer']), 'position' => $i]));

            if ($this->lat !== null && $this->lng !== null) {
                $profile->verifyLocation($this->lat, $this->lng);
            }

            $user->forceFill(['onboarded_at' => now()])->save();
        });

        Cache::forget($this->draftKey());

        Flux::toast(variant: 'success', heading: __('Welcome to :app!', ['app' => config('app.name')]), text: __('Your profile is with our team for a quick review.'));
        $this->redirectRoute('dashboard', navigate: true);
    }

    private function saveDraft(): void
    {
        Cache::put($this->draftKey(), $this->only(self::SAVED), now()->addDays(14));
    }

    private function draftKey(): string
    {
        return 'onboarding:'.Auth::id();
    }
}; ?>

@php
    $index = array_search($step, $this->steps, true);
    $total = count($this->steps) - 1;
    $vitals = config('profile_options.vitals');

    $titles = [
        'name' => [__('What’s your first name?'), __('This is how you’ll appear to others. Use your real first name.')],
        'birthday' => [__('What’s your date of birth?'), __('You must be 18 or older. We only show your age, never your birthday.')],
        'terms' => [__('A few house rules'), __('We’re building a safe place to meet real people.')],
        'gender' => [__('Which gender best describes you?'), null],
        'seeking' => [__('Who would you like to meet?'), null],
        'location' => [__('Where do you live?'), __('Only your city and state are shown. Using your location also earns the green “verified location” pin.')],
        'goal' => [__('What are you looking for?'), __('Be honest — it helps everyone find the right person.')],
        'height' => [__('How tall are you?'), null],
        'kids' => [__('Children & family plans'), null],
        'work' => [__('What do you do?'), null],
        'beliefs' => [__('Religion & politics'), __('Share as much or as little as you like.')],
        'habits' => [__('Drinking & smoking'), null],
        'interests' => [__('What are you into?'), __('Pick up to :n.', ['n' => config('profile_options.max_interests')])],
        'photos' => [__('Add your photos'), __('Add at least 1 (we recommend 3–6). Clear photos of just you, smiling, get the most likes.')],
        'prompts' => [__('Write your prompts'), __('Answer 1 to 3. Good answers give people an easy way to start a conversation.')],
        'bio' => [__('Anything else to add?'), __('A short bio. Optional.')],
        'done' => [__('You’re all set!'), __('Our team checks every new profile to keep fakes out — usually within a day.')],
    ];
@endphp

<div class="flex flex-1 flex-col" x-data x-on:step-changed.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    {{-- Progress --}}
    <div class="mb-8 flex items-center gap-3">
        @if ($index > 0 && $step !== 'done')
            <button type="button" wire:click="back" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white shadow ring-1 ring-zinc-200 transition hover:scale-105 dark:bg-zinc-900 dark:ring-zinc-800" aria-label="{{ __('Back') }}">
                <flux:icon.arrow-left class="size-4" />
            </button>
        @endif
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-zinc-900 transition-all duration-500 dark:bg-white" style="width: {{ (int) round($index / max(1, $total) * 100) }}%"></div>
        </div>
        <span class="text-xs font-semibold text-zinc-500">{{ min($index + 1, $total) }}/{{ $total }}</span>
    </div>

    <form wire:submit="{{ $step === 'done' ? 'finish' : 'next' }}" class="flex flex-1 flex-col" wire:key="step-{{ $step }}">
        <div class="animate-fade-up">
            <h1 class="font-display text-4xl leading-tight">{{ $titles[$step][0] }}</h1>
            @if ($titles[$step][1])
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $titles[$step][1] }}</p>
            @endif
        </div>

        <div class="mt-8 flex-1 space-y-4 animate-fade-up [animation-delay:80ms]">
            @switch($step)
                @case('name')
                    <flux:input wire:model="displayName" maxlength="40" class="[&_input]:!h-14 [&_input]:!text-xl" autofocus :placeholder="__('First name')" />
                    <flux:error name="displayName" />
                    @break

                @case('birthday')
                    <flux:input type="date" wire:model="dob" max="{{ now()->subYears(18)->toDateString() }}" class="[&_input]:!h-14 [&_input]:!text-xl" />
                    <flux:error name="dob" />
                    @break

                @case('terms')
                    <div class="hw-card space-y-3 p-5 text-sm">
                        @foreach ([__('I am 18 or older.'), __('I will use real photos of myself and be honest about who I am.'), __('I will never ask anyone for money, gift cards or crypto.'), __('I will treat people with respect, and report anyone who doesn’t.')] as $rule)
                            <p class="flex items-start gap-2"><flux:icon.check-circle variant="solid" class="size-5 shrink-0 text-emerald-500" />{{ $rule }}</p>
                        @endforeach
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="termsAccepted" class="mt-0.5 size-4 accent-zinc-900" />
                        <span>{{ __('I agree to the') }} <a href="{{ route('legal.terms') }}" target="_blank" class="underline">{{ __('Terms') }}</a>, <a href="{{ route('legal.privacy') }}" target="_blank" class="underline">{{ __('Privacy Policy') }}</a> {{ __('and') }} <a href="{{ route('legal.guidelines') }}" target="_blank" class="underline">{{ __('Community Guidelines') }}</a>.</span>
                    </label>
                    <flux:error name="termsAccepted" />
                    @break

                @case('gender')
                    @include('partials.onboarding-choices', ['model' => 'gender', 'options' => collect(\App\Enums\Gender::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all()])
                    @break

                @case('seeking')
                    @include('partials.onboarding-choices', ['model' => 'seeking', 'options' => collect(\App\Enums\Seeking::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()])
                    @break

                @case('location')
                    <div x-data="{ busy: false, problem: '' }" class="space-y-4">
                        <button type="button" x-bind:disabled="busy"
                            x-on:click="busy = true; problem = ''; navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => { $wire.useLocation(p.coords.latitude, p.coords.longitude).then(() => busy = false) }, e => { busy = false; problem = e.code === 1 ? @js(__('Location is blocked for this site. You can allow it in your browser, or just pick your state below.')) : @js(__('We couldn’t get your location — pick your state below.')) }, { timeout: 15000 }) : (busy = false)"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-zinc-900 px-4 py-4 font-semibold text-white transition hover:scale-[1.01] dark:bg-white dark:text-zinc-900">
                            <flux:icon.map-pin class="size-5" />
                            <span x-show="! busy">{{ $lat ? __('Location found ✓ — use it again') : __('Use my current location') }}</span>
                            <span x-show="busy" x-cloak>{{ __('Finding you…') }}</span>
                        </button>
                        <p x-show="problem" x-text="problem" x-cloak class="text-sm text-amber-700 dark:text-amber-300"></p>

                        <flux:select wire:model="state" :label="__('State')">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach (config('dating.us_states') as $code => $name)
                                <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="state" />
                        <flux:input wire:model="city" :label="__('City')" maxlength="80" />
                        <flux:error name="city" />
                    </div>
                    @break

                @case('goal')
                    @include('partials.onboarding-choices', ['model' => 'goal', 'options' => collect(\App\Enums\RelationshipGoal::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all()])
                    @break

                @case('height')
                    <div class="grid grid-cols-2 gap-3">
                        <flux:select wire:model="heightFeet" :label="__('Feet')">
                            <flux:select.option value="">—</flux:select.option>
                            @foreach (range(4, 7) as $ft)<flux:select.option :value="$ft">{{ $ft }} ft</flux:select.option>@endforeach
                        </flux:select>
                        <flux:select wire:model="heightInches" :label="__('Inches')">
                            <flux:select.option value="">—</flux:select.option>
                            @foreach (range(0, 11) as $in)<flux:select.option :value="$in">{{ $in }} in</flux:select.option>@endforeach
                        </flux:select>
                    </div>
                    @break

                @case('kids')
                    <p class="text-sm font-semibold">{{ __('Do you have children?') }}</p>
                    @include('partials.onboarding-choices', ['model' => 'hasKids', 'options' => $vitals['has_kids']['options']])
                    <p class="pt-2 text-sm font-semibold">{{ __('Do you want children?') }}</p>
                    @include('partials.onboarding-choices', ['model' => 'wantsKids', 'options' => $vitals['wants_kids']['options']])
                    @break

                @case('work')
                    <flux:input wire:model="jobTitle" :label="__('Job title')" maxlength="80" icon="briefcase" />
                    <flux:error name="jobTitle" />
                    <flux:input wire:model="school" :label="__('School')" maxlength="80" icon="academic-cap" />
                    <flux:error name="school" />
                    <flux:select wire:model="education" :label="__('Highest education')">
                        <flux:select.option value="">{{ __('Prefer not to say') }}</flux:select.option>
                        @foreach ($vitals['education']['options'] as $value => $label)<flux:select.option :value="$value">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                    @break

                @case('beliefs')
                    <flux:select wire:model="religion" :label="__('Religion')">
                        <flux:select.option value="">{{ __('Prefer not to say') }}</flux:select.option>
                        @foreach ($vitals['religion']['options'] as $value => $label)<flux:select.option :value="$value">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:select wire:model="politics" :label="__('Politics')">
                        <flux:select.option value="">{{ __('Prefer not to say') }}</flux:select.option>
                        @foreach ($vitals['politics']['options'] as $value => $label)<flux:select.option :value="$value">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                    @break

                @case('habits')
                    <p class="text-sm font-semibold">{{ __('Do you drink?') }}</p>
                    @include('partials.onboarding-choices', ['model' => 'drinking', 'options' => $vitals['drinking']['options']])
                    <p class="pt-2 text-sm font-semibold">{{ __('Do you smoke?') }}</p>
                    @include('partials.onboarding-choices', ['model' => 'smoking', 'options' => $vitals['smoking']['options']])
                    @break

                @case('interests')
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('profile_options.interests') as $interest)
                            @php($on = in_array($interest, $interests, true))
                            <button type="button" wire:click="toggleInterest(@js($interest))"
                                class="rounded-full border px-4 py-2 text-sm font-medium transition active:scale-95 {{ $on ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 bg-white hover:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900' }}">
                                {{ $interest }}
                            </button>
                        @endforeach
                    </div>
                    @break

                @case('photos')
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($this->photos as $photo)
                            <div class="relative aspect-[3/4] overflow-hidden rounded-2xl shadow" wire:key="ob-photo-{{ $photo->id }}">
                                <img src="{{ $photo->url(true) }}" alt="" class="size-full object-cover" />
                                <button type="button" wire:click="deletePhoto({{ $photo->id }})" class="absolute end-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white" aria-label="{{ __('Remove photo') }}">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </div>
                        @endforeach
                        @for ($i = $this->photos->count(); $i < config('dating.limits.max_photos'); $i++)
                            <label class="flex aspect-[3/4] cursor-pointer items-center justify-center rounded-2xl border-2 border-dashed border-zinc-300 bg-white/60 text-zinc-400 transition hover:border-zinc-900 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900/50">
                                <flux:icon.plus class="size-7" wire:loading.remove wire:target="uploads" />
                                <flux:icon.loading class="size-6" wire:loading wire:target="uploads" />
                                <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" />
                            </label>
                        @endfor
                    </div>
                    <flux:error name="uploads" />
                    <flux:error name="uploads.*" />
                    <p class="text-xs text-zinc-500">{{ __('Location data is removed from every photo. Each photo is checked by a moderator.') }}</p>
                    @break

                @case('prompts')
                    @foreach ($prompts as $i => $row)
                        <div class="hw-card space-y-3 p-4" wire:key="ob-prompt-{{ $i }}">
                            <flux:select wire:model="prompts.{{ $i }}.prompt" :placeholder="__('Select a prompt')">
                                @foreach (config('profile_options.prompts') as $option)
                                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:textarea wire:model="prompts.{{ $i }}.answer" rows="2" maxlength="300" :placeholder="__('Your answer…')" />
                            <flux:error name="prompts.{{ $i }}.prompt" />
                            <flux:error name="prompts.{{ $i }}.answer" />
                        </div>
                    @endforeach
                    <flux:error name="prompts" />
                    @break

                @case('bio')
                    <flux:textarea wire:model="bio" rows="5" maxlength="1500" :placeholder="__('What do you enjoy? What are you hoping to find here?')" />
                    <flux:error name="bio" />
                    @break

                @case('done')
                    <div class="hw-card space-y-4 p-6 text-center">
                        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white animate-pop dark:bg-white dark:text-zinc-900">
                            <flux:icon.heart variant="solid" class="size-8" />
                        </div>
                        <p class="font-display text-2xl">{{ __('Nice to meet you, :name.', ['name' => $displayName]) }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('While you wait for approval, you can browse, get the selfie badge, and set your preferences.') }}</p>
                    </div>
                    @break
            @endswitch
        </div>

        <div class="sticky bottom-0 mt-8 space-y-2 bg-gradient-to-t from-[#f7f4ef] via-[#f7f4ef] pt-4 dark:from-[#0e0d0d] dark:via-[#0e0d0d]">
            <button type="submit" class="flex h-14 w-full items-center justify-center gap-2 rounded-full bg-zinc-900 text-lg font-semibold text-white shadow-xl transition hover:scale-[1.01] active:scale-[.99] dark:bg-white dark:text-zinc-900" data-test="onboarding-next">
                <span wire:loading.remove wire:target="next,finish">{{ $step === 'done' ? __('Start meeting people') : __('Continue') }}</span>
                <flux:icon.loading class="size-5" wire:loading wire:target="next,finish" />
            </button>
            @if ($this->isOptional())
                <button type="button" wire:click="skip" class="w-full py-2 text-sm font-semibold text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Skip for now') }}</button>
            @endif
        </div>
    </form>
</div>
