<?php

use App\Livewire\Concerns\LikesProfiles;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserMatch;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    use LikesProfiles;

    #[Locked]
    public int $personId;

    public function mount(User $user): void
    {
        $viewer = Auth::user();

        $visible = $viewer->is($user)
            || ($viewer->is_admin && $user->profile()->exists())
            || (User::query()->discoverable()->whereKey($user->id)->exists() && ! $viewer->hasBlockBetween($user));

        abort_unless($visible, 404);

        $this->personId = $user->id;

        ProfileView::record($viewer, $user);
    }

    #[Computed]
    public function person(): User
    {
        $person = User::with(['profile', 'approvedPhotos', 'prompts'])->findOrFail($this->personId);

        // Admins reviewing a profile see every photo, including ones still waiting for review.
        if (Auth::user()->is_admin) {
            $person->setRelation('approvedPhotos', $person->photos()->get());
        }

        return $person;
    }

    #[Computed]
    public function myLike(): ?Like
    {
        return Like::query()->where('user_id', Auth::id())->where('target_id', $this->personId)->first();
    }

    #[Computed]
    public function theirLike(): ?Like
    {
        return Like::query()->where('user_id', $this->personId)->where('target_id', Auth::id())->where('liked', true)->first();
    }

    #[Computed]
    public function match(): ?UserMatch
    {
        return Auth::user()->matchWith($this->person);
    }

    #[Computed]
    public function isFavorite(): bool
    {
        return Auth::user()->hasFavorited($this->person);
    }

    public function toggleFavorite(): void
    {
        $existing = Favorite::query()->where('user_id', Auth::id())->where('favorite_id', $this->personId);

        if ($existing->exists()) {
            $existing->delete();
            Flux::toast(text: __('Removed from favorites.'));
        } else {
            Favorite::create(['user_id' => Auth::id(), 'favorite_id' => $this->personId]);
            Flux::toast(variant: 'success', text: __('Saved to favorites.'));
        }

        unset($this->isFavorite);
    }

    protected function afterLike(User $target, ?UserMatch $match): void
    {
        unset($this->myLike, $this->match);

        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        } else {
            Flux::toast(variant: 'success', text: __('Like sent! If :name likes you back, it’s a match.', ['name' => $target->displayName()]));
        }
    }

    protected function afterPass(): void
    {
        $this->redirectRoute('discover', navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->person->displayName());
    }
}; ?>

@php($person = $this->person)
@php($isAdmin = Auth::user()->is_admin)
{{-- For admins the page is a read-only review: no likes, favorites or reports. --}}
@php($isMe = Auth::user()->is($person) || $isAdmin)
@php($canLike = ! $isMe && ! $this->match && ! $this->myLike?->liked)

<section class="w-full pb-24">
    @if ($isAdmin)
        <div class="mx-auto mb-4 max-w-xl space-y-3 rounded-3xl bg-zinc-900 p-5 text-white dark:bg-white dark:text-zinc-900">
            <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider opacity-70"><flux:icon.shield-check class="size-4" /> {{ __('Admin review') }}</p>
            <p class="font-semibold">{{ $person->name }} · {{ $person->email }}</p>
            <p class="text-sm opacity-80">
                {{ __('Profile: :status', ['status' => $person->profile->status->label()]) }} ·
                {{ __('Account: :status', ['status' => $person->status->label()]) }} ·
                {{ __('Joined :date', ['date' => $person->created_at->toFormattedDateString()]) }}
                @if ($person->flag_reason) · <span class="font-semibold text-amber-400 dark:text-amber-600">{{ __('Flagged: :r', ['r' => $person->flag_reason]) }}</span> @endif
            </p>
            <flux:button size="sm" icon="arrow-left" :href="route('admin.users')" wire:navigate>{{ __('Back to members') }}</flux:button>
        </div>
    @endif

    <div class="mx-auto mb-2 flex max-w-xl items-center justify-between {{ $isAdmin ? 'hidden' : '' }}">
        <flux:button size="sm" variant="ghost" icon="arrow-left" x-on:click="history.length > 1 ? history.back() : Livewire.navigate('{{ route('discover') }}')">{{ __('Back') }}</flux:button>
        @unless ($isMe)
            <flux:button size="sm" variant="ghost" icon="star" wire:click="toggleFavorite" data-test="favorite-button"
                class="{{ $this->isFavorite ? '!text-amber-500' : '' }}">
                {{ $this->isFavorite ? __('Favorited') : __('Favorite') }}
            </flux:button>
        @else
            <flux:badge>{{ __('This is how others see you') }}</flux:badge>
        @endunless
    </div>

    @if ($this->theirLike && ! $this->match && ! $isMe)
        <div class="mx-auto mb-4 max-w-xl rounded-3xl bg-zinc-900 p-5 text-white dark:bg-white dark:text-zinc-900">
            <p class="flex items-center gap-2 font-semibold">
                @if ($this->theirLike->is_spark)<flux:icon.bolt variant="solid" class="size-5 text-amber-400" />@else<flux:icon.heart variant="solid" class="size-5 text-brand-400" />@endif
                {{ __(':name liked you', ['name' => $person->displayName()]) }}
            </p>
            @if ($this->theirLike->comment)
                <p class="font-display mt-2 text-xl">“{{ $this->theirLike->comment }}”</p>
            @endif
            <p class="mt-1 text-sm opacity-70">{{ __('Like anything on their profile to match.') }}</p>
        </div>
    @endif

    @include('partials.profile-feed', ['person' => $person, 'canLike' => $canLike])

    @unless ($isMe)
        <div class="mx-auto mt-6 max-w-xl space-y-4">
            @if ($this->match)
                <flux:button variant="primary" class="w-full" icon="chat-bubble-left-right" :href="route('messages.show', $this->match)" wire:navigate>{{ __('Send a message') }}</flux:button>
            @elseif ($this->myLike?->liked)
                <p class="text-center text-sm text-zinc-500">{{ __('You liked :name. If they like you back, it’s a match.', ['name' => $person->displayName()]) }}</p>
            @endif

            <div class="flex justify-center border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <livewire:report-block :user-id="$person->id" :key="'rb-'.$person->id" />
            </div>
        </div>

        @if ($canLike)
            <div class="pointer-events-none fixed inset-x-0 bottom-24 z-30 flex justify-between px-6 lg:bottom-8 lg:ps-72">
                <button type="button" wire:click="passOn({{ $person->id }})" aria-label="{{ __('Pass') }}"
                    class="pointer-events-auto flex size-14 items-center justify-center rounded-full bg-white text-zinc-700 shadow-xl ring-1 ring-zinc-200 transition hover:scale-110 active:scale-95 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-700">
                    <flux:icon.x-mark class="size-7" />
                </button>
                <button type="button" wire:click="openLike({{ $person->id }})" aria-label="{{ __('Like') }}" data-test="like-button"
                    class="pointer-events-auto flex size-14 items-center justify-center rounded-full bg-zinc-900 text-white shadow-xl transition hover:scale-110 active:scale-95 dark:bg-white dark:text-zinc-900">
                    <flux:icon.heart variant="solid" class="size-7" />
                </button>
            </div>
        @endif

        @include('partials.like-modal')
    @endunless
</section>
