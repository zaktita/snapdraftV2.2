@php
    $pageTitle = $title ?? config('app.name', 'SnapDraft');
    $pageDescription = $description ?? null;
    $ogImage = $ogImage ?? '/images/marketing/og.png';
    $ogType = $ogType ?? 'website';
    $canonical = $canonical ?? url()->current();
    $ogImageUrl = str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage);
    $robots = $robots ?? null;
    $articlePublished = $articlePublished ?? null;
    $articleModified = $articleModified ?? null;
    $preloadLcp = $preloadLcp ?? null;
    $posthogKey = config('posthog.api_key', '');
    $posthogHost = config('posthog.host') ?: 'https://us.i.posthog.com';
    $posthogDisabled = (bool) config('posthog.disabled', false);
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
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:site_name" content="SnapDraft">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($pageDescription)
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    @if ($articlePublished)
        <meta property="article:published_time" content="{{ $articlePublished }}">
    @endif
    @if ($articleModified)
        <meta property="article:modified_time" content="{{ $articleModified }}">
    @endif
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

    @if ($preloadLcp)
        <link rel="preload" as="image" href="{{ $preloadLcp }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Inter:wght@400;500;600;700;800;900&family=Raleway:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

    @vite(['resources/css/app.css', 'resources/js/marketing.js'])

    @include('website.partials.schema')
    @stack('ld-json')

    @if (! $posthogDisabled && $posthogKey !== '')
        <script>
            !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_session_id createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
            posthog.init(@json($posthogKey), {
                api_host: @json($posthogHost),
                person_profiles: 'identified_only',
                capture_pageview: true,
                capture_pageleave: true,
                persistence: 'localStorage+cookie'
            });
        </script>
    @endif
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
            data-ph-capture="marketing_support_click"
        >
            <span class="sd-support-fab__icon" aria-hidden="true">
                <i class="fa-solid fa-comments"></i>
            </span>
            <span class="sd-support-fab__label">Support</span>
        </a>
    @endif
</body>
</html>
