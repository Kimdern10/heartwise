<?php

use App\Enums\ModerationStatus;
use App\Models\Like;
use App\Models\UserMatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Home')] class extends Component {
    /**
     * @return list<array{label: string, done: bool, route: string, hint: string, icon: string}>
     */
    #[Computed]
    public function steps(): array
    {
        $user = Auth::user();
        $profile = $user->profile;

        return [
            ['label' => __('Verify your email'), 'done' => $user->hasVerifiedEmail(), 'route' => route('verification.notice'), 'hint' => __('Enter the 6-digit code we emailed you.'), 'icon' => 'envelope'],
            ['label' => __('Create your profile'), 'done' => $profile !== null, 'route' => route('my-profile.edit'), 'hint' => __('Display name, city and what you’re looking for.'), 'icon' => 'user-circle'],
            ['label' => __('Add a photo'), 'done' => $user->photos()->exists(), 'route' => route('my-profile.edit'), 'hint' => __('Profiles with photos get far more matches.'), 'icon' => 'camera'],
            ['label' => __('Pass profile review'), 'done' => $profile?->status === ModerationStatus::Approved && $user->approvedPhotos()->exists(), 'route' => route('my-profile.edit'), 'hint' => __('A real person checks every profile and photo.'), 'icon' => 'shield-check'],
            ['label' => __('Verify your location'), 'done' => (bool) $profile?->isLocationVerified(), 'route' => route('my-profile.edit').'#location', 'hint' => __('Turn on location once to prove you live where you say.'), 'icon' => 'map-pin'],
            ['label' => __('Get the selfie badge'), 'done' => $profile?->photo_verified_at !== null, 'route' => route('my-profile.verify'), 'hint' => __('Members trust verified profiles more.'), 'icon' => 'check-badge'],
        ];
    }

    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();

        return [
            ['label' => __('Liked you'), 'value' => Like::query()->where('target_id', $user->id)->where('liked', true)->whereNotIn('user_id', $user->blockedUserIds())->count(), 'icon' => 'heart', 'route' => route('activity')],
            ['label' => __('Matches'), 'value' => UserMatch::query()->involving($user)->count(), 'icon' => 'sparkles', 'route' => route('matches')],
            ['label' => __('Unread'), 'value' => $user->unreadMessagesCount(), 'icon' => 'chat-bubble-left-right', 'route' => route('messages.index')],
        ];
    }
}; ?>

@php($done = collect($this->steps)->where('done', true)->count())
@php($total = count($this->steps))
@php($pct = (int) round($done / $total * 100))

