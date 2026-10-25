<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-canvas min-h-screen text-zinc-900 antialiased dark:text-zinc-100">
        @php($unread = auth()->user()->unreadMessagesCount())
        @php($nav = [
            ['dashboard', 'dashboard', 'home', __('Home')],
            ['discover', 'discover,people.show', 'sparkles', __('Discover')],
            ['matches', 'matches', 'heart', __('Matches')],
            ['messages.index', 'messages.*', 'chat-bubble-left-right', __('Messages')],
            ['my-profile.edit', 'my-profile.*', 'user-circle', __('Profile')],
        ])

        <flux:sidebar sticky collapsible="mobile" class="glass border-e-0 lg:m-3 lg:h-[calc(100svh-1.5rem)] lg:rounded-3xl lg:shadow-xl lg:shadow-brand-500/5">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group class="grid gap-1">
                    @foreach ($nav as [$route, $active, $icon, $label])
                        <flux:sidebar.item :icon="$icon" :href="route($route)" :current="request()->routeIs(...explode(',', $active))"
                            :badge="$route === 'messages.index' && $unread ? $unread : null" wire:navigate>
                            {{ $label }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>

                @if (auth()->user()->is_admin)
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" wire:navigate>
                            {{ __('Moderation') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="bg-brand-gradient relative hidden overflow-hidden rounded-2xl p-4 text-white lg:block">
                <div class="absolute -end-6 -top-6 size-24 rounded-full bg-white/15 blur-xl"></div>
                <flux:icon.shield-check class="size-6" />
                <p class="mt-2 text-sm font-semibold">{{ __('Date safely') }}</p>
                <p class="mt-1 text-xs text-white/80">{{ __('Never send money or gift cards to someone you met online.') }}</p>
                <a href="{{ route('legal.safety') }}" target="_blank" class="mt-3 inline-block text-xs font-semibold underline underline-offset-2">{{ __('Safety tips') }} →</a>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->displayName()" />
        </flux:sidebar>

        <!-- Mobile top bar -->
        <flux:header class="glass sticky top-0 z-30 border-b-0 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <a href="{{ route('dashboard') }}" wire:navigate class="ms-2 flex items-center gap-2 font-bold">
                <span class="bg-brand-gradient flex size-7 items-center justify-center rounded-lg"><x-app-logo-icon class="size-4 text-white" /></span>
                {{ config('app.name') }}
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->displayName()" :initials="auth()->user()->initials()" />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->displayName() }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <!-- Mobile bottom tab bar -->
        <nav class="glass fixed inset-x-3 bottom-3 z-40 flex justify-around rounded-2xl px-2 py-1.5 shadow-lg shadow-black/10 lg:hidden">
            @foreach ($nav as [$route, $active, $icon, $label])
                @php($isActive = request()->routeIs(...explode(',', $active)))
                <a href="{{ route($route) }}" wire:navigate
                    class="relative flex flex-1 flex-col items-center gap-0.5 rounded-xl py-1.5 text-[11px] font-medium transition {{ $isActive ? 'text-brand-600 dark:text-brand-400' : 'text-zinc-500' }}">
                    <flux:icon :name="$icon" :variant="$isActive ? 'solid' : 'outline'" class="size-6 transition {{ $isActive ? 'scale-110' : '' }}" />
                    {{ $label }}
                    @if ($route === 'messages.index' && $unread)
                        <span class="bg-brand-gradient absolute end-[22%] top-0.5 flex size-4 items-center justify-center rounded-full text-[10px] text-white">{{ $unread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
