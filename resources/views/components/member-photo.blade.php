@props([
    'user',
    'small' => true,
])

@php($photo = $user->primaryPhoto())

<div {{ $attributes->class('relative overflow-hidden bg-brand-gradient') }} x-data="{ loaded: false }">
    @if ($photo)
        <div class="skeleton absolute inset-0" x-show="! loaded"></div>
        <img src="{{ $photo->url($small) }}" alt="{{ __('Photo of :name', ['name' => $user->displayName()]) }}"
            class="size-full object-cover transition duration-700" loading="lazy" draggable="false"
            x-on:load="loaded = true" x-init="if ($el.complete) loaded = true"
            x-bind:class="loaded ? 'opacity-100 scale-100' : 'opacity-0 scale-105'" />
    @else
        <div class="flex size-full items-center justify-center text-3xl font-bold text-white/90">
            {{ $user->initials() }}
        </div>
    @endif
</div>
