@props(['user'])

@if ($user->isPhotoVerified())
    <flux:tooltip :content="__('Photo verified: this member passed a selfie check')">
        <flux:icon.check-badge variant="solid" {{ $attributes->class('size-5 text-sky-500') }} />
    </flux:tooltip>
@endif
