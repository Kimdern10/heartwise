<?php

use App\Actions\Dating\BlockUser;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Privacy settings')] class extends Component {
    public bool $isVisible = true;

    public bool $showLastActive = true;

    public bool $browsePrivately = false;

    /** @var array<string, bool> Email notification switches */
    public array $emails = [];

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->profile;

        $this->isVisible = $profile?->is_visible ?? true;
        $this->showLastActive = $profile?->show_last_active ?? true;
        $this->browsePrivately = $profile?->browse_privately ?? false;
        $this->emails = $user->only(['notify_matches', 'notify_messages', 'notify_likes', 'notify_digest']);
    }

    #[Computed]
    public function blocked()
    {
        return Auth::user()->blocks()->with('blocked.profile')->latest()->get();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'emails.')) {
            $key = substr($property, 7);

            if (in_array($key, ['notify_matches', 'notify_messages', 'notify_likes', 'notify_digest'], true)) {
                Auth::user()->forceFill([$key => (bool) $this->emails[$key]])->save();
                Flux::toast(variant: 'success', text: __('Email settings saved.'));
            }

            return;
        }

        $profile = Auth::user()->profile;

        if (! $profile) {
            Flux::toast(variant: 'warning', text: __('Create your profile first.'));

            return;
        }

        $profile->forceFill([
            'is_visible' => $this->isVisible,
            'show_last_active' => $this->showLastActive,
            'browse_privately' => $this->browsePrivately,
        ])->save();

        Flux::toast(variant: 'success', text: __('Privacy settings saved.'));
    }

    public function unblock(int $userId, BlockUser $blockUser): void
    {
        $blockUser->unblock(Auth::user(), User::findOrFail($userId));
        unset($this->blocked);

        Flux::toast(text: __('Unblocked.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Privacy, notifications & blocked')" :subheading="__('Who sees you, what we email you, and what we keep')">
        <div class="space-y-8">
            <div class="space-y-4">
                <flux:switch wire:model.live="isVisible" :label="__('Show my profile in Discover')"
                    :description="__('Turn off to take a break. Your matches can still message you.')" />
                <flux:switch wire:model.live="showLastActive" :label="__('Show when I was last active')" />
                <flux:switch wire:model.live="browsePrivately" :disabled="! auth()->user()->isPremium()" :label="__('Browse privately (Premium)')" :description="__('Don’t show up in other members’ “Viewed you” list.')" />
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-4" id="notifications">
                <flux:heading>{{ __('Email notifications') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Everything always appears under the bell icon. Choose what we also email you about.') }}</flux:text>
                <flux:switch wire:model.live="emails.notify_matches" :label="__('New matches')" />
                <flux:switch wire:model.live="emails.notify_messages" :label="__('New messages (only when you’re away, at most once an hour)')" />
                <flux:switch wire:model.live="emails.notify_likes" :label="__('New likes (at most every 6 hours)')" />
                <flux:switch wire:model.live="emails.notify_digest" :label="__('Weekly “new people near you” email')" />
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-3">
                <flux:heading>{{ __('Blocked members') }}</flux:heading>
                @forelse ($this->blocked as $block)
                    <div class="flex items-center justify-between gap-3" wire:key="block-{{ $block->id }}">
                        <flux:text>{{ $block->blocked->displayName() }}</flux:text>
                        <flux:button size="sm" wire:click="unblock({{ $block->blocked_id }})">{{ __('Unblock') }}</flux:button>
                    </div>
                @empty
                    <flux:text>{{ __('You have not blocked anyone.') }}</flux:text>
                @endforelse
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-3">
                <flux:heading>{{ __('Your data') }}</flux:heading>
                <flux:text>{{ __('Download a copy of your account, profile, likes and messages you sent. To delete everything, use “Delete account” on the Account page.') }}</flux:text>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" icon="arrow-down-tray" :href="route('privacy.export')">{{ __('Download my data') }}</flux:button>
                    <flux:button size="sm" variant="ghost" :href="route('profile.edit')" wire:navigate>{{ __('Delete account') }}</flux:button>
                </div>
                <flux:text class="text-xs">{{ __('See our') }} <flux:link :href="route('legal.privacy')">{{ __('Privacy Policy') }}</flux:link> {{ __('for your rights, including under California law (CCPA/CPRA).') }}</flux:text>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
