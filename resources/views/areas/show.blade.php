@php
    use App\Http\Controllers\DatingAreaController;

    $place = $city ? "{$city}, {$code}" : $stateName;
    $title = __('Online dating in :place', ['place' => $place]);
    $description = __('Meet real, verified singles in :place. Free to join, 18+ only, and every profile is reviewed by a real person.', ['place' => $place]);
@endphp

<x-layouts::public :title="$title" :description="$description">
    @push('head')
        <link rel="canonical" href="{{ url()->current() }}">
    @endpush

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -start-32 -top-32 size-[30rem] rounded-full bg-brand-400/15 blur-3xl animate-blob"></div>
        </div>
        <div class="mx-auto max-w-4xl px-4 pb-14 pt-16 text-center">
            <nav class="mb-6 text-sm text-zinc-500" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ route('areas.index') }}" class="hover:underline">{{ __('Dating in the US') }}</a>
                @if ($city)
                    › <a href="{{ route('areas.state', DatingAreaController::stateSlug($code)) }}" class="hover:underline">{{ $stateName }}</a>
                @endif
            </nav>
            <h1 class="font-display text-5xl leading-tight md:text-6xl">{!! __('Real dating in <span class="text-gradient">:place</span>', ['place' => e($place)]) !!}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg text-zinc-600 dark:text-zinc-300">
                {{ $city
                    ? __('Looking for someone special in :city? Meet genuine singles near you — every profile is checked by a real person, and members can verify their selfie and location.', ['city' => $city])
                    : __('Meet genuine singles across :state. Every profile is checked by a real person, and members can verify their selfie and location.', ['state' => $stateName]) }}
            </p>
            @if ($members)
                <p class="mt-4 font-semibold">{{ __(':n members in :state', ['n' => number_format($members), 'state' => $stateName]) }}</p>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="hw-btn hw-btn--primary !h-12 !px-7 !text-base">{{ __('Join free') }} →</a>
                <a href="{{ route('login') }}" class="hw-btn hw-btn--ghost !h-12 !px-7 !text-base">{{ __('Log in') }}</a>
            </div>
            <p class="mt-3 text-sm text-zinc-500">{{ __('18+ only. Free to join, like and chat with your matches.') }}</p>
        </div>
    </section>

    <section class="mx-auto grid max-w-5xl gap-5 px-4 pb-16 md:grid-cols-3">
        @foreach ([
            ['map-pin', __('People near you'), $city ? __('See singles in and around :city first — sorted by distance once you verify your location.', ['city' => $city]) : __('See people in :state first, or widen your search to the whole US.', ['state' => $stateName])],
            ['check-badge', __('Real, verified members'), __('Selfie and location checks, plus a real person reviewing every profile and photo.')],
            ['shield-check', __('Safety built in'), __('Report and block in one tap, scam warnings in chat, and date check-ins with a trusted friend.')],
        ] as [$icon, $heading, $text])
            <div class="hw-card p-6" data-reveal style="--i: {{ $loop->index }}">
                <flux:icon :name="$icon" class="size-7 text-brand-600" />
                <h2 class="mt-3 font-bold">{{ $heading }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
            </div>
        @endforeach
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-16">
        <h2 class="font-display text-3xl">{{ __('Dating safely in :place', ['place' => $place]) }}</h2>
        <ul class="mt-4 list-disc space-y-2 ps-6 text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Video chat before you meet, and meet somewhere public the first few times.') }}</li>
            <li>{{ __('Tell a friend your plans — Heartwise can do this for you with a date check-in.') }}</li>
            <li>{{ __('Never send money, gift cards or crypto to someone you met online.') }}</li>
        </ul>
        <a href="{{ route('legal.safety') }}" class="mt-4 inline-block font-semibold underline">{{ __('Read all our safety tips') }}</a>
    </section>

    @if ($cities->isNotEmpty())
        <section class="mx-auto max-w-4xl px-4 pb-20">
            <h2 class="font-display text-2xl">{{ $city ? __('More cities in :state', ['state' => $stateName]) : __('Popular cities in :state', ['state' => $stateName]) }}</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($cities as $slug => [$name])
                    <a href="{{ route('areas.city', [DatingAreaController::stateSlug($code), $slug]) }}" class="rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:border-zinc-900 dark:border-zinc-700 dark:bg-zinc-900">{{ __('Dating in :city', ['city' => $name]) }}</a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts::public>
