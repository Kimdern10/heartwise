<?php

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Livewire\Concerns\LikesProfiles;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\Discovery;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Discover')] class extends Component {
    use LikesProfiles;

    #[Url]
    public string $view = 'feed';

    #[Url]
    public int $ageMin = 18;

    #[Url]
    public int $ageMax = 99;

    #[Url]
    public string $state = '';

    #[Url]
    public bool $verified = false;

    public function mount(): void
    {
        $profile = Auth::user()->profile;

        // Start from the member's saved preferences unless the URL says otherwise.
        if (! request()->has('ageMin') && $profile?->pref_age_min) {
            $this->ageMin = $profile->pref_age_min;
            $this->ageMax = $profile->pref_age_max ?? 99;
        }
    }

    #[Computed]
    public function people()
    {
        return Discovery::for(Auth::user(), [
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'state' => $this->state ?: null,
            'verified' => $this->verified,
        ])->with('prompts')->limit(3)->get();
    }

    /**
     * Today's Most Compatible pick goes first in the feed, if not seen yet.
     */
    #[Computed]
    public function dailyPick(): ?User
    {
        return Discovery::dailyPicks(Auth::user())['pick'];
    }

    #[Computed]
    public function current(): ?User
    {
        // When the member is filtering, show what they asked for instead.
        return ($this->activeFilters === 0 ? $this->dailyPick : null) ?? $this->people->first();
    }

    #[Computed]
    public function activeFilters(): int
    {
        return (int) ($this->ageMin > 18) + (int) ($this->ageMax < 99) + (int) filled($this->state) + (int) $this->verified;
    }

    /**
     * Swipe mode: like or pass without a comment.
     */
    public function react(int $userId, bool $liked, LikeUser $likeUser): void
    {
        $target = User::findOrFail($userId);

        try {
            $match = $likeUser->handle(Auth::user(), $target, $liked);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
            $this->dispatch('swipe-reset');

            return;
        }

        $this->refreshPeople();

        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        }
    }

    protected function afterLike(User $target, ?UserMatch $match): void
    {
        $this->refreshPeople();

        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        } else {
            Flux::toast(variant: 'success', text: __('Like sent to :name.', ['name' => $target->displayName()]));
        }
    }

    protected function afterPass(): void
    {
        $this->refreshPeople();
    }

    public function updated(): void
    {
        $this->refreshPeople();
    }

    public function resetFilters(): void
    {
        $this->reset('ageMin', 'ageMax', 'state', 'verified');
        $this->refreshPeople();
    }

    private function refreshPeople(): void
    {
        unset($this->people, $this->dailyPick, $this->current);
        $this->dispatch('feed-advanced');
    }

    public function rendered(): void
    {
        if ($this->view === 'feed' && $this->current) {
            ProfileView::record(Auth::user(), $this->current);
        }
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6" x-data="{ filters: false }" x-on:feed-advanced.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    <div class="flex flex-wrap items-end justify-between gap-4 animate-fade-up">
        <div>
            <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('Discover') }}</flux:heading>
            <flux:subheading size="lg">{{ $view === 'feed' ? __('Like a photo or answer that catches your eye — add a comment to stand out.') : __('Swipe right to like, left to pass.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex rounded-full bg-zinc-100 p-1 text-sm font-semibold dark:bg-zinc-800">
                @foreach (['feed' => ['rectangle-stack', __('Profiles')], 'swipe' => ['hand-raised', __('Swipe')]] as $mode => [$icon, $label])
                    <button type="button" wire:click="$set('view', '{{ $mode }}')"
                        class="flex items-center gap-1.5 rounded-full px-4 py-1.5 transition {{ $view === $mode ? 'bg-white text-zinc-900 shadow dark:bg-zinc-950 dark:text-white' : 'text-zinc-500' }}">
                        <flux:icon :name="$icon" class="size-4" /> {{ $label }}
                    </button>
                @endforeach
            </div>
            <flux:button icon="adjustments-horizontal" x-on:click="filters = ! filters">
                @if ($this->activeFilters)<flux:badge size="sm" class="ms-1">{{ $this->activeFilters }}</flux:badge>@endif
            </flux:button>
            <flux:button icon="magnifying-glass" :href="route('search')" wire:navigate>{{ __('Search') }}</flux:button>
        </div>
    </div>

    <div x-show="filters" x-collapse x-cloak>
        <div class="hw-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input type="number" wire:model.live.debounce.500ms="ageMin" :label="__('Age from')" min="18" max="99" />
            <flux:input type="number" wire:model.live.debounce.500ms="ageMax" :label="__('Age to')" min="18" max="99" />
            <flux:select wire:model.live="state" :label="__('State')">
                <flux:select.option value="">{{ __('Anywhere in the US') }}</flux:select.option>
                @foreach (config('dating.us_states') as $code => $name)
                    <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex flex-col justify-end gap-2">
                <flux:checkbox wire:model.live="verified" :label="__('Verified only')" />
                <div class="flex gap-3 text-xs font-semibold">
                    <button type="button" wire:click="resetFilters" class="hover:underline">{{ __('Reset') }}</button>
                    <a href="{{ route('preferences') }}" wire:navigate class="hover:underline">{{ __('Dealbreakers →') }}</a>
                </div>
            </div>
        </div>
    </div>

    @if (! $this->current)
        <div class="hw-card mx-auto max-w-md px-6 py-14 text-center animate-pop">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white animate-float dark:bg-white dark:text-zinc-900">
                <flux:icon.sparkles class="size-8" />
            </div>
            <flux:heading size="lg" class="font-display mt-5 !text-2xl">{{ __('You’ve seen everyone for now') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Widen your filters or preferences, or check back later — new members are approved every day.') }}</flux:text>
            <div class="mt-5 flex justify-center gap-2">
                <flux:button wire:click="resetFilters">{{ __('Reset filters') }}</flux:button>
                <flux:button variant="primary" :href="route('preferences')" wire:navigate>{{ __('Preferences') }}</flux:button>
            </div>
        </div>
    @elseif ($view === 'feed')
        @php($person = $this->current)
        <div wire:key="feed-{{ $person->id }}" class="animate-fade-up pb-24">
            @if ($this->dailyPick?->is($person))
                <div class="mx-auto mb-3 flex max-w-xl items-center gap-2 rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-900 dark:bg-amber-500/15 dark:text-amber-300">
                    <flux:icon.star variant="solid" class="size-4" /> {{ __('Most Compatible today — we think you two could really click.') }}
                </div>
            @endif

            @include('partials.profile-feed', ['person' => $person, 'canLike' => true])

            <div class="pointer-events-none fixed inset-x-0 bottom-24 z-30 flex justify-between px-6 lg:bottom-8 lg:ps-72">
                <button type="button" wire:click="passOn({{ $person->id }})" aria-label="{{ __('Skip') }}" data-test="skip-{{ $person->id }}"
                    class="pointer-events-auto flex size-14 items-center justify-center rounded-full bg-white text-zinc-700 shadow-xl ring-1 ring-zinc-200 transition hover:scale-110 active:scale-95 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-700">
                    <flux:icon.x-mark class="size-7" />
                </button>
                <button type="button" wire:click="openLike({{ $person->id }})" aria-label="{{ __('Like') }}"
                    class="pointer-events-auto flex size-14 items-center justify-center rounded-full bg-zinc-900 text-white shadow-xl transition hover:scale-110 active:scale-95 dark:bg-white dark:text-zinc-900">
                    <flux:icon.heart variant="solid" class="size-7" />
                </button>
            </div>
        </div>
    @else
        {{-- Swipe deck: the first card is on top and can be dragged --}}
        @php($deck = $this->people)
        <div class="mx-auto w-full max-w-sm">
            <div class="relative aspect-[3/4.2] w-full">
                @foreach ($deck as $i => $person)
                    @php($miles = Auth::user()->profile?->milesFrom($person->profile))
                    <article wire:key="deck-{{ $person->id }}"
                        @if ($i === 0)
                            x-data="swipeCard({{ $person->id }})"
                            x-on:pointerdown="down($event)" x-on:pointermove.window="move($event)" x-on:pointerup.window="up()"
                            x-on:swipe-reset.window="x = 0; y = 0; leaving = null"
                            x-bind:style="style"
                            class="swipe-card absolute inset-0 z-30 animate-pop"
                        @else
                            class="absolute inset-0 transition duration-500"
                            style="z-index: {{ 30 - $i }}; transform: translateY({{ $i * 14 }}px) scale({{ 1 - $i * 0.05 }}); opacity: {{ 1 - $i * 0.25 }}"
                        @endif
                    >
                        <div class="relative size-full overflow-hidden rounded-[2rem] shadow-2xl shadow-black/20">
                            <x-member-photo :user="$person" :small="false" class="absolute inset-0 size-full" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent"></div>

                            @if ($i === 0)
                                <div class="swipe-stamp start-5 -rotate-12 text-emerald-400" x-bind:style="`opacity: ${likeOpacity}`">{{ __('LIKE') }}</div>
                                <div class="swipe-stamp end-5 rotate-12 text-rose-400" x-bind:style="`opacity: ${passOpacity}`">{{ __('NOPE') }}</div>
                            @endif

                            <div class="absolute inset-x-0 bottom-0 space-y-2 p-6 text-white">
                                <div class="flex items-center gap-2">
                                    <h2 class="font-display text-4xl">{{ $person->displayName() }}</h2>
                                    <span class="text-2xl font-light">{{ $person->age() }}</span>
                                    <x-verified-badge :user="$person" class="size-6" />
                                    <x-location-badge :user="$person" class="size-5" />
                                </div>
                                <p class="flex items-center gap-1 text-sm text-white/85">
                                    <flux:icon.map-pin class="size-4" /> {{ $person->profile->location() }}
                                    @if ($miles) · {{ trans_choice(':count mile away|:count miles away', $miles) }} @endif
                                </p>
                                @if ($prompt = $person->prompts->first())
                                    <p class="line-clamp-2 text-sm text-white/85"><span class="opacity-70">{{ $prompt->prompt }}:</span> {{ $prompt->answer }}</p>
                                @elseif ($person->profile->bio)
                                    <p class="line-clamp-2 text-sm text-white/80">{{ $person->profile->bio }}</p>
                                @endif
                                <x-sample-badge :user="$person" />
                            </div>

                            <a href="{{ route('people.show', $person) }}" wire:navigate class="absolute end-4 top-4 flex size-10 items-center justify-center rounded-full bg-black/30 text-white backdrop-blur transition hover:bg-black/50" aria-label="{{ __('View profile') }}">
                                <flux:icon.information-circle class="size-6" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            @php($top = $deck->first())
            <div class="mt-8 flex items-center justify-center gap-5">
                <button type="button" wire:click="react({{ $top->id }}, false)" aria-label="{{ __('Pass') }}"
                    class="flex size-16 items-center justify-center rounded-full bg-white text-zinc-700 shadow-xl ring-1 ring-zinc-200 transition hover:scale-110 active:scale-95 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700">
                    <flux:icon.x-mark class="size-8" />
                </button>
                <button type="button" wire:click="openLike({{ $top->id }})" aria-label="{{ __('Like with a comment') }}"
                    class="flex size-12 items-center justify-center rounded-full bg-white text-amber-500 shadow-lg ring-1 ring-zinc-200 transition hover:scale-110 dark:bg-zinc-800 dark:ring-zinc-700">
                    <flux:icon.chat-bubble-left class="size-6" />
                </button>
                <button type="button" wire:click="react({{ $top->id }}, true)" aria-label="{{ __('Like') }}" data-test="like-{{ $top->id }}"
                    class="flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white shadow-xl transition hover:scale-110 active:scale-95 dark:bg-white dark:text-zinc-900">
                    <flux:icon.heart variant="solid" class="size-8" />
                </button>
            </div>
            <p class="mt-4 text-center text-xs text-zinc-500">{{ __('Tip: drag the card left or right') }}</p>
        </div>
    @endif

    @include('partials.like-modal')
</section>
