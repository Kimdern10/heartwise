<?php

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $personId;

    public function mount(User $user): void
    {
        $viewer = Auth::user();

        $visible = $viewer->is($user)
            || (User::query()->discoverable()->whereKey($user->id)->exists() && ! $viewer->hasBlockBetween($user));

        abort_unless($visible, 404);

        $this->personId = $user->id;
    }

    #[Computed]
    public function person(): User
    {
        return User::with(['profile', 'approvedPhotos'])->findOrFail($this->personId);
    }

    #[Computed]
    public function myReaction(): ?bool
    {
        return Like::query()->where('user_id', Auth::id())->where('target_id', $this->personId)->value('liked');
    }

    #[Computed]
    public function match(): ?UserMatch
    {
        return Auth::user()->matchWith($this->person);
    }

    public function react(bool $liked, LikeUser $likeUser): void
    {
        try {
            $match = $likeUser->handle(Auth::user(), $this->person, $liked);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        unset($this->myReaction, $this->match);

        if ($match) {
            $this->dispatch('matched', name: $this->person->displayName(), url: route('messages.show', $match));
        } elseif (! $liked) {
            $this->redirectRoute('discover', navigate: true);
        } else {
            Flux::toast(variant: 'success', text: __('Liked! If :name likes you back, it’s a match.', ['name' => $this->person->displayName()]));
        }
    }

    public function render()
    {
        return $this->view()->title($this->person->displayName());
    }
}; ?>

@php($person = $this->person)
@php($profile = $person->profile)
@php($miles = Auth::user()->profile?->milesFrom($profile))

<section class="mx-auto w-full max-w-5xl space-y-6">
    <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('discover')" wire:navigate>{{ __('Back to Discover') }}</flux:button>

    <div class="grid gap-8 md:grid-cols-[1fr_1.1fr]">
        <div class="space-y-3 animate-fade-up" x-data="{ current: 0, total: {{ max(1, $person->approvedPhotos->count()) }} }">
            <div class="relative aspect-[4/5] overflow-hidden rounded-[2rem] shadow-2xl shadow-black/15">
                @if ($person->approvedPhotos->isEmpty())
                    <x-member-photo :user="$person" :small="false" class="absolute inset-0 size-full" />
                @else
                    @foreach ($person->approvedPhotos as $photo)
                        <img x-show="current === {{ $loop->index }}"
                            x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0 scale-105" x-transition:enter-end="opacity-100 scale-100"
                            src="{{ $photo->url() }}" alt="{{ __('Photo :n of :name', ['n' => $loop->iteration, 'name' => $person->displayName()]) }}"
                            class="absolute inset-0 size-full object-cover" @if (! $loop->first) x-cloak @endif />
                    @endforeach

                    @if ($person->approvedPhotos->count() > 1)
                        <div class="absolute inset-x-4 top-4 flex gap-1.5">
                            @foreach ($person->approvedPhotos as $photo)
                                <span class="h-1 flex-1 rounded-full transition" x-bind:class="current === {{ $loop->index }} ? 'bg-white' : 'bg-white/40'"></span>
                            @endforeach
                        </div>
                        <button type="button" class="absolute inset-y-0 start-0 w-1/3" x-on:click="current = (current - 1 + total) % total" aria-label="{{ __('Previous photo') }}"></button>
                        <button type="button" class="absolute inset-y-0 end-0 w-1/3" x-on:click="current = (current + 1) % total" aria-label="{{ __('Next photo') }}"></button>
                    @endif
                @endif
            </div>
        </div>

        <div class="space-y-6 animate-fade-up [animation-delay:120ms]">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1" class="!text-4xl !font-extrabold">{{ $person->displayName() }}</flux:heading>
                    <span class="text-3xl font-light text-zinc-500">{{ $person->age() }}</span>
                    <x-verified-badge :user="$person" class="size-7" />
                    <x-location-badge :user="$person" class="size-6" />
                </div>
                <flux:text class="mt-2 flex items-center gap-1">
                    <flux:icon.map-pin class="size-4" /> {{ $profile->location() }}
                    @if ($miles) · {{ trans_choice(':count mile away|:count miles away', $miles) }} @endif
                </flux:text>
                @if ($profile->show_last_active && $person->last_active_at)
                    <flux:text class="mt-1 flex items-center gap-1.5 text-sm">
                        @if ($person->last_active_at->gt(now()->subMinutes(15)))
                            <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2.5 rounded-full bg-emerald-500"></span></span>
                            {{ __('Active now') }}
                        @else
                            {{ __('Active :time', ['time' => $person->last_active_at->diffForHumans()]) }}
                        @endif
                    </flux:text>
                @endif
                <x-sample-badge :user="$person" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium dark:bg-zinc-800">{{ $profile->gender->label() }}</span>
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium dark:bg-zinc-800">{{ __('Into :who', ['who' => strtolower($profile->seeking->label())]) }}</span>
                @if ($profile->relationship_goal)
                    <span class="bg-brand-gradient rounded-full px-3 py-1 text-sm font-semibold text-white">{{ $profile->relationship_goal->label() }}</span>
                @endif
                @if ($profile->heightLabel())
                    <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium dark:bg-zinc-800">{{ $profile->heightLabel() }}</span>
                @endif
            </div>

            @if ($profile->bio)
                <div class="hw-card p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-brand-600">{{ __('About me') }}</p>
                    <p class="mt-2 whitespace-pre-line leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $profile->bio }}</p>
                </div>
            @endif

            @unless (Auth::user()->is($person))
                <div class="flex flex-wrap gap-3">
                    @if ($this->match)
                        <flux:button variant="primary" icon="chat-bubble-left-right" :href="route('messages.show', $this->match)" wire:navigate>{{ __('Send a message') }}</flux:button>
                    @elseif ($this->myReaction === true)
                        <flux:button disabled icon="heart">{{ __('You liked :name', ['name' => $person->displayName()]) }}</flux:button>
                    @else
                        <flux:button icon="x-mark" wire:click="react(false)">{{ __('Pass') }}</flux:button>
                        <flux:button variant="primary" icon="heart" wire:click="react(true)" data-test="like-button">{{ __('Like') }}</flux:button>
                    @endif
                </div>

                <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <livewire:report-block :user-id="$person->id" :key="'rb-'.$person->id" />
                </div>
            @endunless
        </div>
    </div>
</section>
