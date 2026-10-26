<?php

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ProfileView;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Activity')] class extends Component {
    #[Locked]
    public string $tab = 'likes';

    public function mount(string $tab = 'likes'): void
    {
        $this->tab = $tab;
    }

    /**
     * People who liked me and are still waiting for my answer. Sparks first.
     */
    #[Computed]
    public function likes()
    {
        $me = Auth::user();

        return Like::query()
            ->where('target_id', $me->id)
            ->where('liked', true)
            ->whereNotIn('user_id', $me->blockedUserIds())
            ->whereNotIn('user_id', Like::query()->where('user_id', $me->id)->select('target_id'))
            ->whereHas('user', fn (Builder $q) => $q->discoverable())
            ->with(['user.profile', 'user.approvedPhotos', 'photo', 'prompt'])
            ->orderByDesc('is_spark')
            ->latest('updated_at')
            ->get();
    }

    #[Computed]
    public function views()
    {
        $me = Auth::user();

        return ProfileView::query()
            ->where('viewed_id', $me->id)
            ->whereNotIn('viewer_id', $me->blockedUserIds())
            ->whereHas('viewer', fn (Builder $q) => $q->discoverable())
            ->with(['viewer.profile', 'viewer.approvedPhotos'])
            ->latest('viewed_at')
            ->limit(60)
            ->get();
    }

    #[Computed]
    public function favorites()
    {
        $me = Auth::user();

        return $me->favorites()
            ->whereNotIn('favorite_id', $me->blockedUserIds())
            ->whereHas('favorite', fn (Builder $q) => $q->discoverable())
            ->with(['favorite.profile', 'favorite.approvedPhotos'])
            ->latest()
            ->get();
    }

    public function likeBack(int $userId, LikeUser $likeUser): void
    {
        $target = User::findOrFail($userId);

        try {
            $match = $likeUser->handle(Auth::user(), $target, true);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        unset($this->likes);

        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        }
    }

    public function remove(int $userId, LikeUser $likeUser): void
    {
        try {
            $likeUser->handle(Auth::user(), User::findOrFail($userId), false);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());
        }

        unset($this->likes);
    }

    public function unfavorite(int $userId): void
    {
        Favorite::query()->where('user_id', Auth::id())->where('favorite_id', $userId)->delete();
        unset($this->favorites);
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <div class="animate-fade-up">
        <flux:heading size="xl" level="1" class="font-display !text-4xl">{{ __('Activity') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Who’s interested in you, and who you’ve saved.') }}</flux:subheading>
    </div>

    <nav class="flex gap-1 overflow-x-auto rounded-full bg-zinc-100 p-1 text-sm font-semibold dark:bg-zinc-800 sm:inline-flex">
        @foreach (['likes' => [__('Likes you'), $this->likes->count()], 'views' => [__('Viewed you'), $this->views->count()], 'favorites' => [__('Favorites'), $this->favorites->count()]] as $key => [$label, $count])
            <a href="{{ route('activity', $key) }}" wire:navigate
                class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 transition {{ $tab === $key ? 'bg-white text-zinc-900 shadow dark:bg-zinc-950 dark:text-white' : 'text-zinc-500' }}">
                {{ $label }}
                @if ($count)<span class="rounded-full bg-zinc-900 px-2 text-xs text-white dark:bg-white dark:text-zinc-900">{{ $count }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'likes')
        @if ($this->likes->isEmpty())
            @include('partials.empty-state', ['icon' => 'heart', 'title' => __('No new likes yet'), 'text' => __('Complete your prompts and add more photos — profiles with three prompts get more likes.')])
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->likes as $like)
                    @php($person = $like->user)
                    <article wire:key="like-{{ $like->id }}" class="hw-card overflow-hidden {{ $like->is_spark ? 'ring-2 ring-amber-400' : '' }}" data-reveal>
                        <a href="{{ route('people.show', $person) }}" wire:navigate class="relative block">
                            <x-member-photo :user="$person" class="aspect-[4/5] w-full" />
                            @if ($like->is_spark)
                                <span class="absolute start-3 top-3 flex items-center gap-1 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-amber-950"><flux:icon.bolt variant="solid" class="size-3.5" />{{ __('Spark') }}</span>
                            @endif
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-4 text-white">
                                <p class="font-display text-2xl">{{ $person->displayName() }}, {{ $person->age() }}</p>
                            </div>
                        </a>
                        <div class="space-y-3 p-4">
                            @if ($like->prompt)
                                <div class="rounded-2xl bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-500">{{ __('Liked your answer to “:prompt”', ['prompt' => $like->prompt->prompt]) }}</p>
                                    <p class="mt-1 line-clamp-2">{{ $like->prompt->answer }}</p>
                                </div>
                            @elseif ($like->photo)
                                <div class="flex items-center gap-3 rounded-2xl bg-zinc-50 p-2 text-xs text-zinc-500 dark:bg-zinc-800">
                                    <img src="{{ $like->photo->url(true) }}" alt="" class="size-10 rounded-lg object-cover" />
                                    {{ __('Liked your photo') }}
                                </div>
                            @endif
                            @if ($like->comment)
                                <p class="font-display text-lg leading-snug">“{{ $like->comment }}”</p>
                            @endif
                            <div class="flex gap-2">
                                <flux:button size="sm" icon="x-mark" class="flex-1" wire:click="remove({{ $person->id }})">{{ __('Remove') }}</flux:button>
                                <flux:button size="sm" variant="primary" icon="heart" class="flex-1" wire:click="likeBack({{ $person->id }})" data-test="like-back-{{ $person->id }}">{{ __('Match') }}</flux:button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'views')
        @if ($this->views->isEmpty())
            @include('partials.empty-state', ['icon' => 'eye', 'title' => __('No profile views yet'), 'text' => __('When members look at your profile, they show up here.')])
        @else
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($this->views as $view)
                    <a href="{{ route('people.show', $view->viewer) }}" wire:navigate wire:key="view-{{ $view->viewer_id }}" class="hw-card hw-card--hover block overflow-hidden" data-reveal>
                        <x-member-photo :user="$view->viewer" class="aspect-square w-full" />
                        <div class="p-3">
                            <p class="font-semibold">{{ $view->viewer->displayName() }}, {{ $view->viewer->age() }}</p>
                            <p class="text-xs text-zinc-500">{{ __('Viewed :time', ['time' => $view->viewed_at->diffForHumans()]) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
        <flux:text class="text-xs">{{ __('Want to look around without showing up here? Turn on “Browse privately” in Settings → Privacy.') }}</flux:text>
    @else
        @if ($this->favorites->isEmpty())
            @include('partials.empty-state', ['icon' => 'star', 'title' => __('No favorites yet'), 'text' => __('Tap “Favorite” on a profile to save it here for later.')])
        @else
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($this->favorites as $favorite)
                    <div wire:key="fav-{{ $favorite->favorite_id }}" class="hw-card overflow-hidden" data-reveal>
                        <a href="{{ route('people.show', $favorite->favorite) }}" wire:navigate>
                            <x-member-photo :user="$favorite->favorite" class="aspect-square w-full" />
                        </a>
                        <div class="flex items-center justify-between gap-2 p-3">
                            <p class="truncate font-semibold">{{ $favorite->favorite->displayName() }}, {{ $favorite->favorite->age() }}</p>
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unfavorite({{ $favorite->favorite_id }})" :aria-label="__('Remove from favorites')" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</section>
