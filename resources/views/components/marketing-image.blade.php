@props([
    'name',          // e.g. "portrait-1"
    'alt' => '',
    'width' => 640,  // size asked from Unsplash
])

{{--
    Marketing pictures (models, never real members). Order of preference:
    1. your own file in public/images/marketing/ (e.g. portrait-1.jpg)
    2. the stock photo set in config/marketing.php
    3. the drawn illustration (portrait-1.svg), also used if the photo fails to load
--}}
@php
    $fallback = asset("images/marketing/{$name}.svg");
    $local = collect(['webp', 'jpg', 'jpeg', 'png'])
        ->map(fn ($ext) => "images/marketing/{$name}.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)));
    $remote = config("marketing.photos.{$name}");

    $src = match (true) {
        $local !== null => asset($local),
        filled($remote) => $remote.'?'.http_build_query(['auto' => 'format', 'fit' => 'crop', 'w' => $width, 'q' => 75]),
        default => $fallback,
    };
@endphp

<img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async" draggable="false" referrerpolicy="no-referrer"
    onerror="if (this.src !== '{{ $fallback }}') this.src = '{{ $fallback }}'"
    {{ $attributes->class('object-cover') }} />
