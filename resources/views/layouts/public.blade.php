<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @isset($description)
            <meta name="description" content="{{ $description }}" />
        @endisset
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="sticky top-0 z-40" x-data="{ scrolled: false }" x-on:scroll.window="scrolled = window.scrollY > 10">
            <div class="mx-auto mt-3 flex max-w-6xl items-center justify-between gap-4 rounded-full px-4 py-3 transition-all duration-300"
                x-bind:class="scrolled ? 'glass mx-3 shadow-lg shadow-black/5 md:mx-auto' : ''">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-extrabold">
                    <span class="bg-brand-gradient flex size-9 items-center justify-center rounded-xl shadow-md shadow-brand-500/30"><x-app-logo-icon class="size-5 text-white" /></span>
                    {{ config('app.name') }}
                </a>
                <nav class="flex items-center gap-2">
                    @auth
                        <flux:button size="sm" variant="primary" :href="route('dashboard')">{{ __('Open app') }}</flux:button>
                    @else
                        <flux:button size="sm" variant="ghost" :href="route('login')">{{ __('Log in') }}</flux:button>
                        @if (Route::has('register'))
                            <flux:button size="sm" variant="primary" :href="route('register')">{{ __('Join free') }}</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 py-8 text-sm text-zinc-500 dark:border-zinc-800">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4">
                <p>© {{ now()->year }} {{ config('app.name') }}. {{ __('For adults 18+ in the United States.') }}</p>
                <nav class="flex flex-wrap gap-4">
                    <a href="{{ route('legal.safety') }}" class="hover:underline">{{ __('Safety tips') }}</a>
                    <a href="{{ route('legal.guidelines') }}" class="hover:underline">{{ __('Community guidelines') }}</a>
                    <a href="{{ route('legal.terms') }}" class="hover:underline">{{ __('Terms') }}</a>
                    <a href="{{ route('legal.privacy') }}" class="hover:underline">{{ __('Privacy') }}</a>
                    <a href="{{ route('legal.cookies') }}" class="hover:underline">{{ __('Cookies') }}</a>
                </nav>
            </div>
        </footer>

        <div x-data="{ show: false }" x-init="try { show = ! localStorage.getItem('cookie-notice') } catch (e) { show = true }" x-show="show" x-cloak
            class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-xl rounded-xl border border-zinc-200 bg-white p-4 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
            <p>
                {{ __('We only use essential cookies to keep you signed in and secure. No advertising or tracking cookies.') }}
                <a href="{{ route('legal.cookies') }}" class="underline">{{ __('Learn more') }}</a>
            </p>
            <div class="mt-3 text-end">
                <flux:button size="sm" variant="primary" x-on:click="show = false; try { localStorage.setItem('cookie-notice', '1') } catch (e) {}">{{ __('OK') }}</flux:button>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
