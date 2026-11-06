<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function boost(): void
    {
        $user = Auth::user();

        if (! $user->canBoost() || ! $user->profile) {
            Flux::toast(variant: 'warning', text: $user->isPremium()
                ? __('Your next Boost is available :when.', ['when' => $user->nextBoostAt()?->diffForHumans()])
                : __('Boost is a Premium feature.'));

            return;
        }

        $user->profile->forceFill(['boosted_until' => now()->addMinutes(config('premium.boost_minutes'))])->save();
        $user->forceFill(['last_boost_at' => now()])->save();

        Flux::toast(variant: 'success', heading: __('You’re boosted! 🚀'), text: __('For the next :n minutes you’re one of the first people members near you see.', ['n' => config('premium.boost_minutes')]));
    }
}; ?>

@php($user = Auth::user())
<div wire:poll.60s>
    @if ($user->isBoosted())
        <span class="inline-flex items-center gap-2 rounded-full bg-violet-100 px-4 py-2 text-sm font-semibold text-violet-800 dark:bg-violet-500/15 dark:text-violet-300" data-test="boost-active">
            <flux:icon.rocket-launch class="size-4" />
            {{ __('Boosted · :n min left', ['n' => max(1, (int) ceil(now()->diffInMinutes($user->profile->boosted_until)))]) }}
        </span>
    @elseif ($user->canBoost())
        <button type="button" wire:click="boost" data-confirm="{{ __('Use your Boost now? You’ll be shown first to people near you for 30 minutes.') }}"
            class="inline-flex items-center gap-2 rounded-full bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-violet-600/30 transition hover:scale-105" data-test="boost-button">
            <flux:icon.rocket-launch class="size-4" /> {{ __('Boost me') }}
        </button>
    @elseif ($user->isPremium())
        <span class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-500 dark:bg-zinc-800">
            <flux:icon.rocket-launch class="size-4" /> {{ __('Next Boost :when', ['when' => $user->nextBoostAt()?->diffForHumans()]) }}
        </span>
    @else
        <a href="{{ route('premium') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50 dark:border-violet-700 dark:text-violet-300 dark:hover:bg-violet-500/10">
            <flux:icon.rocket-launch class="size-4" /> {{ __('Boost (Premium)') }}
        </a>
    @endif
</div>
