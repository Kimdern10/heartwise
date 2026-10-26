<x-layouts::public :title="__('Real dating for real people')" :description="__('Meet verified singles across the United States. 18+ only, every profile reviewed by a real person.')">
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -start-32 -top-32 size-[32rem] rounded-full bg-brand-400/15 blur-3xl animate-blob"></div>
            <div class="absolute -end-24 top-20 size-[28rem] rounded-full bg-plum-500/10 blur-3xl animate-blob [animation-delay:-6s]"></div>
            <div class="absolute bottom-0 start-1/3 size-80 rounded-full bg-amber-300/15 blur-3xl animate-blob [animation-delay:-12s]"></div>
        </div>

        <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 md:grid-cols-[1.1fr_1fr] md:pt-24">
            <div class="space-y-7">
                <span class="glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold text-brand-700 animate-fade-up dark:text-brand-400">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-500 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-brand-500"></span></span>
                    {{ __('Every profile reviewed by a real person') }}
                </span>

                <h1 class="font-display text-6xl leading-[1.02] animate-fade-up [animation-delay:80ms] md:text-6xl lg:text-7xl">
                    {{ __('Real dating') }}<br>
                    {{ __('for') }} <span class="text-gradient">{{ __('real people.') }}</span>
                </h1>

                <p class="max-w-lg text-lg text-zinc-600 animate-fade-up [animation-delay:160ms] dark:text-zinc-300">
                    {{ __('Meet genuine singles across the United States. Selfie and location verified. No bots, no fake profiles — just people looking for something real.') }}
                </p>

                <div class="flex flex-wrap items-center gap-3 animate-fade-up [animation-delay:240ms]">
                    @auth
                        <a href="{{ route('discover') }}" class="hw-btn hw-btn--primary !h-12 !px-7 !text-base">{{ __('Start discovering') }} →</a>
                    @else
                        <a href="{{ route('register') }}" class="hw-btn hw-btn--primary !h-12 !px-7 !text-base">{{ __('Join free') }} →</a>
                        <a href="{{ route('login') }}" class="hw-btn hw-btn--ghost !h-12 !px-7 !text-base">{{ __('Log in') }}</a>
                    @endauth
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500 animate-fade-up [animation-delay:320ms]">
                    <span class="flex items-center gap-1.5"><flux:icon.check-badge variant="solid" class="size-5 text-sky-500" /> {{ __('Selfie verified') }}</span>
                    <span class="flex items-center gap-1.5"><flux:icon.map-pin variant="solid" class="size-5 text-emerald-500" /> {{ __('Location verified') }}</span>
                    <span class="flex items-center gap-1.5"><flux:icon.shield-check variant="solid" class="size-5 text-brand-500" /> {{ __('18+ only') }}</span>
                </div>
            </div>

            {{-- Floating card stack --}}
            <div class="relative mx-auto h-[30rem] w-full max-w-sm animate-pop [animation-delay:200ms]">
                <div class="absolute inset-x-6 top-10 h-[24rem] rotate-[-7deg] rounded-[2rem] bg-gradient-to-br from-sky-300 to-violet-400 shadow-2xl animate-float-slow [--r:-7deg]"></div>
                <div class="absolute inset-x-0 top-0 h-[26rem] rotate-[3deg] overflow-hidden rounded-[2rem] shadow-2xl shadow-brand-500/30 animate-float [--r:3deg]">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-200 via-rose-300 to-brand-500"></div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent"></div>
                    <div class="absolute start-5 top-5 -rotate-12 rounded-xl border-[3px] border-emerald-300 px-3 py-0.5 text-2xl font-extrabold text-emerald-300">LIKE</div>
                    <div class="absolute inset-x-0 bottom-0 p-6 text-white">
                        <p class="flex items-center gap-2 text-3xl font-extrabold">{{ __('Your match') }} <flux:icon.check-badge variant="solid" class="size-6 text-sky-300" /></p>
                        <p class="mt-1 flex items-center gap-1 text-sm text-white/85"><flux:icon.map-pin class="size-4" /> {{ __('Somewhere near you') }}</p>
                        <span class="mt-3 inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur">{{ __('Long-term relationship') }}</span>
                    </div>
                </div>

                <div class="glass absolute -start-6 bottom-6 flex items-center gap-3 rounded-2xl p-3 pe-5 shadow-xl animate-float [animation-delay:-2s]">
                    <span class="bg-brand-gradient flex size-10 items-center justify-center rounded-full text-white"><flux:icon.heart variant="solid" class="size-5" /></span>
                    <div>
                        <p class="text-sm font-bold">{{ __('It’s a match!') }}</p>
                        <p class="text-xs text-zinc-500">{{ __('Say hello 👋') }}</p>
                    </div>
                </div>

                <div class="glass absolute -end-4 top-16 flex items-center gap-2 rounded-2xl px-3 py-2 shadow-xl animate-float-slow [animation-delay:-4s]">
                    <flux:icon.shield-check variant="solid" class="size-5 text-emerald-500" />
                    <span class="text-xs font-semibold">{{ __('Profile reviewed') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-6xl px-4 py-20">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="text-sm font-bold uppercase tracking-widest text-brand-600">{{ __('How it works') }}</p>
            <h2 class="mt-2 font-display text-5xl">{{ __('Three steps to your first date') }}</h2>
        </div>
        <ol class="mt-14 grid gap-6 md:grid-cols-3">
            @foreach ([
                ['user-circle', __('Create your profile'), __('Add real photos and a few words about yourself. A moderator reviews it to keep fakes out.')],
                ['sparkles', __('Swipe & like'), __('Filter by age, state and what they want. Swipe right to like, left to pass.')],
                ['chat-bubble-left-right', __('Match & chat'), __('When the like is mutual, it’s a match — say hello and take it from there.')],
            ] as [$icon, $title, $text])
                <li class="hw-card hw-card--hover relative p-7" data-reveal style="--i: {{ $loop->index }}">
                    <span class="absolute end-6 top-5 text-6xl font-extrabold text-zinc-100 dark:text-zinc-800">{{ $loop->iteration }}</span>
                    <span class="bg-brand-gradient relative flex size-12 items-center justify-center rounded-2xl text-white shadow-lg shadow-brand-500/30">
                        <flux:icon :name="$icon" class="size-6" />
                    </span>
                    <h3 class="relative mt-5 text-lg font-bold">{{ $title }}</h3>
                    <p class="relative mt-2 text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Trust features --}}
    <section class="relative overflow-hidden py-20">
        <div class="absolute inset-0 -z-10 bg-zinc-950"></div>
        <div class="absolute -end-40 -top-40 -z-10 size-[30rem] rounded-full bg-brand-500/30 blur-3xl"></div>
        <div class="mx-auto max-w-6xl px-4 text-white">
            <div class="max-w-2xl" data-reveal>
                <p class="text-sm font-bold uppercase tracking-widest text-brand-400">{{ __('Safety first') }}</p>
                <h2 class="mt-2 font-display text-5xl">{{ __('Built to keep scammers out') }}</h2>
                <p class="mt-3 text-lg text-white/70">{{ __('Romance scams cost Americans over a billion dollars a year. Here’s how we fight back.') }}</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['check-badge', __('Selfie verification'), __('Members prove they match their photos with a live pose. Selfies are deleted right after review.')],
                    ['map-pin', __('Location verification'), __('A quick device check confirms members really live in the state they say.')],
                    ['envelope', __('Email code sign-up'), __('Every account confirms a real email with a 6-digit code.')],
                    ['shield-exclamation', __('Scam warnings in chat'), __('Messages mentioning gift cards, crypto or wire transfers trigger a warning and moderator review.')],
                    ['flag', __('Report & block'), __('One tap to report or block. Every report is reviewed by our team.')],
                    ['lock-closed', __('Your data, your call'), __('Hide your profile, download your data, or delete everything any time.')],
                ] as [$icon, $title, $text])
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur transition hover:-translate-y-1 hover:bg-white/10" data-reveal style="--i: {{ $loop->index % 3 }}">
                        <flux:icon :name="$icon" class="size-7 text-brand-400" />
                        <h3 class="mt-4 font-bold">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-white/65">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mx-auto max-w-3xl px-4 py-20">
        <h2 class="text-center font-display text-5xl" data-reveal>{{ __('Questions?') }}</h2>
        <div class="mt-10 space-y-3">
            @foreach ([
                [__('Is it free?'), __('Yes. Creating a profile, liking people and chatting with your matches is free.')],
                [__('Who can join?'), __('Adults 18 and over living in the United States. We check your date of birth and review every profile.')],
                [__('What does “verified” mean?'), __('The blue badge means a member’s selfie matched their photos. The green pin means their device was in their state when they checked. It does not mean a background check.')],
                [__('How do you protect my privacy?'), __('We remove location data from your photos, store only a rounded position for location checks, and never sell your data.')],
            ] as [$q, $a])
                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="hw-card overflow-hidden" data-reveal style="--i: {{ $loop->index }}">
                    <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between gap-4 p-5 text-start font-bold">
                        {{ $q }}
                        <flux:icon.chevron-down class="size-5 shrink-0 transition duration-300" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <div x-show="open" x-collapse>
                        <p class="px-5 pb-5 text-zinc-600 dark:text-zinc-400">{{ $a }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto mb-20 max-w-6xl px-4" data-reveal>
        <div class="bg-brand-gradient relative overflow-hidden rounded-[2.5rem] px-8 py-14 text-center text-white shadow-2xl shadow-brand-500/30">
            <div class="absolute -start-10 -top-10 size-60 rounded-full bg-white/10 blur-2xl animate-blob"></div>
            <h2 class="relative font-display text-5xl">{{ __('Your person might be one swipe away.') }}</h2>
            <p class="relative mt-3 text-lg text-white/85">{{ __('Join free in under two minutes.') }}</p>
            <a href="{{ auth()->check() ? route('discover') : route('register') }}" class="relative mt-7 inline-flex h-12 items-center rounded-full bg-white px-8 font-bold text-brand-600 shadow-xl transition hover:scale-105">
                {{ auth()->check() ? __('Start discovering') : __('Create my profile') }} →
            </a>
        </div>
    </section>
</x-layouts::public>
