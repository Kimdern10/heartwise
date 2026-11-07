@props(['user'])

@if ($user->isPhotoVerified())
    <flux:tooltip :content="__('Photo verified: this member passed a selfie check')">
        <flux:icon.check-badge variant="solid" {{ $attributes->class('size-5 text-sky-500') }} />
    </flux:tooltip>
@endif
@if ($user->isIdVerified())
    <flux:tooltip :content="__('ID verified: this member’s photo ID and age were checked')">
        <flux:icon.identification variant="solid" {{ $attributes->class('size-5 text-emerald-600') }} data-test="id-badge" />
    </flux:tooltip>
@endif
