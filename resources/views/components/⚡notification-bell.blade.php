<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function items()
    {
        return Auth::user()->notifications()->latest()->limit(12)->get();
    }

    #[Computed]
    public function unread(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->items, $this->unread);
    }

    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }
}; ?>

<div class="relative" wire:poll.30s x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <button type="button" x-ref="bell" x-on:click="open = ! open" class="relative flex size-10 items-center justify-center rounded-full transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
        aria-label="{{ __('Notifications') }}" data-test="bell">
        <flux:icon.bell class="size-6" />
        @if ($this->unread)
            <span class="absolute end-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ min($this->unread, 99) }}</span>
        @endif
    </button>

    {{-- Teleported to <body> and anchored to the bell, so the sidebar can't clip it --}}
    <template x-teleport="body">
    <div x-show="open" x-cloak x-on:click.outside="if (! $refs.bell.contains($event.target)) open = false" x-transition.origin.top
        x-anchor.bottom-start.offset.8="$refs.bell"
        class="z-[60] w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl border border-zinc-200 bg-white text-zinc-900 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900 dark:text-white">
        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <p class="font-semibold">{{ __('Notifications') }}</p>
            @if ($this->unread)
                <button type="button" wire:click="markAllRead" class="text-xs font-semibold text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Mark all read') }}</button>
            @endif
        </div>
        <div class="max-h-96 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
            @forelse ($this->items as $n)
                @php($icon = ['match' => 'heart', 'message' => 'chat-bubble-left-right', 'like' => 'sparkles', 'checkin' => 'shield-check'][$n->data['kind'] ?? ''] ?? 'bell')
                <button type="button" wire:click="open('{{ $n->id }}')" wire:key="n-{{ $n->id }}"
                    class="flex w-full items-start gap-3 px-4 py-3 text-start transition hover:bg-zinc-50 dark:hover:bg-zinc-800 {{ $n->read_at ? 'opacity-70' : '' }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $n->read_at ? 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' : 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' }}">
                        <flux:icon :name="$icon" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold">{{ $n->data['title'] ?? '' }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ $n->data['body'] ?? '' }}</span>
                        <span class="block text-[11px] text-zinc-400">{{ $n->created_at->diffForHumans() }}</span>
                    </span>
                    @unless ($n->read_at)
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-600"></span>
                    @endunless
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('Nothing yet. Likes, matches and messages will show up here.') }}</p>
            @endforelse
        </div>
        <a href="{{ route('privacy.edit') }}#notifications" wire:navigate class="block border-t border-zinc-100 px-4 py-2.5 text-center text-xs font-semibold text-zinc-500 hover:text-zinc-900 dark:border-zinc-800 dark:hover:text-white">{{ __('Email notification settings') }}</a>
    </div>
    </template>
</div>
