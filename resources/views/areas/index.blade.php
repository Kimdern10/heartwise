@php use App\Http\Controllers\DatingAreaController; @endphp

<x-layouts::public :title="__('Dating across the United States')" :description="__('Find real, verified singles in your state and city. Free to join, 18+ only.')">
    <section class="mx-auto max-w-5xl px-4 py-16">
        <h1 class="font-display text-center text-5xl">{{ __('Dating across the US') }}</h1>
        <p class="mx-auto mt-4 max-w-2xl text-center text-lg text-zinc-600 dark:text-zinc-300">{{ __('Pick your state to see how Heartwise works where you live.') }}</p>

        <div class="mt-10 grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach (config('dating.us_states') as $code => $name)
                <a href="{{ route('areas.state', DatingAreaController::stateSlug($code)) }}" class="rounded-2xl border border-zinc-200 bg-white px-4 py-3 font-medium transition hover:border-zinc-900 dark:border-zinc-800 dark:bg-zinc-900">
                    {{ __('Dating in :state', ['state' => $name]) }}
                </a>
            @endforeach
        </div>

        <h2 class="font-display mt-14 text-3xl">{{ __('Popular cities') }}</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (config('us_cities') as $slug => [$city, $code])
                <a href="{{ route('areas.city', [DatingAreaController::stateSlug($code), $slug]) }}" class="rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm hover:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900">{{ $city }}, {{ $code }}</a>
            @endforeach
        </div>
    </section>
</x-layouts::public>
