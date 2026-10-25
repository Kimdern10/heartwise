<?php

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Models\User;
use App\Support\Discovery;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Discover')] class extends Component {
    use WithPagination;

    #[Url]
    public string $view = 'swipe';

    #[Url]
    public int $ageMin = 18;

    #[Url]
    public int $ageMax = 99;

    #[Url]
    public string $state = '';

    #[Url]
    public string $goal = '';

    #[Url]
    public bool $verified = false;

    #[Url]
    public bool $hideSeen = true;

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function people()
    {
        return Discovery::for(Auth::user(), [
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'state' => $this->state ?: null,
            'goal' => $this->goal ?: null,
            'verified' => $this->verified,
            // The swipe deck always skips people you already saw.
            'hide_seen' => $this->view === 'swipe' ? true : $this->hideSeen,
        ])->paginate($this->view === 'swipe' ? 3 : 12);
    }

    #[Computed]
    public function activeFilters(): int
    {
        return (int) ($this->ageMin > 18) + (int) ($this->ageMax < 99) + (int) filled($this->state)
            + (int) filled($this->goal) + (int) $this->verified;
    }

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

        unset($this->people);

        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        }
    }

    public function resetFilters(): void
    {
        $this->reset('ageMin', 'ageMax', 'state', 'goal', 'verified', 'hideSeen');
        $this->resetPage();
    }
}; ?>

@php($me = Auth::user()->profile)

