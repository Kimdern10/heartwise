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

    /**
     * Suggested openers, shown until I've sent my first message in this chat.
     *
     * @return list<string>
     */
    #[Computed]
    public function icebreakers(): array
    {
        $iHaveWritten = $this->match->messages()->where('sender_id', Auth::id())->exists();

        return $iHaveWritten ? [] : \App\Support\Icebreakers::for(Auth::user(), $this->other);
    }

    public function poll(): void
    {
        // The other person unmatched (or blocked) while this chat was open: close it gently.
        $match = UserMatch::find($this->matchId);
        $other = $match?->otherUser(Auth::user());

        if (! $match?->isActive() || ! $other?->isActive() || Auth::user()->hasBlockBetween($other)) {
            Flux::toast(text: __('This conversation has ended.'));
            $this->redirectRoute('messages.index', navigate: true);

            return;
        }

        $this->markRead();
        unset($this->messages);
    }

    /**
     * The browser shows the message straight away and passes its text here;
     * returns false if it couldn't be sent, so the browser can put it back.
     */
    public function send(SendMessage $sendMessage, ?string $text = null): bool
    {
        try {
            $sendMessage->handle(Auth::user(), $this->match, $text ?? $this->body);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return false;
        }

        $this->body = '';
        unset($this->messages, $this->icebreakers);
        $this->dispatch('message-sent');

        return true;
    }

    // "Plan a date safely" form
    public string $planWhen = '';
    public string $planPlace = '';
    public string $planFriendName = '';
    public string $planFriendEmail = '';
    public string $planNote = '';

    /**
     * Share date plans with a trusted friend, who is alerted if the member
     * doesn't check in afterwards.
     */
    public function shareDatePlan(): void
    {
        $this->validate([
            'planWhen' => ['required', 'date'],
            'planPlace' => ['required', 'string', 'max:200'],
            'planFriendName' => ['required', 'string', 'max:60'],
            'planFriendEmail' => ['required', 'email', 'max:255', new \App\Rules\NotDisposableEmail],
            'planNote' => ['nullable', 'string', 'max:300'],
        ]);

        if (! \Illuminate\Support\Facades\RateLimiter::attempt('date-plan:'.Auth::id(), 3, fn () => true, 86400)) {
            $this->addError('planWhen', __('You can share up to 3 date plans a day.'));

            return;
        }

        $tz = request()->cookie('tz');
        $tz = is_string($tz) && in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'America/Chicago';
        $meetingAt = \Illuminate\Support\Carbon::parse($this->planWhen, $tz)->utc();

        if ($meetingAt->lt(now()->subHour()) || $meetingAt->gt(now()->addDays(30))) {
            $this->addError('planWhen', __('Pick a time in the next 30 days.'));

            return;
        }

        $checkin = Auth::user()->hasMany(\App\Models\DateCheckin::class)->create([
            'match_id' => $this->matchId,
            'match_name' => $this->other->displayName().', '.$this->other->age().' — '.$this->other->profile?->location(),
            'meeting_at' => $meetingAt,
            'timezone' => $tz,
            'place' => $this->planPlace,
            'friend_name' => $this->planFriendName,
            'friend_email' => $this->planFriendEmail,
            'note' => $this->planNote ?: null,
        ]);

        try {
            \Illuminate\Support\Facades\Notification::route('mail', [$checkin->friend_email => $checkin->friend_name])
                ->notify(new \App\Notifications\DateCheckinFriendMail($checkin, 'plan'));
        } catch (\Throwable $e) {
            report($e);
        }

        $this->reset('planWhen', 'planPlace', 'planFriendName', 'planFriendEmail', 'planNote');
        Flux::modal('date-plan')->close();
        Flux::toast(variant: 'success', heading: __('Plan shared with :friend', ['friend' => $checkin->friend_name]),
            text: __('After your date, tap “I’m safe” on your Home page. If you don’t within 3 hours of the start time, we’ll email them.'));
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
@php($isPremium = Auth::user()->isPremium())
@php($online = $other->profile?->show_last_active && $other->last_active_at?->gt(now()->subMinutes(15)))

<section class="mx-auto flex h-[calc(100svh-12rem)] w-full max-w-3xl flex-col lg:h-[calc(100svh-4rem)]">
    {{-- Messages appear the moment you press send; the server saves them in the background. --}}
    <div class="hw-card flex h-full flex-col overflow-hidden"
        x-data="{
            draft: '',
            pending: [],
            submit() {
                const text = this.draft.trim();
                if (! text) return;
                const id = Date.now();
                this.pending.push({ id, text });
                this.draft = '';
                this.$nextTick(() => this.$dispatch('message-sent'));
                const done = (ok) => {
                    this.pending = this.pending.filter((m) => m.id !== id);
                    if (ok !== true && ! this.draft) this.draft = text;
                };
                this.$wire.send(text).then(done, () => done(false));
                // If the connection drops, give the text back instead of waiting forever.
                setTimeout(() => { if (this.pending.some((m) => m.id === id)) done(false) }, 20000);
            },
        }">
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
            <div class="ms-auto flex shrink-0 items-center gap-1">
                <flux:dropdown align="end">
                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('More')" />
                    <flux:menu>
                        <flux:menu.item icon="user" :href="route('people.show', $other)" wire:navigate>{{ __('View profile') }}</flux:menu.item>
                        <flux:menu.item icon="shield-check" x-on:click="$flux.modal('date-plan').show()" data-test="plan-date">{{ __('Plan a date safely') }}</flux:menu.item>
                        <flux:menu.item icon="arrow-uturn-left" wire:click="unmatch" data-confirm-danger data-confirm="{{ __('Unmatch :name? The chat will disappear for both of you.', ['name' => $other->displayName()]) }}" data-test="unmatch">{{ __('Unmatch') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <livewire:report-block :user-id="$other->id" compact :key="'rb-chat-'.$other->id" />
            </div>
        </div>

        {{-- Messages --}}
        <div wire:poll.3s.keep-alive="poll"
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
                            {{ $message->created_at->isToday() ? $message->created_at->format('g:i A') : $message->created_at->format('D, M j · g:i A') }}
                            {{-- Read receipts are a Premium perk --}}
                            @if ($mine && $message->read_at && $isPremium) · <span class="text-brand-500">{{ __('Seen') }}</span> @endif
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

            {{-- Sending: shown instantly, replaced by the saved message a moment later --}}
            <div wire:ignore class="space-y-3">
                <template x-for="m in pending" :key="m.id">
                    <div class="flex justify-end" data-test="pending-message">
                        <div class="max-w-[80%] space-y-1">
                            <div class="bg-brand-gradient whitespace-pre-line break-words rounded-3xl rounded-br-lg px-4 py-2.5 text-[15px] leading-relaxed text-white shadow-sm" x-text="m.text"></div>
                            <div class="px-2 text-end text-[11px] text-zinc-400">{{ __('Sending…') }}</div>
                        </div>
                    </div>
                </template>
            </div>
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

        {{-- Icebreakers: tap one to put it in the message box --}}
        @if ($this->icebreakers)
            <div class="flex gap-2 overflow-x-auto border-t border-zinc-100 px-3 pt-3 dark:border-zinc-800" data-test="icebreakers">
                <span class="flex shrink-0 items-center gap-1 text-xs font-semibold text-zinc-500"><flux:icon.light-bulb class="size-4" />{{ __('Ideas:') }}</span>
                @foreach ($this->icebreakers as $idea)
                    <button type="button" x-on:click="draft = @js($idea); $nextTick(() => $root.querySelector('textarea')?.focus())"
                        class="shrink-0 rounded-full border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium transition hover:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-white">
                        {{ $idea }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Composer --}}
        <form x-on:submit.prevent="submit()" class="flex items-end gap-2 border-t border-zinc-100 p-3 dark:border-zinc-800">
            <flux:textarea x-model="draft" rows="auto" resize="none" maxlength="1000" class="flex-1"
                :placeholder="__('Write a message…')" :aria-label="__('Message')"
                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); submit() }" />
            <button type="submit" data-test="send-message" aria-label="{{ __('Send') }}"
                class="bg-brand-gradient flex size-11 shrink-0 items-center justify-center rounded-full text-white shadow-lg shadow-brand-500/30 transition hover:scale-105 active:scale-95">
                <flux:icon.paper-airplane class="size-5" />
            </button>
        </form>
    </div>

    {{-- Date check-in: share plans with a trusted friend --}}
    <flux:modal name="date-plan" class="w-full max-w-md">
        <form wire:submit="shareDatePlan" class="space-y-4">
            <div>
                <flux:heading size="lg" class="font-display !text-2xl">{{ __('Plan a date safely') }}</flux:heading>
                <flux:text>{{ __('Tell a friend where you’ll be. If you don’t tap “I’m safe” within 3 hours of the start time, we’ll email them so they can check on you.') }}</flux:text>
            </div>
            <flux:input type="datetime-local" wire:model="planWhen" :label="__('When')" />
            <flux:input wire:model="planPlace" :label="__('Where (name and address)')" maxlength="200" :placeholder="__('e.g. Jo’s Coffee, 1300 S Congress Ave')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="planFriendName" :label="__('Friend’s name')" maxlength="60" />
                <flux:input type="email" wire:model="planFriendEmail" :label="__('Friend’s email')" />
            </div>
            <flux:textarea wire:model="planNote" rows="2" maxlength="300" :label="__('Note for your friend (optional)')" />
            <flux:text class="text-xs">{{ __('Your friend will see :name’s first name, age and city, plus the time and place. Meet in a public place and arrange your own ride.', ['name' => $other->displayName()]) }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="shield-check" data-test="share-plan">{{ __('Share with my friend') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
