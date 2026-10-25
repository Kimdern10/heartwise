<?php

use App\Enums\ModerationStatus;
use App\Models\Photo;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Photo review')] class extends Component {
    use WithPagination;

    public const REASONS = [
        'Your face must be clearly visible.',
        'Photos must be of you only, not other people or celebrities.',
        'No nudity or sexual content.',
        'No contact details, text or watermarks on photos.',
        'This photo appears to be taken from the internet.',
    ];

    #[Computed]
    public function photos()
    {
        return Photo::query()
            ->where('status', ModerationStatus::Pending)
            ->with('user.profile')
            ->oldest()
            ->paginate(24);
    }

    public function approve(int $photoId): void
    {
        Photo::findOrFail($photoId)->forceFill(['status' => ModerationStatus::Approved, 'rejection_reason' => null])->save();
        unset($this->photos);
    }

    public function reject(int $photoId, int $reason): void
    {
        Photo::findOrFail($photoId)->forceFill([
            'status' => ModerationStatus::Rejected,
            'rejection_reason' => self::REASONS[$reason] ?? self::REASONS[0],
        ])->save();
        unset($this->photos);

        Flux::toast(text: __('Photo rejected.'));
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Photo review')])

    @if ($this->photos->isEmpty())
        <flux:card class="py-10 text-center"><flux:text>{{ __('No photos waiting. 🎉') }}</flux:text></flux:card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->photos as $photo)
                <flux:card wire:key="photo-{{ $photo->id }}" class="space-y-3 !p-3">
                    <a href="{{ $photo->url() }}" target="_blank">
                        <img src="{{ $photo->url(true) }}" alt="" class="aspect-[3/4] w-full rounded-lg object-cover" />
                    </a>
                    <flux:text class="text-sm">
                        {{ $photo->user->profile?->display_name ?? $photo->user->name }}, {{ $photo->user->age() }}
                        · {{ $photo->user->profile?->gender?->label() }}
                    </flux:text>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="primary" icon="check" class="flex-1" wire:click="approve({{ $photo->id }})" data-test="approve-photo">{{ __('Approve') }}</flux:button>
                        <flux:dropdown>
                            <flux:button size="sm" variant="danger" icon:trailing="chevron-down">{{ __('Reject') }}</flux:button>
                            <flux:menu>
                                @foreach ($this::REASONS as $i => $reason)
                                    <flux:menu.item wire:click="reject({{ $photo->id }}, {{ $i }})">{{ $reason }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="mt-4">{{ $this->photos->links() }}</div>
    @endif
</section>
