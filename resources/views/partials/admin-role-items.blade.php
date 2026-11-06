{{-- "Make admin / moderator / member" menu items. Admins only; the parent uses ModeratesMembers. --}}
@php
    $roleIcons = ['admin' => 'shield-check', 'moderator' => 'user-group', 'member' => 'user'];
@endphp

@foreach (\App\Enums\Role::cases() as $option)
    @unless ($option === $member->role)
        <flux:menu.item :icon="$roleIcons[$option->value] ?? 'user'"
            wire:click="changeRole({{ $member->id }}, '{{ $option->value }}')"
            data-confirm="{{ __('Make :name :article :role? :what', ['name' => $member->displayName(), 'article' => $option === \App\Enums\Role::Admin ? 'an' : 'a', 'role' => strtolower($option->label()), 'what' => $option->description()]) }}">
            {{ $option === \App\Enums\Role::Member ? __('Remove staff role (back to member)') : __('Make :role', ['role' => strtolower($option->label())]) }}
        </flux:menu.item>
    @endunless
@endforeach
