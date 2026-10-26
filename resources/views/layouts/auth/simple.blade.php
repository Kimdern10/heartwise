<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-canvas min-h-screen antialiased">
        <div class="grid min-h-svh lg:grid-cols-2">
            {{-- Brand panel --}}
            <div class="bg-brand-gradient relative hidden overflow-hidden p-12 text-white lg:flex lg:flex-col">
                <div class="absolute -start-20 -top-20 size-96 rounded-full bg-white/10 blur-3xl animate-blob"></div>
                <div class="absolute -bottom-32 -end-10 size-[28rem] rounded-full bg-fuchsia-300/25 blur-3xl animate-blob [animation-delay:-7s]"></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-2 text-xl font-extrabold">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur"><x-app-logo-icon class="size-6 text-white" /></span>
                    {{ config('app.name') }}
                </a>

                <div class="relative my-auto max-w-md">
                    <div class="relative mb-10 h-56">
                        <div class="glass absolute start-0 top-6 w-44 rotate-[-8deg] rounded-3xl p-3 !text-zinc-900 shadow-2xl animate-float [--r:-8deg]">
                            <div class="h-32 rounded-2xl bg-gradient-to-br from-amber-200 to-rose-300"></div>
                            <p class="mt-2 text-sm font-bold">Real profiles</p>
                            <p class="text-xs text-zinc-600">Reviewed by people</p>
                        </div>
                        <div class="glass absolute start-40 top-0 w-44 rotate-[6deg] rounded-3xl p-3 !text-zinc-900 shadow-2xl animate-float-slow [--r:6deg]">
                            <div class="h-32 rounded-2xl bg-gradient-to-br from-sky-200 to-violet-300"></div>
                            <p class="mt-2 flex items-center gap-1 text-sm font-bold">Verified <flux:icon.check-badge variant="solid" class="size-4 text-sky-500" /></p>
                            <p class="text-xs text-zinc-600">Selfie + location</p>
                        </div>
                        <div class="absolute end-6 top-36 flex size-14 items-center justify-center rounded-full bg-white text-brand-600 shadow-2xl animate-pop [animation-delay:400ms]">
                            <flux:icon.heart variant="solid" class="size-7" />
                        </div>
                    </div>

                    <h2 class="font-display text-5xl leading-tight">{{ __('Find someone real.') }}</h2>
                    <p class="mt-3 text-lg text-white/85">{{ __('A dating community for US adults 18+, where every profile is checked by a real person.') }}</p>
                </div>

                <p class="relative text-sm text-white/70">{{ __('Never send money to someone you met online.') }}</p>
            </div>

            {{-- Form --}}
            <div class="flex flex-col items-center justify-center p-6 md:p-10">
                <div class="w-full max-w-sm animate-fade-up">
                    <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-2 text-lg font-extrabold lg:hidden" wire:navigate>
                        <span class="bg-brand-gradient flex size-9 items-center justify-center rounded-xl"><x-app-logo-icon class="size-5 text-white" /></span>
                        {{ config('app.name') }}
                    </a>
                    <div class="hw-card p-6 sm:p-8">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
