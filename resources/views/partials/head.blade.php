<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- ?v= makes browsers fetch the new icon instead of an old cached one; bump it when the icon changes. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="48x48">
<link rel="icon" href="{{ asset('favicon.svg') }}?v=2" type="image/svg+xml">
<link rel="icon" href="{{ asset('icons/icon-192.png') }}?v=2" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">

{{-- Installable app (PWA) --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#1b1718">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
