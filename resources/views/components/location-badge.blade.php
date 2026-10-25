@props(['user'])

@if ($user->profile?->isLocationVerified())
    <flux:tooltip :content="__('Location verified: their device was in :state when they checked', ['state' => $user->profile->state])">
        <flux:icon.map-pin variant="solid" {{ $attributes->class('size-5 text-emerald-500') }} />
    </flux:tooltip>
@endif
