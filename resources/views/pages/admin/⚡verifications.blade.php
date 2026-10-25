<?php

use App\Enums\ModerationStatus;
use App\Models\VerificationRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Selfie verification')] class extends Component {
    #[Computed]
    public function requests()
    {
        return VerificationRequest::query()
            ->where('status', ModerationStatus::Pending)
            ->with(['user.profile', 'user.photos'])
            ->oldest()
            ->limit(20)
            ->get();
    }

    public function approve(int $id): void
    {
        VerificationRequest::findOrFail($id)->decide(ModerationStatus::Approved, Auth::user());
        unset($this->requests);

        Flux::toast(variant: 'success', text: __('Verified. The selfie has been deleted.'));
    }

    public function reject(int $id, string $note): void
    {
        VerificationRequest::findOrFail($id)->decide(ModerationStatus::Rejected, Auth::user(), $note);
        unset($this->requests);

        Flux::toast(text: __('Rejected. The selfie has been deleted.'));
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Selfie verification')])

    <flux:text class="mb-4">{{ __('Check that the selfie shows the same person as the profile photos, doing the requested pose. Selfies are deleted as soon as you decide.') }}</flux:text>

    <div class="space-y-4">
        @forelse ($this->requests as $request)
            <flux:card wire:key="vr-{{ $request->id }}" class="grid gap-4 md:grid-cols-[1fr_2fr]">
                <div class="space-y-2">
                    <flux:badge color="amber">{{ __('Pose: :pose', ['pose' => $request->gesture]) }}</flux:badge>
                    <img src="{{ route('admin.verifications.selfie', $request) }}" alt="{{ __('Selfie') }}" class="w-full rounded-lg object-cover" />
                </div>
                <div class="space-y-3">
                    <flux:heading size="lg">{{ $request->user->displayName() }}, {{ $request->user->age() }}</flux:heading>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($request->user->photos as $photo)
                            <img src="{{ $photo->url(true) }}" alt="" class="aspect-square rounded-lg object-cover" />
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" icon="check-badge" wire:click="approve({{ $request->id }})" data-test="approve-selfie">{{ __('Same person — verify') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="reject({{ $request->id }}, '{{ __('The selfie did not show the requested pose.') }}')">{{ __('Wrong pose') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="reject({{ $request->id }}, '{{ __('We could not match your selfie to your profile photos.') }}')">{{ __('Not the same person') }}</flux:button>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:card class="py-10 text-center"><flux:text>{{ __('No selfies waiting. 🎉') }}</flux:text></flux:card>
        @endforelse
    </div>
</section>
