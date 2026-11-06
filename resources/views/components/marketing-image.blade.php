@props([
    'name',          // e.g. "portrait-1"
    'alt' => '',
])

{{--
    Marketing pictures. To use your own photo, put a file with the same name in
    public/images/marketing/ (e.g. portrait-1.jpg). Only use photos you have the
    rights to (your own, or properly licensed stock — never real members).
    Without one, a drawn illustration (portrait-1.svg) is shown.
--}}
@php
    $src = collect(['webp', 'jpg', 'jpeg', 'png'])
        ->map(fn ($ext) => "images/marketing/{$name}.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)))
        ?? "images/marketing/{$name}.svg";
@endphp

<img src="{{ asset($src) }}" alt="{{ $alt }}" loading="lazy" draggable="false" {{ $attributes->class('object-cover') }} />
