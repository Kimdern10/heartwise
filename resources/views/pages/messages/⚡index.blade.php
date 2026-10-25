<?php

use App\Support\Conversations;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component {
    public string $search = '';

    #[Computed]
    public function conversations()
    {
        $all = Conversations::for(Auth::user());

        if ($this->search === '') {
            return $all;
        }

        return $all->filter(fn ($match) => str_contains(
            strtolower($match->otherUser(Auth::user())->displayName()),
            strtolower($this->search),
        ));
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6" wire:poll.15s>
    <div class="flex flex-wrap items-end justify-between gap-4 animate-fade-up">
        <div>
            <flux:heading size="xl" level="1" class="!text-3xl !font-extrabold">{{ __('Messages') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Chat with the people you matched with.') }}</flux:subheading>
        </div>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search matches')" class="max-w-56" />
    </div>

    @if ($this->conversations->isEmpty())
        <div class="hw-card px-6 py-14 text-center animate-pop">
            <div class="bg-brand-gradient mx-auto flex size-16 items-center justify-center rounded-full shadow-lg shadow-brand-500/30 animate-float">
                <flux:icon.chat-bubble-oval-left-ellipsis class="size-8 text-white" />
            </div>
            <flux:heading size="lg" class="mt-5">{{ $search ? __('No one by that name') : __('No conversations yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('When you match with someone, you can start chatting here.') }}</flux:text>
        </div>
    @else
        <div class="hw-card stagger divide-y divide-zinc-100 overflow-hidden dark:divide-zinc-800">
            @foreach ($this->conversations as $match)
                @php($other = $match->otherUser(Auth::user()))
                @php($online = $other->profile?->show_last_active && $other->last_active_at?->gt(now()->subMinutes(15)))
                <a href="{{ route('messages.show', $match) }}" wire:navigate wire:key="conv-{{ $match->id }}"
                    class="flex items-center gap-4 p-4 transition hover:bg-brand-50/60 dark:hover:bg-white/5">
                    <div class="relative shrink-0">
                        <x-member-photo :user="$other" class="size-14 rounded-full" />
                        @if ($online)
                            <span class="absolute bottom-0.5 end-0.5 size-3.5 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"></span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold">{{ $other->displayName() }}</span>
                            <x-verified-badge :user="$other" class="size-4" />
                            <span class="ms-auto text-xs text-zinc-500">{{ ($match->last_message_at ?? $match->created_at)->diffForHumans(short: true) }}</span>
                        </div>
                        <p class="truncate text-sm {{ $match->unread_count ? 'font-semibold text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                            @if ($match->latestMessage)
                                {{ $match->latestMessage->sender_id === Auth::id() ? __('You: ') : '' }}{{ $match->latestMessage->body }}
                            @else
                                <span class="text-brand-600">{{ __('New match — say hello! 👋') }}</span>
                            @endif
                        </p>
                    </div>
                    @if ($match->unread_count)
                        <span class="bg-brand-gradient flex size-6 items-center justify-center rounded-full text-xs font-bold text-white">{{ $match->unread_count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</section>
