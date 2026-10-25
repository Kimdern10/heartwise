@php
    $counts = [
        'profiles' => \App\Models\Profile::where('status', 'pending')->count(),
        'photos' => \App\Models\Photo::where('status', 'pending')->count(),
        'verifications' => \App\Models\VerificationRequest::where('status', 'pending')->count(),
        'reports' => \App\Models\Report::where('status', 'open')->count(),
    ];
@endphp

<div class="mb-6 space-y-4">
    <div>
        <flux:heading size="xl" level="1">{{ $heading ?? __('Moderation') }}</flux:heading>
        <flux:subheading>{{ __('Keep the community real and safe. Every decision is final for the member, so check carefully.') }}</flux:subheading>
    </div>

    <flux:navbar class="-mb-px flex-wrap border-b border-zinc-200 dark:border-zinc-700">
        <flux:navbar.item :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Overview') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.profiles')" :current="request()->routeIs('admin.profiles')" :badge="$counts['profiles'] ?: null" wire:navigate>{{ __('Profiles') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.photos')" :current="request()->routeIs('admin.photos')" :badge="$counts['photos'] ?: null" wire:navigate>{{ __('Photos') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.verifications')" :current="request()->routeIs('admin.verifications')" :badge="$counts['verifications'] ?: null" wire:navigate>{{ __('Selfies') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.reports')" :current="request()->routeIs('admin.reports')" :badge="$counts['reports'] ?: null" wire:navigate>{{ __('Reports') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.messages')" :current="request()->routeIs('admin.messages')" wire:navigate>{{ __('Flagged messages') }}</flux:navbar.item>
        <flux:navbar.item :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('Members') }}</flux:navbar.item>
    </flux:navbar>
</div>
