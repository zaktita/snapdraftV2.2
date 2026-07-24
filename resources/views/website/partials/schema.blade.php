{{-- JSON-LD: Organization + WebSite entity signals sitewide --}}
@php
    $orgId = url('/').'#organization';
    $websiteId = url('/').'#website';
    $sameAs = array_values(array_filter([
        trim((string) config('app.support_x_url')) ?: null,
    ], fn ($url) => is_string($url) && $url !== '' && filter_var($url, FILTER_VALIDATE_URL)));

    $defaultOrg = [
        '@type' => 'Organization',
        '@id' => $orgId,
        'name' => 'SnapDraft',
        'legalName' => 'SnapDraft',
        'url' => url('/'),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => url('/SnapdraftLogoBlack.svg'),
        ],
        'description' => 'SnapDraft is a web app that turns brand references and content spreadsheets into on-brand social visuals. Built for social media managers, freelancers, and agencies.',
        'email' => config('app.feedback_email'),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'email' => config('app.feedback_email'),
            'url' => url('/contact'),
        ],
        'knowsAbout' => [
            'Brand DNA',
            'CSV batch generation',
            'Canvas image editing',
            'Social media content production',
            'On-brand visual consistency',
        ],
    ];
    if (count($sameAs) > 0) {
        $defaultOrg['sameAs'] = $sameAs;
    }

    $defaultWebsite = [
        '@type' => 'WebSite',
        '@id' => $websiteId,
        'name' => 'SnapDraft',
        'url' => url('/'),
        'description' => 'On-brand visuals without waiting on designers.',
        'publisher' => ['@id' => $orgId],
        'about' => ['@id' => $orgId],
    ];
    $graphs = $schemaGraphs ?? [];
    $includeDefaults = $schemaIncludeDefaults ?? true;
    if ($includeDefaults) {
        array_unshift($graphs, $defaultWebsite);
        array_unshift($graphs, $defaultOrg);
    }
    $payload = [
        '@context' => 'https://schema.org',
        '@graph' => array_values($graphs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
