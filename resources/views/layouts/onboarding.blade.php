<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-canvas min-h-screen text-zinc-900 antialiased dark:text-zinc-100">
        <div class="mx-auto flex min-h-svh w-full max-w-lg flex-col px-5 pb-8 pt-6">
            <header class="mb-6 flex items-center justify-between">
                <span class="flex items-center gap-2 font-bold">
                    <span class="bg-brand-gradient flex size-8 items-center justify-center rounded-lg"><x-app-logo-icon class="size-4 text-white" /></span>
                    {{ config('app.name') }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Log out') }}</button>
                </form>
            </header>

            <main class="flex flex-1 flex-col">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
