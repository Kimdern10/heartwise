<?php

use App\Actions\Dating\SendMessage;
use App\Exceptions\DatingActionException;
use App\Models\User;
use App\Models\UserMatch;
use App\Support\MessageScreener;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $matchId;

    public string $body = '';

    public function mount(UserMatch $match): void
    {
        $viewer = Auth::user();

        abort_unless($match->includes($viewer) && $match->isActive(), 404);

        $other = $match->otherUser($viewer);
        abort_if(! $other->isActive() || $viewer->hasBlockBetween($other), 404);

        $this->matchId = $match->id;
        $this->markRead();
    }

    #[Computed]
    public function match(): UserMatch
    {
        return UserMatch::with(['userOne.profile', 'userTwo.profile'])->findOrFail($this->matchId);
    }

    #[Computed]
    public function other(): User
    {
        return $this->match->otherUser(Auth::user());
    }

    #[Computed]
    public function messages()
    {
        return $this->match->messages()->latest('id')->limit(100)->get()->reverse()->values();
    }

    public function poll(): void
    {
        $this->markRead();
        unset($this->messages);
    }

    public function send(SendMessage $sendMessage): void
    {
        try {
            $sendMessage->handle(Auth::user(), $this->match, $this->body);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->body = '';
        unset($this->messages);
        $this->dispatch('message-sent');
    }

    public function unmatch(): void
    {
        $this->match->unmatch(Auth::user());

        Flux::toast(text: __('Unmatched. You won’t see each other again.'));
        $this->redirectRoute('messages.index', navigate: true);
    }

    /**
     * Ask "Did you meet?" once the chat has been going a few days.
     */
    #[Computed]
    public function askWeMet(): bool
    {
        return $this->match->created_at->lt(now()->subDays(3))
            && $this->match->messages()->count() >= 6
            && ! $this->match->feedback()->where('user_id', Auth::id())->exists();
    }

    public function weMet(bool $met, ?int $rating = null): void
    {
        $this->match->feedback()->updateOrCreate(
            ['user_id' => Auth::id()],
            ['met' => $met, 'rating' => $met && $rating ? max(1, min(5, $rating)) : null],
        );

        unset($this->askWeMet);
        Flux::toast(variant: 'success', text: $met ? __('Thanks! That helps us find you better matches.') : __('Thanks for letting us know.'));
    }

    private function markRead(): void
    {
        $this->match->messages()
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        return $this->view()->title(__('Chat with :name', ['name' => $this->other->displayName()]));
    }
}; ?>

@php($other = $this->other)
@php($online = $other->profile?->show_last_active && $other->last_active_at?->gt(now()->subMinutes(15)))

