@props(['compact' => false])

{{-- Turns phone / desktop notifications on or off for this device (see pushToggle in resources/js/app.js). --}}
<div x-data="pushToggle(@js(config('webpush.vapid.public_key')))" x-show="supported" x-cloak {{ $attributes->class('') }} data-test="push-toggle">
    @if ($compact)
        {{-- Home page prompt: only while notifications are off --}}
        <div x-show="! on && ! blocked" class="hw-card flex flex-wrap items-center gap-4 p-5">
            <span class="bg-brand-gradient flex size-11 shrink-0 items-center justify-center rounded-2xl text-white"><flux:icon.bell-alert class="size-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ __('Never miss a message') }}</p>
                <p class="text-sm text-zinc-500">{{ __('Get a notification on this phone or computer when you get a message, a like or a match.') }}</p>
            </div>
            <flux:button variant="primary" x-on:click="enable()" x-bind:disabled="busy">{{ __('Turn on notifications') }}</flux:button>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div>
                <p class="font-semibold">{{ __('Phone & desktop notifications') }}</p>
                <p class="text-sm text-zinc-500" x-text="blocked ? @js(__('Blocked in your browser settings for this site.')) : (on ? @js(__('On for this device.')) : @js(__('Off for this device.')))"></p>
            </div>
            <flux:button x-show="! on" variant="primary" size="sm" x-on:click="enable()" x-bind:disabled="busy || blocked">{{ __('Turn on') }}</flux:button>
            <flux:button x-show="on" size="sm" x-on:click="disable()" x-bind:disabled="busy">{{ __('Turn off') }}</flux:button>
        </div>
    @endif
</div>
