<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-canvas min-h-screen text-zinc-900 antialiased dark:text-zinc-100">
        @php($isAdmin = auth()->user()->isStaff())
        @php($home = $isAdmin ? route('admin.dashboard') : route('dashboard'))

        @if ($isAdmin)
            {{-- Admins run the site; they don't use the dating features. --}}
            @php($nav = [])
            @php($counts = [
                'profiles' => \App\Models\Profile::where('status', 'pending')->count(),
                'photos' => \App\Models\Photo::where('status', 'pending')->count(),
                'verifications' => \App\Models\VerificationRequest::where('status', 'pending')->count(),
                'reports' => \App\Models\Report::where('status', 'open')->count(),
            ])
            @php($adminNav = [
                ['admin.dashboard', 'chart-bar', __('Overview'), null],
                ['admin.profiles', 'user-circle', __('Profiles to review'), $counts['profiles']],
                ['admin.photos', 'photo', __('Photos to review'), $counts['photos']],
                ['admin.verifications', 'check-badge', __('Selfie checks'), $counts['verifications']],
                ['admin.reports', 'flag', __('Reports'), $counts['reports']],
                ['admin.messages', 'shield-exclamation', __('Flagged messages'), null],
                ['admin.users', 'user-group', __('Members'), null],
                ...(auth()->user()->isAdmin() ? [['admin.security', 'globe-americas', __('Security / VPN'), null]] : []),
            ])
        @else
            @php($unread = auth()->user()->unreadMessagesCount())
            @php($pendingLikes = \App\Models\Like::query()->where('target_id', auth()->id())->where('liked', true)
                ->whereNotIn('user_id', \App\Models\Like::query()->where('user_id', auth()->id())->select('target_id'))->count())
            @php($nav = [
                ['discover', 'discover,people.show', 'sparkles', __('Discover')],
                ['activity', 'activity', 'heart', __('Likes')],
                ['matches', 'matches', 'user-group', __('Matches')],
                ['messages.index', 'messages.*', 'chat-bubble-left-right', __('Messages')],
                ['my-profile.edit', 'my-profile.*,preferences', 'user-circle', __('Profile')],
            ])
            @php($badges = ['messages.index' => $unread, 'activity' => $pendingLikes])
            {{-- Badge names the live checker (live-pulse) updates without a page refresh --}}
            @php($liveKeys = ['messages.index' => 'messages', 'activity' => 'likes'])
        @endif

        <flux:sidebar sticky collapsible="mobile" class="hw-sidebar glass border-e-0 overflow-y-auto overscroll-contain lg:m-3 lg:h-[calc(100svh-1.5rem)] lg:max-h-[calc(100svh-1.5rem)] lg:rounded-3xl lg:shadow-xl lg:shadow-brand-500/5">
            {{-- Phones: who you are, at the top of the drawer --}}
            <div class="flex items-center gap-3 border-b border-zinc-100 pb-4 lg:hidden dark:border-zinc-800" data-test="drawer-profile">
                @if (! $isAdmin && auth()->user()->profile)
                    <a href="{{ route('people.show', auth()->user()) }}" wire:navigate.hover class="flex min-w-0 flex-1 items-center gap-3">
                        <x-member-photo :user="auth()->user()" class="size-12 shrink-0 rounded-full" />
                        <span class="min-w-0">
                            <span class="block truncate font-bold">{{ auth()->user()->displayName() }}</span>
                            <span class="block text-xs text-zinc-500">{{ __('View my profile') }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <flux:avatar :name="auth()->user()->displayName()" :initials="auth()->user()->initials()" />
                        <span class="truncate font-bold">{{ auth()->user()->displayName() }}</span>
                    </div>
                @endif
                <flux:sidebar.collapse />
            </div>

            <flux:sidebar.header class="max-lg:hidden">
                <x-app-logo :sidebar="true" href="{{ $home }}" wire:navigate.hover />
                @unless ($isAdmin)
                    <div class="ms-auto hidden lg:block"><livewire:notification-bell key="bell-desktop" /></div>
                @endunless
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($isAdmin)
                <div class="mx-2 mb-1 flex items-center gap-2 rounded-xl bg-zinc-900 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white dark:bg-white dark:text-zinc-900">
                    <flux:icon.shield-check class="size-4" /> {{ auth()->user()->isAdmin() ? __('Admin panel') : __('Moderator panel') }}
                </div>
            @endif

            <flux:sidebar.nav>
                @if ($isAdmin)
                    <flux:sidebar.group class="grid gap-1">
                        @foreach ($adminNav as [$route, $icon, $label, $count])
                            <flux:sidebar.item :icon="$icon" :href="route($route)" :current="request()->routeIs($route)" :badge="$count ?: null" wire:navigate.hover>
                                {{ $label }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group class="grid gap-1">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate.hover>{{ __('Home') }}</flux:sidebar.item>
                        @foreach ($nav as [$route, $active, $icon, $label])
                            <flux:sidebar.item :icon="$icon" :href="route($route)" :current="request()->routeIs(...explode(',', $active))"
                                :badge="isset($liveKeys[$route]) ? (string) ($badges[$route] ?? 0) : null"
                                :badge:data-live-badge="$liveKeys[$route] ?? null"
                                :badge:class="($badges[$route] ?? 0) ? '' : 'hidden'" wire:navigate.hover>
                                {{ $label }}
                            </flux:sidebar.item>
                            @if ($route === 'discover')
                                <flux:sidebar.item icon="magnifying-glass" :href="route('search')" :current="request()->routeIs('search')" wire:navigate.hover>{{ __('Search') }}</flux:sidebar.item>
                            @endif
                        @endforeach
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('preferences')" :current="request()->routeIs('preferences')" wire:navigate.hover>{{ __('Preferences') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="star" :href="route('premium')" :current="request()->routeIs('premium')" wire:navigate.hover>
                            {{ auth()->user()->subscribed('premium') ? __('Premium ✓') : __('Get Premium') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Shown by resources/js/app.js when the browser offers "Add to home screen". --}}
            <button type="button" data-install-app hidden
                class="mx-2 mb-2 flex items-center justify-center gap-2 rounded-xl border border-zinc-300 px-3 py-2 text-sm font-semibold transition hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <flux:icon.device-phone-mobile class="size-4" /> {{ __('Install the app') }}
            </button>

            {{-- shrink-0: never squashed (the sidebar scrolls instead on short screens) --}}
            <div class="bg-brand-gradient relative shrink-0 overflow-hidden rounded-2xl px-4 py-3 text-white {{ $isAdmin ? 'hidden' : 'hidden lg:block' }}" data-test="date-safely">
                <div class="pointer-events-none absolute -end-6 -top-6 size-24 rounded-full bg-white/15 blur-xl"></div>
                <p class="flex items-center gap-2 text-sm font-semibold"><flux:icon.shield-check class="size-5 shrink-0" />{{ __('Date safely') }}</p>
                <p class="mt-1 text-xs leading-snug text-white/85">{{ __('Never send money or gift cards to someone you met online.') }}</p>
                <a href="{{ route('legal.safety') }}" target="_blank" class="relative mt-2 inline-block text-xs font-semibold underline underline-offset-2">{{ __('Safety tips') }} →</a>
            </div>

            <div class="hidden lg:block">
                <x-desktop-user-menu :name="auth()->user()->displayName()" />
            </div>

            {{-- Phones: settings and the one Log out button --}}
            <div class="space-y-1 border-t border-zinc-100 pt-3 lg:hidden dark:border-zinc-800">
                @unless ($isAdmin)
                    <a href="{{ route('legal.safety') }}" target="_blank" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <flux:icon.shield-check class="size-5" /> {{ __('Date safely: tips') }}
                    </a>
                @endunless
                <a href="{{ route('profile.edit') }}" wire:navigate.hover class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <flux:icon.cog-6-tooth class="size-5 text-zinc-500" /> {{ __('Settings') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10" data-test="drawer-logout">
                        <flux:icon.arrow-right-start-on-rectangle class="size-5" /> {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </flux:sidebar>

        <!-- Mobile top bar -->
        <flux:header class="glass sticky top-0 z-30 border-b-0 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <a href="{{ $home }}" wire:navigate.hover class="ms-2 flex items-center gap-2 font-bold">
                <span class="bg-brand-gradient flex size-7 items-center justify-center rounded-lg"><x-app-logo-icon class="size-4 text-white" /></span>
                {{ config('app.name') }}
            </a>

            <flux:spacer />

            @unless ($isAdmin)
                <livewire:notification-bell key="bell-mobile" />
            @endunless

        </flux:header>

        {{ $slot }}

        <!-- Mobile bottom tab bar -->
        <nav class="glass fixed inset-x-3 bottom-3 z-40 flex justify-around rounded-2xl px-2 py-1.5 shadow-lg shadow-black/10 lg:hidden {{ $isAdmin ? '!hidden' : '' }}">
            @foreach ($nav as [$route, $active, $icon, $label])
                @php($isActive = request()->routeIs(...explode(',', $active)))
                <a href="{{ route($route) }}" wire:navigate.hover
                    class="relative flex flex-1 flex-col items-center gap-0.5 rounded-xl py-1.5 text-[11px] font-medium transition {{ $isActive ? 'text-brand-600 dark:text-brand-400' : 'text-zinc-500' }}">
                    <flux:icon :name="$icon" :variant="$isActive ? 'solid' : 'outline'" class="size-6 transition {{ $isActive ? 'scale-110' : '' }}" />
                    {{ $label }}
                    @isset($liveKeys[$route])
                        <span data-live-badge="{{ $liveKeys[$route] }}" class="bg-brand-gradient absolute end-[22%] top-0.5 flex size-4 items-center justify-center rounded-full text-[10px] text-white {{ ($badges[$route] ?? 0) ? '' : 'hidden' }}">{{ $badges[$route] ?? 0 }}</span>
                    @endisset
                </a>
            @endforeach
        </nav>

        {{-- Toasts are shown with SweetAlert (resources/js/app.js). --}}
        @unless ($isAdmin)
            {{-- New messages, likes and matches pop up and update the badges without a refresh. --}}
            @persist('live-pulse')
                <livewire:live-pulse />
            @endpersist
        @endunless

        @fluxScripts
    </body>
</html>
