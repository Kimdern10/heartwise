@props(['user'])

@if ($user->is_sample)
    <flux:badge size="sm" color="zinc" {{ $attributes }}>{{ __('Sample (local test data)') }}</flux:badge>
@endif