<section class="mx-auto w-full max-w-6xl space-y-6" x-data="{ filters: false }">
    <div class="flex flex-wrap items-end justify-between gap-4 animate-fade-up">
        <div>
            <flux:heading size="xl" level="1" class="!text-3xl !font-extrabold">{{ __('Discover') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Swipe right to like, left to pass. Mutual likes become matches.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <div class="glass flex rounded-full p-1 text-sm font-semibold">
                <button type="button" wire:click="$set('view', 'swipe')" class="flex items-center gap-1.5 rounded-full px-4 py-1.5 transition {{ $view === 'swipe' ? 'bg-brand-gradient text-white shadow' : 'text-zinc-600 dark:text-zinc-300' }}">
                    <flux:icon.rectangle-stack class="size-4" /> {{ __('Swipe') }}
                </button>
                <button type="button" wire:click="$set('view', 'grid')" class="flex items-center gap-1.5 rounded-full px-4 py-1.5 transition {{ $view === 'grid' ? 'bg-brand-gradient text-white shadow' : 'text-zinc-600 dark:text-zinc-300' }}">
                    <flux:icon.squares-2x2 class="size-4" /> {{ __('Grid') }}
                </button>
            </div>
            <flux:button icon="adjustments-horizontal" x-on:click="filters = ! filters">
                {{ __('Filters') }}
                @if ($this->activeFilters)
                    <flux:badge size="sm" color="rose" class="ms-1">{{ $this->activeFilters }}</flux:badge>
                @endif
            </flux:button>
        </div>
    </div>

    <div x-show="filters" x-collapse x-cloak>
        <div class="hw-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="grid grid-cols-2 gap-3 lg:col-span-2">
                <flux:input type="number" wire:model.live.debounce.500ms="ageMin" :label="__('Age from')" min="18" max="99" />
                <flux:input type="number" wire:model.live.debounce.500ms="ageMax" :label="__('Age to')" min="18" max="99" />
            </div>
            <flux:select wire:model.live="state" :label="__('State')">
                <flux:select.option value="">{{ __('Anywhere in the US') }}</flux:select.option>
                @foreach (config('dating.us_states') as $code => $name)
                    <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="goal" :label="__('Looking for')">
                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                @foreach (\App\Enums\RelationshipGoal::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex flex-col justify-end gap-2">
                <flux:checkbox wire:model.live="verified" :label="__('Verified only')" />
                @if ($view === 'grid')
                    <flux:checkbox wire:model.live="hideSeen" :label="__('Hide people I saw')" />
                @endif
                <button type="button" wire:click="resetFilters" class="self-start text-xs font-semibold text-brand-600 hover:underline">{{ __('Reset filters') }}</button>
            </div>
        </div>
    </div>

    @if ($this->people->isEmpty())
        <div class="hw-card mx-auto max-w-md px-6 py-14 text-center animate-pop">
            <div class="bg-brand-gradient mx-auto flex size-16 items-center justify-center rounded-full shadow-lg shadow-brand-500/30 animate-float">
                <flux:icon.sparkles class="size-8 text-white" />
            </div>
            <flux:heading size="lg" class="mt-5">{{ __('You’ve seen everyone for now') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Widen your filters or check back later — new members are approved every day.') }}</flux:text>
            <flux:button class="mt-5" variant="primary" wire:click="resetFilters">{{ __('Reset filters') }}</flux:button>
        </div>
    @elseif ($view === 'swipe')
        {{-- Swipe deck: the first card is on top and can be dragged --}}
        <div class="mx-auto w-full max-w-sm">
            <div class="relative aspect-[3/4.2] w-full">
                @foreach ($this->people->items() as $i => $person)
                    @php($miles = $me?->milesFrom($person->profile))
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
                                    <h2 class="text-3xl font-extrabold tracking-tight">{{ $person->displayName() }}</h2>
                                    <span class="text-2xl font-light">{{ $person->age() }}</span>
                                    <x-verified-badge :user="$person" class="size-6" />
                                    <x-location-badge :user="$person" class="size-5" />
                                </div>
                                <p class="flex items-center gap-1 text-sm text-white/85">
                                    <flux:icon.map-pin class="size-4" /> {{ $person->profile->location() }}
                                    @if ($miles) · {{ trans_choice(':count mile away|:count miles away', $miles) }} @endif
                                </p>
                                @if ($person->profile->relationship_goal)
                                    <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur">{{ $person->profile->relationship_goal->label() }}</span>
                                @endif
                                @if ($person->profile->bio)
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

            @php($top = $this->people->items()[0])
            <div class="mt-8 flex items-center justify-center gap-5">
                <button type="button" wire:click="react({{ $top->id }}, false)" aria-label="{{ __('Pass') }}"
                    class="flex size-16 items-center justify-center rounded-full bg-white text-rose-500 shadow-xl shadow-black/10 transition hover:scale-110 active:scale-95 dark:bg-zinc-800">
                    <flux:icon.x-mark class="size-8" />
                </button>
                <a href="{{ route('people.show', $top) }}" wire:navigate aria-label="{{ __('View profile') }}"
                    class="flex size-12 items-center justify-center rounded-full bg-white text-sky-500 shadow-lg shadow-black/10 transition hover:scale-110 dark:bg-zinc-800">
                    <flux:icon.eye class="size-6" />
                </a>
                <button type="button" wire:click="react({{ $top->id }}, true)" aria-label="{{ __('Like') }}" data-test="like-{{ $top->id }}"
                    class="bg-brand-gradient flex size-16 items-center justify-center rounded-full text-white shadow-xl shadow-brand-500/40 transition hover:scale-110 active:scale-95">
                    <flux:icon.heart variant="solid" class="size-8" />
                </button>
            </div>
            <p class="mt-4 text-center text-xs text-zinc-500">{{ __('Tip: drag the card left or right') }}</p>
        </div>
    @else
        <div class="stagger grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->people as $person)
                @php($miles = $me?->milesFrom($person->profile))
                <article wire:key="person-{{ $person->id }}" class="hw-card hw-card--hover group overflow-hidden">
                    <a href="{{ route('people.show', $person) }}" wire:navigate class="relative block">
                        <x-member-photo :user="$person" class="aspect-[4/5] w-full transition duration-500 group-hover:scale-[1.03]" />
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-4 text-white">
                            <div class="flex items-center gap-1.5 text-lg font-bold">
                                {{ $person->displayName() }}, {{ $person->age() }}
                                <x-verified-badge :user="$person" class="size-5" />
                                <x-location-badge :user="$person" class="size-4" />
                            </div>
                            <p class="text-xs text-white/80">{{ $person->profile->location() }}@if ($miles) · {{ $miles }} mi @endif</p>
                        </div>
                    </a>
                    <div class="flex gap-2 p-3">
                        <flux:button class="flex-1" icon="x-mark" wire:click="react({{ $person->id }}, false)">{{ __('Pass') }}</flux:button>
                        <flux:button class="flex-1" variant="primary" icon="heart" wire:click="react({{ $person->id }}, true)" data-test="like-{{ $person->id }}">{{ __('Like') }}</flux:button>
                    </div>
                </article>
            @endforeach
        </div>

        {{ $this->people->links() }}
    @endif
</section>
