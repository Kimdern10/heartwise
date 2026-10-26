{{--
    Hinge-style profile: photos, prompts and details in one scrolling column,
    with a heart on each photo and prompt. Expects $person (User) and
    $canLike (bool). The parent component uses the LikesProfiles trait.
--}}
@php
    $profile = $person->profile;
    $photos = $person->approvedPhotos->values();
    $prompts = $person->prompts->values();
    $vitals = $profile->vitals();
    $viewer = auth()->user()->profile;
    $miles = $viewer?->milesFrom($profile);
    $isMe = auth()->user()->is($person);
    $score = (! $isMe && $viewer) ? \App\Support\Compatibility::score($viewer, $profile) : null;
    $reasons = (! $isMe && $viewer) ? \App\Support\Compatibility::reasons($viewer, $profile) : [];
    $online = $profile->show_last_active && $person->last_active_at?->gt(now()->subMinutes(15));

    // Interleave: photo, prompt, details, photo, prompt, photo, bio, prompt, remaining photos...
    $blocks = [];
    $blocks[] = ['photo', $photos->get(0)];
    $blocks[] = ['prompt', $prompts->get(0)];
    $blocks[] = ['vitals', null];
    $blocks[] = ['photo', $photos->get(1)];
    $blocks[] = ['prompt', $prompts->get(1)];
    $blocks[] = ['photo', $photos->get(2)];
    $blocks[] = ['bio', null];
    $blocks[] = ['prompt', $prompts->get(2)];
    foreach ($photos->slice(3) as $extra) {
        $blocks[] = ['photo', $extra];
    }
    $blocks[] = ['interests', null];
@endphp

<div class="mx-auto w-full max-w-xl space-y-4">
    {{-- Name header --}}
    <div class="flex items-start justify-between gap-4 px-1 pt-2">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-4xl leading-none">{{ $person->displayName() }}</h1>
                <x-verified-badge :user="$person" class="size-6" />
                <x-location-badge :user="$person" class="size-5" />
            </div>
            <p class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-zinc-500">
                @if ($online)
                    <span class="flex items-center gap-1.5 font-semibold text-emerald-600"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('Active now') }}</span>
                @elseif ($profile->show_last_active && $person->last_active_at)
                    <span>{{ __('Active :time', ['time' => $person->last_active_at->diffForHumans()]) }}</span>
                @endif
            </p>
        </div>
        @if ($score)
            <div class="shrink-0 text-center" title="{{ __('Compatibility based on your answers and preferences') }}">
                <div class="font-display text-3xl leading-none">{{ $score }}%</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{{ __('match') }}</div>
            </div>
        @endif
    </div>

    @foreach ($blocks as [$type, $item])
        @if ($type === 'photo' && $item)
            <figure class="group relative overflow-hidden rounded-3xl bg-zinc-100 dark:bg-zinc-800" data-reveal>
                <img src="{{ $item->url() }}" alt="{{ __('Photo of :name', ['name' => $person->displayName()]) }}" class="aspect-[4/5] w-full object-cover" loading="lazy" />
                @if ($canLike)
                    <button type="button" wire:click="openLike({{ $person->id }}, 'photo', {{ $item->id }})" aria-label="{{ __('Like this photo') }}"
                        class="absolute bottom-4 end-4 flex size-14 items-center justify-center rounded-full bg-white text-brand-600 shadow-xl transition hover:scale-110 active:scale-95 dark:bg-zinc-900" data-test="like-photo-{{ $item->id }}">
                        <flux:icon.heart class="size-7" />
                    </button>
                @endif
            </figure>
        @elseif ($type === 'prompt' && $item)
            <div class="hw-card relative px-6 pb-8 pt-6" data-reveal>
                <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-400">{{ $item->prompt }}</p>
                <p class="font-display mt-3 pe-12 text-[1.75rem] leading-snug">{{ $item->answer }}</p>
                @if ($canLike)
                    <button type="button" wire:click="openLike({{ $person->id }}, 'prompt', {{ $item->id }})" aria-label="{{ __('Like this answer') }}"
                        class="absolute bottom-4 end-4 flex size-12 items-center justify-center rounded-full bg-white text-brand-600 shadow-lg ring-1 ring-zinc-200 transition hover:scale-110 active:scale-95 dark:bg-zinc-800 dark:ring-zinc-700" data-test="like-prompt-{{ $item->id }}">
                        <flux:icon.heart class="size-6" />
                    </button>
                @endif
            </div>
        @elseif ($type === 'vitals')
            <div class="hw-card overflow-hidden" data-reveal>
                <div class="flex divide-x divide-zinc-200 overflow-x-auto py-4 text-sm font-semibold dark:divide-zinc-800">
                    <span class="flex shrink-0 items-center gap-2 px-5"><flux:icon.cake class="size-5 text-zinc-400" />{{ $person->age() }}</span>
                    <span class="flex shrink-0 items-center gap-2 px-5"><flux:icon.user class="size-5 text-zinc-400" />{{ $profile->gender->label() }}</span>
                    @if ($profile->heightLabel())
                        <span class="flex shrink-0 items-center gap-2 px-5"><flux:icon.arrows-up-down class="size-5 text-zinc-400" />{{ $profile->heightLabel() }}</span>
                    @endif
                    <span class="flex shrink-0 items-center gap-2 px-5"><flux:icon.map-pin class="size-5 text-zinc-400" />{{ $profile->location() }}@if ($miles) · {{ $miles }} mi @endif</span>
                </div>
                <div class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                    @if ($profile->job_title)
                        <p class="flex items-center gap-3 px-5 py-3"><flux:icon.briefcase class="size-5 text-zinc-400" />{{ $profile->job_title }}</p>
                    @endif
                    @if ($profile->school)
                        <p class="flex items-center gap-3 px-5 py-3"><flux:icon.academic-cap class="size-5 text-zinc-400" />{{ $profile->school }}</p>
                    @endif
                    @if ($profile->relationship_goal)
                        <p class="flex items-center gap-3 px-5 py-3"><flux:icon.magnifying-glass class="size-5 text-zinc-400" />{{ $profile->relationship_goal->label() }}</p>
                    @endif
                    @foreach ($vitals as $vital)
                        <p class="flex items-center gap-3 px-5 py-3"><flux:icon :name="$vital['icon']" class="size-5 text-zinc-400" />{{ $vital['value'] }}</p>
                    @endforeach
                </div>
            </div>
        @elseif ($type === 'bio' && $profile->bio)
            <div class="hw-card px-6 py-6" data-reveal>
                <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-400">{{ __('About me') }}</p>
                <p class="mt-3 whitespace-pre-line text-lg leading-relaxed">{{ $profile->bio }}</p>
            </div>
        @elseif ($type === 'interests' && ($profile->interests || $reasons))
            <div class="hw-card space-y-4 px-6 py-6" data-reveal>
                @if ($reasons)
                    <div>
                        <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Why you might click') }}</p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($reasons as $reason)
                                <li class="flex items-start gap-2"><flux:icon.sparkles class="mt-0.5 size-4 shrink-0 text-brand-500" />{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($profile->interests)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($profile->interests as $interest)
                            @php($shared = $viewer && in_array($interest, $viewer->interests ?? [], true) && ! $isMe)
                            <span class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $shared ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800' }}">{{ $interest }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endforeach

    @if ($photos->isEmpty())
        <x-member-photo :user="$person" :small="false" class="aspect-[4/5] w-full rounded-3xl" />
    @endif
</div>
