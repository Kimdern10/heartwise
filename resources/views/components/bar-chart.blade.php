@props([
    'title',
    'data',          // array: 'Y-m-d' => count (one bar per day)
    'total' => null, // optional headline number
])

@php
    $values = array_values($data);
    $labels = array_keys($data);
    $max = max(1, ...$values ?: [1]);
    // A tidy y-axis top: 1, 2, 5, 10, 20, 50...
    $magnitude = 10 ** floor(log10($max));
    $top = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $max) ?? $max;
    $w = 600; $h = 160; $padL = 28; $padB = 22; $padT = 8;
    $plotW = $w - $padL; $plotH = $h - $padB - $padT;
    $n = max(1, count($values));
    $slot = $plotW / $n;
    $barW = max(2, $slot - 2); // 2px surface gap between bars
    $id = 'chart-'.md5($title);
@endphp

<div {{ $attributes->class('hw-card p-5') }} x-data="{ tip: null }">
    <div class="flex items-baseline justify-between gap-3">
        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $title }}</p>
        @if ($total !== null)
            <p class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $total }}</p>
        @endif
    </div>

    <div class="relative mt-3">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full overflow-visible" role="img" aria-labelledby="{{ $id }}-t">
            <title id="{{ $id }}-t">{{ $title }}: {{ array_sum($values) }} {{ __('in the last :n days', ['n' => $n]) }}</title>

            {{-- Recessive grid: baseline, middle and top --}}
            @foreach ([0, 0.5, 1] as $f)
                @php
                    $y = $padT + $plotH * (1 - $f);
                @endphp
                <line x1="{{ $padL }}" x2="{{ $w }}" y1="{{ $y }}" y2="{{ $y }}" class="stroke-zinc-200 dark:stroke-zinc-800" stroke-width="1" />
                <text x="{{ $padL - 6 }}" y="{{ $y + 3 }}" text-anchor="end" class="fill-zinc-400 text-[10px]">{{ rtrim(rtrim(number_format($top * $f, 1), '0'), '.') }}</text>
            @endforeach

            @foreach ($values as $i => $v)
                @php
                    $bh = $v > 0 ? max(2, $plotH * $v / $top) : 0;
                    $x = $padL + $i * $slot + 1;
                    $y = $padT + $plotH - $bh;
                    $r = min(4, $barW / 2, $bh);
                    $label = \Illuminate\Support\Carbon::parse($labels[$i])->format('M j');
                @endphp
                @if ($bh > 0)
                    {{-- Rounded top, square base anchored to the baseline --}}
                    <path d="M{{ $x }},{{ $y + $bh }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} V{{ $y + $bh }} Z"
                        class="fill-rose-500 dark:fill-rose-400" />
                @endif
                {{-- Hit target: the whole column, bigger than the bar --}}
                <rect x="{{ $padL + $i * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent"
                    x-on:mouseenter="tip = { x: {{ ($padL + ($i + 0.5) * $slot) / $w * 100 }}, label: @js($label), value: {{ $v }} }"
                    x-on:mouseleave="tip = null" />
            @endforeach

            {{-- First and last date on the x-axis --}}
            @if ($labels)
                <text x="{{ $padL }}" y="{{ $h - 4 }}" class="fill-zinc-400 text-[10px]">{{ \Illuminate\Support\Carbon::parse($labels[0])->format('M j') }}</text>
                <text x="{{ $w }}" y="{{ $h - 4 }}" text-anchor="end" class="fill-zinc-400 text-[10px]">{{ \Illuminate\Support\Carbon::parse(end($labels))->format('M j') }}</text>
            @endif
        </svg>

        <div x-show="tip" x-cloak class="pointer-events-none absolute -top-2 -translate-x-1/2 -translate-y-full rounded-lg bg-zinc-900 px-2.5 py-1.5 text-xs text-white shadow-lg dark:bg-white dark:text-zinc-900"
            x-bind:style="tip && `left: ${tip.x}%`">
            <span x-text="tip?.label"></span>: <strong x-text="tip?.value"></strong>
        </div>
    </div>

    <details class="mt-3 text-xs text-zinc-500">
        <summary class="cursor-pointer select-none">{{ __('Show as table') }}</summary>
        <table class="mt-2 w-full">
            <thead><tr class="text-start"><th class="text-start font-semibold">{{ __('Date') }}</th><th class="text-end font-semibold">{{ __('Count') }}</th></tr></thead>
            <tbody>
                @foreach (array_reverse($data, true) as $day => $v)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800"><td class="py-0.5">{{ \Illuminate\Support\Carbon::parse($day)->format('D, M j') }}</td><td class="text-end tabular-nums">{{ $v }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
