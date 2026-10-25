<?php

use App\Enums\ModerationStatus;
use App\Models\VerificationRequest;
use App\Support\ImageProcessor;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Get verified')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public string $gesture = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $selfie = null;

    public function mount(): void
    {
        $this->gesture = Arr::random(config('dating.gestures'));
    }

    #[Computed]
    public function latest(): ?VerificationRequest
    {
        return Auth::user()->verificationRequests()->first();
    }

    #[Computed]
    public function canSubmit(): bool
    {
        $user = Auth::user();

        return $user->profile !== null
            && $user->profile->photo_verified_at === null
            && $user->photos()->exists()
            && $this->latest?->status !== ModerationStatus::Pending;
    }

    public function submit(): void
    {
        abort_unless($this->canSubmit, 403);

        $this->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('dating.limits.max_photo_kb')],
        ]);

        $path = app(ImageProcessor::class)->storeSingle($this->selfie, 'selfies/'.Auth::id());

        Auth::user()->verificationRequests()->create([
            'selfie_path' => $path,
            'gesture' => $this->gesture,
        ]);

        $this->selfie = null;
        unset($this->latest, $this->canSubmit);

        Flux::toast(variant: 'success', text: __('Thanks! A moderator will compare your selfie with your photos soon.'));
    }
}; ?>

<section class="mx-auto w-full max-w-xl space-y-6">
    <div>
        <flux:heading size="xl" level="1" class="flex items-center gap-2">
            <flux:icon.check-badge variant="solid" class="size-7 text-sky-500" />
            {{ __('Get the verified badge') }}
        </flux:heading>
        <flux:subheading size="lg">{{ __('Show other members you are a real person. It takes one selfie.') }}</flux:subheading>
    </div>

    @if (Auth::user()->profile?->photo_verified_at)
        <flux:callout icon="check-badge" color="sky" :heading="__('You are verified')"
            :text="__('Your profile shows the blue badge. If you replace all your photos, we may ask you to verify again.')" />
    @elseif ($this->latest?->status === \App\Enums\ModerationStatus::Pending)
        <flux:callout icon="clock" color="amber" :heading="__('Selfie received')"
            :text="__('We are reviewing it. Your selfie is only seen by our moderators and is deleted as soon as it is reviewed.')" />
    @elseif (! Auth::user()->profile || ! Auth::user()->photos()->exists())
        <flux:callout icon="information-circle" :heading="__('Add a profile photo first')"
            :text="__('We compare your selfie with your profile photos, so please add at least one photo.')">
            <x-slot name="actions">
                <flux:button size="sm" :href="route('my-profile.edit')" wire:navigate>{{ __('Go to my profile') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($this->latest?->status === \App\Enums\ModerationStatus::Rejected)
        <flux:callout icon="exclamation-triangle" color="red" :heading="__('Your last selfie was not approved')"
            :text="$this->latest->notes ?: __('We could not match it to your photos. Please try again with good lighting.')" />
    @endif

    @if ($this->canSubmit)
        <flux:card class="space-y-5">
            <div>
                <flux:text>{{ __('Take a selfie doing this pose:') }}</flux:text>
                <flux:heading size="lg" class="mt-1">“{{ $gesture }}”</flux:heading>
            </div>

            <form wire:submit="submit" class="space-y-5">
                <flux:input type="file" wire:model="selfie" accept="image/*" capture="user" :label="__('Your selfie')" />

                @if ($selfie && method_exists($selfie, 'isPreviewable') && $selfie->isPreviewable())
                    <img src="{{ $selfie->temporaryUrl() }}" alt="" class="max-h-64 rounded-xl" />
                @endif

                <flux:text class="text-xs">{{ __('Your selfie is private: it is never shown on your profile and is deleted once a moderator has reviewed it.') }}</flux:text>

                <flux:button type="submit" variant="primary" data-test="submit-selfie">{{ __('Send for review') }}</flux:button>
            </form>
        </flux:card>
    @endif
</section>
