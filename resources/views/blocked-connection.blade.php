<x-layouts::public :title="__('Connection not allowed')">
    <section class="mx-auto flex max-w-lg flex-col items-center px-4 py-24 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
            <flux:icon.shield-exclamation class="size-8" />
        </div>

        @if (($reason ?? null) === 'vpn')
            <h1 class="font-display mt-6 text-4xl">{{ __('Please turn off your VPN') }}</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ __('To keep fake accounts out, :app can’t be used through a VPN, proxy or data-centre connection. Switch it off and reload this page.', ['app' => config('app.name')]) }}</p>
        @elseif (($reason ?? null) === 'country')
            <h1 class="font-display mt-6 text-4xl">{{ __('Not available in your location') }}</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ __(':app is currently only available to people living in the United States.', ['app' => config('app.name')]) }}</p>
        @else
            <h1 class="font-display mt-6 text-4xl">{{ __('Access blocked') }}</h1>
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ __('This connection has been blocked by our safety team.') }}</p>
        @endif

        <p class="mt-6 text-sm text-zinc-500">{{ __('Think this is a mistake? Email :email.', ['email' => config('dating.contact_email')]) }}</p>
        <a href="{{ url()->current() === route('connection.blocked') ? route('home') : url()->current() }}" class="hw-btn hw-btn--primary mt-6">{{ __('Try again') }}</a>
    </section>
</x-layouts::public>
