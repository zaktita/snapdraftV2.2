@php
    $pageTitle = $title ?? config('app.name', 'SnapDraft');
    $pageDescription = $description ?? null;
    $ogImage = $ogImage ?? '/images/marketing/og.png';
    $ogType = $ogType ?? 'website';
    $canonical = $canonical ?? url()->current();
    $ogImageUrl = str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    @if ($pageDescription)
        <meta name="description" content="{{ $pageDescription }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($pageDescription)
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    @if ($pageDescription)
        <meta name="twitter:description" content="{{ $pageDescription }}">
    @endif
    <meta name="twitter:image" content="{{ $ogImageUrl }}">

    <script>
        (function () {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        })();
    </script>
    <style>
        html { background-color: oklch(1 0 0); }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700;800;900&family=Raleway:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/marketing.js'])
</head>
<body class="font-sans antialiased">
    <div class="sd-home">
        @include('website.partials.nav')

        <main>
            @yield('content')
        </main>

        @include('website.partials.footer')
    </div>

    @php
        $supportFabUrl = trim((string) config('app.support_x_url'));
        $supportFabValid = $supportFabUrl !== ''
            && filter_var($supportFabUrl, FILTER_VALIDATE_URL)
            && str_starts_with(strtolower($supportFabUrl), 'http');
    @endphp
    @if ($supportFabValid)
        <a
            href="{{ $supportFabUrl }}"
            class="sd-support-fab"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Support - message on X"
        >
            <span class="sd-support-fab__icon" aria-hidden="true">
                <i class="fa-solid fa-comments"></i>
            </span>
            <span class="sd-support-fab__label">Support</span>
        </a>
    @endif
</body>
</html>