<section class="mx-auto flex h-[calc(100svh-12rem)] w-full max-w-3xl flex-col lg:h-[calc(100svh-4rem)]">
    <div class="hw-card flex h-full flex-col overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('messages.index')" wire:navigate :aria-label="__('Back to messages')" />
            <a href="{{ route('people.show', $other) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                <div class="relative shrink-0">
                    <x-member-photo :user="$other" class="size-11 rounded-full" />
                    @if ($online)
                        <span class="absolute bottom-0 end-0 size-3 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"></span>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 truncate font-bold">{{ $other->displayName() }} <x-verified-badge :user="$other" class="size-4" /></p>
                    <p class="text-xs text-zinc-500">{{ $online ? __('Active now') : $other->profile?->location() }}</p>
                </div>
            </a>
            <div class="ms-auto flex items-center gap-1">
                <flux:dropdown align="end">
                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More')" />
                    <flux:menu>
                        <flux:menu.item icon="user" :href="route('people.show', $other)" wire:navigate>{{ __('View profile') }}</flux:menu.item>
                        <flux:menu.item icon="arrow-uturn-left" wire:click="unmatch" data-confirm-danger data-confirm="{{ __('Unmatch :name? The chat will disappear for both of you.', ['name' => $other->displayName()]) }}" data-test="unmatch">{{ __('Unmatch') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <livewire:report-block :user-id="$other->id" :key="'rb-chat-'.$other->id" />
            </div>
        </div>

        {{-- Messages --}}
        <div wire:poll.4s="poll"
            x-data="{ toBottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
            x-init="toBottom()"
            x-on:message-sent.window="$nextTick(() => toBottom())"
            class="flex-1 space-y-3 overflow-y-auto bg-zinc-50/60 px-4 py-5 dark:bg-black/20">
            <div class="mx-auto flex max-w-md items-start gap-2 rounded-2xl bg-brand-50 p-3 text-xs text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                <flux:icon.shield-check class="size-4 shrink-0" />
                <span>{{ __('Never send money, gift cards, crypto or bank details to someone you met online. Real matches will not ask.') }}</span>
            </div>

            @forelse ($this->messages as $message)
                @php($mine = $message->sender_id === Auth::id())
                <div wire:key="msg-{{ $message->id }}" class="bubble-in flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] space-y-1">
                        <div class="whitespace-pre-line break-words px-4 py-2.5 text-[15px] leading-relaxed shadow-sm {{ $mine ? 'bg-brand-gradient rounded-3xl rounded-br-lg text-white' : 'rounded-3xl rounded-bl-lg bg-white text-zinc-900 dark:bg-zinc-800 dark:text-white' }}">{{ $message->body }}</div>
                        @if (! $mine && $warning = \App\Support\MessageScreener::warning($message->flag))
                            <div class="flex items-start gap-1.5 rounded-2xl bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200" data-test="scam-warning">
                                <flux:icon.exclamation-triangle class="size-4 shrink-0" />
                                <span>{{ $warning }}</span>
                            </div>
                        @endif
                        <div class="px-2 text-[11px] text-zinc-400 {{ $mine ? 'text-end' : '' }}">
                            {{ $message->created_at->format('g:i A') }}
                            @if ($mine && $message->read_at) · <span class="text-brand-500">{{ __('Seen') }}</span> @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center animate-pop">
                    <div class="bg-brand-gradient mx-auto flex size-14 items-center justify-center rounded-full shadow-lg shadow-brand-500/30 animate-float">
                        <flux:icon.heart variant="solid" class="size-7 text-white" />
                    </div>
                    <p class="mt-4 font-bold">{{ __('You matched with :name!', ['name' => $other->displayName()]) }}</p>
                    <p class="text-sm text-zinc-500">{{ __('Break the ice — ask about something in their profile.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($this->askWeMet)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 bg-amber-50 px-4 py-3 dark:border-zinc-800 dark:bg-amber-500/10" x-data="{ met: false }">
                <p class="text-sm font-semibold" x-show="! met">{{ __('Have you met :name in person yet?', ['name' => $other->displayName()]) }}</p>
                <div class="flex gap-2" x-show="! met">
                    <flux:button size="xs" wire:click="weMet(false)">{{ __('Not yet') }}</flux:button>
                    <flux:button size="xs" variant="primary" x-on:click="met = true">{{ __('We met!') }}</flux:button>
                </div>
                <div class="flex flex-wrap items-center gap-2" x-show="met" x-cloak>
                    <span class="text-sm font-semibold">{{ __('Would you see them again?') }}</span>
                    @foreach ([5 => __('Definitely'), 3 => __('Maybe'), 1 => __('No')] as $rating => $label)
                        <flux:button size="xs" wire:click="weMet(true, {{ $rating }})">{{ $label }}</flux:button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Composer --}}
        <form wire:submit="send" class="flex items-end gap-2 border-t border-zinc-100 p-3 dark:border-zinc-800">
            <flux:textarea wire:model="body" rows="auto" resize="none" maxlength="1000" class="flex-1"
                :placeholder="__('Write a message…')" :aria-label="__('Message')"
                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }" />
            <button type="submit" data-test="send-message" aria-label="{{ __('Send') }}"
                class="bg-brand-gradient flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow-lg shadow-brand-500/30 transition hover:scale-105 active:scale-95">
                <flux:icon.paper-airplane class="size-5" wire:loading.remove wire:target="send" />
                <flux:icon.loading class="size-5" wire:loading wire:target="send" />
            </button>
        </form>
    </div>
</section>
