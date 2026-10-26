<?php

use App\Support\Conversations;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Matches')] class extends Component {
    #[Computed]
    public function matches()
    {
        return Conversations::for(Auth::user());
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <div class="animate-fade-up">
        <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('Matches') }}</flux:heading>
        <flux:subheading size="lg">{{ __('People who liked you back. Say hello!') }}</flux:subheading>
    </div>

    @if ($this->matches->isEmpty())
        <div class="hw-card mx-auto max-w-md px-6 py-14 text-center animate-pop">
            <div class="bg-brand-gradient mx-auto flex size-16 items-center justify-center rounded-full shadow-lg shadow-brand-500/30 animate-float">
                <flux:icon.heart variant="solid" class="size-8 text-white" />
            </div>
            <flux:heading size="lg" class="mt-5">{{ __('No matches yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Keep exploring — when someone you like likes you back, they show up here.') }}</flux:text>
            <flux:button class="mt-5" variant="primary" :href="route('discover')" wire:navigate>{{ __('Start discovering') }}</flux:button>
        </div>
    @else
        @php($fresh = $this->matches->filter(fn ($m) => ! $m->latestMessage))
        @if ($fresh->isNotEmpty())
            <div class="animate-fade-up">
                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-brand-600">{{ __('New matches') }}</p>
                <div class="flex gap-4 overflow-x-auto pb-2">
                    @foreach ($fresh as $match)
                        @php($other = $match->otherUser(Auth::user()))
                        <a href="{{ route('messages.show', $match) }}" wire:navigate class="group flex w-20 shrink-0 flex-col items-center gap-2" wire:key="new-{{ $match->id }}">
                            <span class="bg-brand-gradient rounded-full p-[3px] transition group-hover:scale-105">
                                <x-member-photo :user="$other" class="size-[4.5rem] rounded-full border-2 border-white dark:border-zinc-900" />
                            </span>
                            <span class="truncate text-sm font-semibold">{{ $other->displayName() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="stagger grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($this->matches as $match)
                @php($other = $match->otherUser(Auth::user()))
                <article wire:key="match-{{ $match->id }}" class="hw-card hw-card--hover group overflow-hidden">
                    <a href="{{ route('people.show', $other) }}" wire:navigate class="relative block">
                        <x-member-photo :user="$other" class="aspect-square w-full transition duration-500 group-hover:scale-[1.03]" />
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-4 text-white">
                            <div class="flex items-center gap-1.5 text-lg font-bold">
                                {{ $other->displayName() }}, {{ $other->age() }}
                                <x-verified-badge :user="$other" class="size-5" />
                            </div>
                            <p class="text-xs text-white/80">{{ __('Matched :time', ['time' => $match->created_at->diffForHumans()]) }}</p>
                        </div>
                        @if ($match->unread_count)
                            <span class="bg-brand-gradient absolute end-3 top-3 rounded-full px-2.5 py-0.5 text-xs font-bold text-white shadow">{{ __(':n new', ['n' => $match->unread_count]) }}</span>
                        @endif
                    </a>
                    <div class="p-3">
                        <flux:button size="sm" class="w-full" variant="primary" icon="chat-bubble-left-right" :href="route('messages.show', $match)" wire:navigate>
                            {{ $match->latestMessage ? __('Open chat') : __('Say hello') }}
                        </flux:button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