<section class="mx-auto w-full max-w-5xl space-y-6">
    {{-- Hero --}}
    <div class="bg-brand-gradient relative overflow-hidden rounded-[2rem] p-6 text-white shadow-2xl shadow-brand-500/25 sm:p-8 animate-fade-up">
        <div class="absolute -end-10 -top-16 size-64 rounded-full bg-white/10 blur-2xl animate-blob"></div>
        <div class="absolute -bottom-20 start-1/3 size-56 rounded-full bg-fuchsia-300/20 blur-2xl animate-blob [animation-delay:-6s]"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div class="max-w-md">
                <p class="text-sm font-semibold text-white/80">{{ now()->format('l') }}</p>
                <h1 class="font-display mt-1 text-4xl sm:text-5xl">{{ __('Hi :name 👋', ['name' => Auth::user()->displayName()]) }}</h1>
                <p class="mt-2 text-white/85">{{ __('Real people, real connections. Ready to meet someone new?') }}</p>
                <a href="{{ route('discover') }}" wire:navigate class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-brand-600 shadow-lg transition hover:scale-105">
                    <flux:icon.sparkles class="size-4" /> {{ __('Start swiping') }}
                </a>
            </div>

            <div class="relative size-32 shrink-0" x-data="{ p: 0 }" x-init="setTimeout(() => p = {{ $pct }}, 150)">
                <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(255 255 255 / .2)" stroke-width="3" />
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"
                        stroke-dasharray="97.4" x-bind:stroke-dashoffset="97.4 - (97.4 * p / 100)" style="transition: stroke-dashoffset 1.2s cubic-bezier(.2,.8,.2,1)" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-extrabold">{{ $pct }}%</span>
                    <span class="text-[11px] text-white/80">{{ __('profile ready') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stagger grid gap-4 sm:grid-cols-3">
        @foreach ($this->stats as $stat)
            <a @if ($stat['route']) href="{{ $stat['route'] }}" wire:navigate @endif class="hw-card hw-card--hover flex items-center gap-4 p-5">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon :name="$stat['icon']" class="size-6" />
                </span>
                <div>
                    <p class="text-3xl font-extrabold tracking-tight">{{ $stat['value'] }}</p>
                    <p class="text-sm text-zinc-500">{{ $stat['label'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Daily picks --}}
    @php($picks = \App\Support\Discovery::dailyPicks(Auth::user()))
    @if ($picks['pick'] || $picks['standouts']->isNotEmpty())
        <div class="space-y-4 animate-fade-up [animation-delay:150ms]">
            <div class="flex items-end justify-between">
                <div>
                    <h2 class="font-display text-3xl">{{ __('Today’s picks') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Chosen for you from your answers and preferences. New picks every day.') }}</p>
                </div>
                <flux:link :href="route('discover')" wire:navigate class="text-sm font-semibold">{{ __('See all') }}</flux:link>
            </div>
            <div class="flex snap-x gap-4 overflow-x-auto pb-3">
                @foreach (collect([$picks['pick']])->filter()->merge($picks['standouts']) as $pick)
                    <a href="{{ route('people.show', $pick) }}" wire:navigate wire:key="pick-{{ $pick->id }}"
                        class="group relative w-56 shrink-0 snap-start overflow-hidden rounded-3xl shadow-lg">
                        <x-member-photo :user="$pick" :small="false" class="aspect-[3/4] w-full transition duration-500 group-hover:scale-105" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent"></div>
                        @if ($loop->first && $picks['pick'])
                            <span class="absolute start-3 top-3 flex items-center gap-1 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-amber-950"><flux:icon.star variant="solid" class="size-3.5" />{{ __('Most Compatible') }}</span>
                        @else
                            <span class="absolute start-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-bold text-zinc-900">{{ __('Standout') }}</span>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 p-4 text-white">
                            <p class="font-display text-2xl">{{ $pick->displayName() }}, {{ $pick->age() }}</p>
                            @if ($prompt = $pick->prompts->first())
                                <p class="mt-1 line-clamp-2 text-xs text-white/85">{{ $prompt->answer }}</p>
                            @else
                                <p class="text-xs text-white/80">{{ $pick->profile->location() }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Checklist --}}
    <div class="hw-card p-6 animate-fade-up [animation-delay:200ms]">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Get the most out of :app', ['app' => config('app.name')]) }}</flux:heading>
            <span class="text-sm font-semibold text-zinc-500">{{ $done }}/{{ $total }}</span>
        </div>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($this->steps as $step)
                <li>
                    <a href="{{ $step['route'] }}" @if (! str_contains($step['route'], '#')) wire:navigate @endif
                        class="flex items-center gap-3 rounded-2xl border p-3.5 transition {{ $step['done'] ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-500/5' : 'border-zinc-200 hover:border-brand-300 hover:bg-brand-50/50 dark:border-zinc-800 dark:hover:bg-white/5' }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $step['done'] ? 'bg-emerald-500 text-white' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' }}">
                            @if ($step['done'])
                                <flux:icon.check class="size-5" />
                            @else
                                <flux:icon :name="$step['icon']" class="size-5" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold {{ $step['done'] ? 'text-emerald-700 dark:text-emerald-400' : '' }}">{{ $step['label'] }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $step['hint'] }}</p>
                        </div>
                        @unless ($step['done'])
                            <flux:icon.chevron-right class="size-4 text-zinc-400" />
                        @endunless
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
