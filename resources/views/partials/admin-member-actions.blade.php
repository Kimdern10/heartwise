{{-- Expects $member (User). The parent component must use the ModeratesMembers trait. --}}
@if ($member->is_admin)
    <flux:badge size="sm">{{ __('Admin') }}</flux:badge>
@elseif ($member->isActive())
    <flux:dropdown>
        <flux:button size="sm" icon:trailing="chevron-down">{{ __('Take action') }}</flux:button>
        <flux:menu>
            <flux:menu.item icon="pause" wire:click="suspend({{ $member->id }}, 7)" data-confirm-danger data-confirm="{{ __('Suspend this member for 7 days?') }}">{{ __('Suspend 7 days') }}</flux:menu.item>
            <flux:menu.item icon="pause" wire:click="suspend({{ $member->id }}, 30)" data-confirm-danger data-confirm="{{ __('Suspend this member for 30 days?') }}">{{ __('Suspend 30 days') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="no-symbol" variant="danger" wire:click="ban({{ $member->id }})" data-confirm-danger data-confirm="{{ __('Ban this member permanently? They will be logged out and hidden.') }}">{{ __('Ban') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
@else
    <div class="flex items-center gap-2">
        <flux:badge size="sm" color="red">{{ $member->status->label() }}</flux:badge>
        <flux:button size="sm" wire:click="reactivate({{ $member->id }})">{{ __('Reactivate') }}</flux:button>
    </div>
@endif
