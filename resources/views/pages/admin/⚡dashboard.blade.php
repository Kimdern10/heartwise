<?php

use App\Models\Message;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\VerificationRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Moderation')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            __('Profiles waiting') => [Profile::where('status', 'pending')->count(), route('admin.profiles')],
            __('Photos waiting') => [Photo::where('status', 'pending')->count(), route('admin.photos')],
            __('Selfies waiting') => [VerificationRequest::where('status', 'pending')->count(), route('admin.verifications')],
            __('Open reports') => [Report::where('status', 'open')->count(), route('admin.reports')],
            __('Flagged messages (7 days)') => [Message::whereNotNull('flag')->where('created_at', '>=', now()->subDays(7))->count(), route('admin.messages')],
            __('Members') => [User::where('is_admin', false)->count(), route('admin.users')],
            __('Active in last 24h') => [User::where('last_active_at', '>=', now()->subDay())->count(), null],
            __('Matches') => [UserMatch::count(), null],
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav')

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->stats as $label => [$value, $link])
            <flux:card>
                <flux:text>{{ $label }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($value) }}</flux:heading>
                @if ($link && $value)
                    <flux:link :href="$link" wire:navigate class="text-sm">{{ __('Review') }}</flux:link>
                @endif
            </flux:card>
        @endforeach
    </div>
</section>
