<?php

use App\Models\Profile;
use App\Support\Compatibility;
use App\Support\Discovery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Search')] class extends Component {
    use WithPagination;

    #[Url] public int $ageMin = 18;
    #[Url] public int $ageMax = 99;
    /**
     * Search looks in ONE city inside the member's own state: their own city
     * by default, or another city in the same state picked from the list.
     */
    #[Url] public string $city = '';
    #[Url] public string $goal = '';
    #[Url] public string $heightMin = '';
    #[Url] public string $heightMax = '';
    #[Url] public string $religion = '';
    #[Url] public string $education = '';
    #[Url] public string $hasKids = '';
    #[Url] public string $wantsKids = '';
    #[Url] public string $drinking = '';
    #[Url] public string $smoking = '';
    #[Url] public bool $online = false;
    #[Url] public bool $verified = false;
    #[Url] public bool $hasPrompts = false;
    /** Premium perk: only show other Premium members. */
    #[Url] public bool $premiumOnly = false;
    #[Url] public string $sort = 'active';

    public function mount(): void
    {
        $this->city = $this->validCity($this->city);
    }

    public function updated(string $property): void
    {
        if ($property === 'city') {
            $this->city = $this->validCity($this->city);
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset();
        $this->city = $this->myCity();
        $this->resetPage();
    }

    public function useMyCity(): void
    {
        $this->city = $this->myCity();
        $this->resetPage();
    }

    public function myState(): string
    {
        return (string) Auth::user()->profile?->state;
    }

    private function myCity(): string
    {
        return trim((string) Auth::user()->profile?->city);
    }

    /** Only cities in the member's own state are allowed; anything else falls back to their city. */
    private function validCity(string $city): string
    {
        $match = collect($this->cities())->first(fn ($c) => mb_strtolower($c) === mb_strtolower(trim($city)));

        return $match ?? $this->myCity();
    }

    /**
     * Cities in the member's state where approved members live (plus their own city).
     *
     * @return list<string>
     */
    #[Computed]
    public function cities(): array
    {
        $state = $this->myState();

        $cities = Cache::remember('cities:'.$state, now()->addMinutes(30), fn () => Profile::query()
            ->where('state', $state)->where('status', 'approved')
            ->selectRaw('MIN(TRIM(city)) as city')->groupByRaw('LOWER(TRIM(city))')
            ->limit(500)->pluck('city')->all());

        return collect($cities)->push($this->myCity())->filter()
            ->unique(fn ($c) => mb_strtolower($c))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    #[Computed]
    public function results()
    {
        $filters = [
            'age_min' => $this->ageMin, 'age_max' => $this->ageMax, 'goal' => $this->goal,
            // Always the member's own state, and one city in it.
            'state' => $this->myState(),
            'city' => $this->city ?: $this->myCity(),
            'online' => $this->online, 'verified' => $this->verified, 'has_prompts' => $this->hasPrompts,
            // People I've already liked or passed never show again.
            'hide_seen' => true,
        ];

        // Advanced filters are a Premium perk.
        if (Auth::user()->isPremium()) {
            $filters += [
                'height_min' => $this->heightMin, 'height_max' => $this->heightMax,
                'religion' => $this->religion, 'education' => $this->education, 'has_kids' => $this->hasKids,
                'wants_kids' => $this->wantsKids, 'drinking' => $this->drinking, 'smoking' => $this->smoking,
                'premium_only' => $this->premiumOnly,
            ];
        }

        $query = Discovery::for(Auth::user(), $filters);

        // Premium members see who else is Premium.
        if (Auth::user()->isPremium()) {
            $query->with('subscriptions');
        }

        if ($this->sort === 'newest') {
            $query->reorder()->latest('users.created_at');
        }

        $page = $query->paginate(24);

        if ($this->sort === 'compatible' && ($me = Auth::user()->profile)) {
            $page->setCollection($page->getCollection()->sortByDesc(fn ($u) => Compatibility::score($me, $u->profile))->values());
        }

        return $page;
    }

    #[Computed]
    public function activeFilters(): int
    {
        return collect([$this->goal, $this->heightMin, $this->heightMax, $this->religion, $this->education,
            $this->hasKids, $this->wantsKids, $this->drinking, $this->smoking])->filter()->count()
            + (int) ($this->ageMin > 18) + (int) ($this->ageMax < 99)
            + (int) $this->online + (int) $this->verified + (int) $this->hasPrompts + (int) ($this->premiumOnly && Auth::user()->isPremium());
    }
}; ?>

@php($vitals = config('profile_options.vitals'))
@php($me = Auth::user()->profile)

<section class="mx-auto w-full max-w-7xl" x-data="{ open: window.innerWidth >= 1024 }">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 animate-fade-up">
        <div>
            <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('Search') }}</flux:heading>
            <flux:subheading size="lg">
                {{ trans_choice(':count member in :place matches your search|:count members in :place match your search', $this->results->total(), ['place' => ($city ?: $me?->city).', '.$me?->state]) }}
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="sort" class="w-44">
                <flux:select.option value="active">{{ __('Recently active') }}</flux:select.option>
                <flux:select.option value="newest">{{ __('Newest members') }}</flux:select.option>
                <flux:select.option value="compatible">{{ __('Most compatible') }}</flux:select.option>
            </flux:select>
            <flux:button icon="funnel" x-on:click="open = ! open" class="lg:hidden">
                {{ __('Filters') }} @if ($this->activeFilters)({{ $this->activeFilters }})@endif
            </flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
        {{-- Filter panel --}}
        <aside x-show="open" x-collapse class="lg:!block">
            <div class="hw-card space-y-5 p-5 lg:sticky lg:top-6">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Filters') }}</flux:heading>
                    @if ($this->activeFilters)
                        <button type="button" wire:click="resetFilters" class="text-xs font-semibold hover:underline">{{ __('Clear all') }}</button>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    <x-check wire:model.live="online" :label="__('Online now')" />
                    <x-check wire:model.live="verified" :label="__('Photo verified')" />
                    <x-check wire:model.live="hasPrompts" :label="__('Has answered prompts')" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="number" size="sm" wire:model.live.debounce.500ms="ageMin" :label="__('Age from')" min="18" max="99" />
                    <flux:input type="number" size="sm" wire:model.live.debounce.500ms="ageMax" :label="__('to')" min="18" max="99" />
                </div>

                {{-- Where: one city, always inside the member's own state --}}
                @php($stateName = config('dating.us_states')[$me?->state] ?? $me?->state)
                <div class="space-y-2 rounded-2xl bg-zinc-50 p-3 dark:bg-zinc-800/60" data-test="search-city">
                    <flux:select size="sm" wire:model.live="city" :label="__('City in :state', ['state' => $stateName])">
                        @foreach ($this->cities as $option)
                            <flux:select.option :value="$option">{{ $option }}{{ mb_strtolower($option) === mb_strtolower(trim((string) $me?->city)) ? ' '.__('(my city)') : '' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <p class="text-xs text-zinc-500">{{ __('Search only shows people in your state. Moved? Update your city in your profile.') }}</p>
                    @if ($me && mb_strtolower($city) !== mb_strtolower(trim($me->city)))
                        <button type="button" wire:click="useMyCity" class="text-xs font-semibold underline">{{ __('Back to my city (:city)', ['city' => $me->city]) }}</button>
                    @endif
                </div>

                <flux:select size="sm" wire:model.live="goal" :label="__('Looking for')">
                    <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                    @foreach (\App\Enums\RelationshipGoal::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Advanced filters (Premium) --}}
                @php($premium = Auth::user()->isPremium())
                <div class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-1.5 text-sm font-semibold"><flux:icon.sparkles variant="solid" class="size-4 text-amber-500" />{{ __('Advanced') }}</p>
                        @unless ($premium)
                            <a href="{{ route('premium') }}" wire:navigate class="text-xs font-semibold underline">{{ __('Unlock with Premium') }}</a>
                        @endunless
                    </div>

                    <fieldset @disabled(! $premium) class="space-y-4 disabled:opacity-50">
                        <x-check wire:model.live="premiumOnly" :label="__('Premium members only')" data-test="premium-only" />
                        <div class="grid grid-cols-2 gap-3">
                            <flux:select size="sm" wire:model.live="heightMin" :label="__('Height from')">
                                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                                @foreach (range(54, 82, 2) as $in)
                                    <flux:select.option :value="$in">{{ intdiv($in, 12) }}′{{ $in % 12 }}″</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select size="sm" wire:model.live="heightMax" :label="__('to')">
                                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                                @foreach (range(56, 84, 2) as $in)
                                    <flux:select.option :value="$in">{{ intdiv($in, 12) }}′{{ $in % 12 }}″</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        @foreach (['religion' => 'religion', 'education' => 'education', 'hasKids' => 'has_kids', 'wantsKids' => 'wants_kids', 'drinking' => 'drinking', 'smoking' => 'smoking'] as $prop => $key)
                            <flux:select size="sm" wire:model.live="{{ $prop }}" :label="__($vitals[$key]['label'])" wire:key="f-{{ $prop }}">
                                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                                @foreach ($vitals[$key]['options'] as $value => $label)
                                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endforeach
                    </fieldset>
                </div>
            </div>
        </aside>

        {{-- Results --}}
        <div>
            @if ($this->results->isEmpty())
                <div class="hw-card px-6 py-14 text-center">
                    <flux:icon.magnifying-glass class="mx-auto size-10 text-zinc-400" />
                    @if ($this->activeFilters)
                        <flux:heading size="lg" class="font-display mt-4 !text-2xl">{{ __('No one in :city matches all of that', ['city' => $city]) }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Try removing a filter or two.') }}</flux:text>
                        <flux:button class="mt-5" wire:click="resetFilters">{{ __('Clear filters') }}</flux:button>
                    @else
                        <flux:heading size="lg" class="font-display mt-4 !text-2xl">{{ __('No one in :city yet', ['city' => $city]) }}</flux:heading>
                        <flux:text class="mt-1">{{ __('New people join every day. You can also pick another city in your state.') }}</flux:text>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4" wire:loading.class="opacity-60 transition">
                    @foreach ($this->results as $person)
                        @php($online = $person->profile->show_last_active && $person->last_active_at?->gt(now()->subMinutes(15)))
                        <a href="{{ route('people.show', $person) }}" wire:navigate wire:key="r-{{ $person->id }}" class="hw-card hw-card--hover group block overflow-hidden" data-reveal>
                            <div class="relative">
                                <x-member-photo :user="$person" class="aspect-[4/5] w-full transition duration-500 group-hover:scale-[1.03]" />
                                @if ($online)
                                    <span class="absolute start-2 top-2 flex items-center gap-1.5 rounded-full bg-white/90 px-2 py-0.5 text-[11px] sm:start-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-xs font-semibold text-emerald-700 backdrop-blur"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('Online') }}</span>
                                @endif
                                @if ($premium && $person->subscribed('premium'))
                                    <span class="absolute bottom-2 start-2 flex items-center gap-1 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-amber-950 sm:bottom-3 sm:start-3 sm:text-xs" data-test="premium-badge"><flux:icon.sparkles variant="solid" class="size-3" />{{ __('Premium') }}</span>
                                @endif
                                @if ($me)
                                    <span class="absolute end-2 top-2 rounded-full bg-black/50 px-2 py-0.5 text-[11px] sm:end-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-xs font-bold text-white backdrop-blur">{{ \App\Support\Compatibility::score($me, $person->profile) }}%</span>
                                @endif
                            </div>
                            <div class="min-w-0 space-y-1 p-3 sm:p-4">
                                <div class="flex items-center gap-1 text-base font-semibold sm:gap-1.5 sm:text-lg">
                                    <span class="truncate">{{ $person->displayName() }}, {{ $person->age() }}</span>
                                    <x-verified-badge :user="$person" class="size-4 shrink-0 sm:size-5" />
                                    <x-location-badge :user="$person" class="size-4 shrink-0" />
                                </div>
                                <p class="truncate text-xs text-zinc-500 sm:text-sm">{{ $person->profile->location() }}@if ($miles = $me?->milesFrom($person->profile)) · {{ $miles }} mi @endif</p>
                                @if ($person->profile->relationship_goal)
                                    <p class="truncate text-xs sm:text-sm">{{ $person->profile->relationship_goal->label() }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">{{ $this->results->links() }}</div>
            @endif
        </div>
    </div>
</section>
