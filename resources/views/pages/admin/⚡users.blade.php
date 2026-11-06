<?php

use App\Livewire\Concerns\ModeratesMembers;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Members')] class extends Component {
    use ModeratesMembers, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $role = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function members()
    {
        return User::query()
            ->with('profile')
            ->withCount(['photos'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhereHas('profile', fn ($q) => $q->where('display_name', 'like', '%'.$this->search.'%'))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->role, fn ($q) => $q->where('role', $this->role))
            ->latest()
            ->paginate(25);
    }

    protected function afterModeration(): void
    {
        unset($this->members);
    }
}; ?>

<section class="w-full">
    @include('partials.admin-nav', ['heading' => __('Members')])

    <div class="mb-4 flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" class="max-w-xs" />
        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (\App\Enums\AccountStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="role" class="max-w-48" data-test="role-filter">
            <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
            @foreach (\App\Enums\Role::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}s</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if (auth()->user()->isAdmin())
        <flux:callout icon="information-circle" class="mb-4">
            <flux:callout.text>
                {{ __('Roles: Moderators can review profiles, photos and selfies, handle reports and flagged messages, and suspend members. Only admins can ban, change security settings and give roles. Staff accounts use this panel instead of the dating side.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:table :paginate="$this->members">
        <flux:table.columns>
            <flux:table.column>{{ __('Member') }}</flux:table.column>
            <flux:table.column>{{ __('Age') }}</flux:table.column>
            <flux:table.column>{{ __('Profile') }}</flux:table.column>
            <flux:table.column>{{ __('Reports') }}</flux:table.column>
            <flux:table.column>{{ __('Joined') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->members as $member)
                <flux:table.row :key="$member->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-1">
                            @if ($member->profile)
                                <a href="{{ route('people.show', $member) }}" wire:navigate class="font-semibold underline-offset-2 hover:underline">{{ $member->displayName() }}</a>
                            @else
                                {{ $member->displayName() }}
                            @endif
                            <x-verified-badge :user="$member" class="size-4" />
                        </div>
                        <span class="text-xs text-zinc-500">{{ $member->email }}</span>

                        @if ($member->subscribed('premium')) <flux:badge size="sm" color="amber">{{ __('Premium') }}</flux:badge> @endif
                        @if ($member->flag_reason)
                            <flux:tooltip :content="$member->flag_reason"><flux:badge size="sm" color="red" icon="shield-exclamation">{{ __('Flagged') }}</flux:badge></flux:tooltip>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $member->age() }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($member->profile)
                            <flux:badge size="sm" :color="$member->profile->status->color()">{{ $member->profile->status->label() }}</flux:badge>
                            <span class="text-xs text-zinc-500">{{ trans_choice(':count photo|:count photos', $member->photos_count) }}</span>
                        @else
                            <span class="text-xs text-zinc-500">{{ __('none yet') }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ \App\Models\Report::where('reported_id', $member->id)->count() }}</flux:table.cell>
                    <flux:table.cell>{{ $member->created_at->toFormattedDateString() }}</flux:table.cell>
                    <flux:table.cell>
                        @if (auth()->user()->isAdmin() && ! $member->is(auth()->user()))
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon:trailing="chevron-down" data-test="role-{{ $member->id }}">{{ $member->role->label() }}</flux:button>
                                <flux:menu>
                                    @foreach (\App\Enums\Role::cases() as $option)
                                        @unless ($option === $member->role)
                                            <flux:menu.item wire:click="changeRole({{ $member->id }}, '{{ $option->value }}')"
                                                data-confirm="{{ __('Make :name a :role? :what', ['name' => $member->displayName(), 'role' => strtolower($option->label()), 'what' => $option->description()]) }}">
                                                {{ __('Make :role', ['role' => strtolower($option->label())]) }}
                                            </flux:menu.item>
                                        @endunless
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @else
                            <span class="text-sm">{{ $member->role->label() }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>@include('partials.admin-member-actions', ['member' => $member])</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
