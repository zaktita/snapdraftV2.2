@extends('layouts.marketing', [
    'title' => ($template['title'] ?? 'Template').' - SnapDraft',
    'description' => $template['excerpt'] ?? ($template['description'] ?? null),
    'ogImage' => $template['image'] ?? '/images/marketing/og.png',
])

@section('content')
@php
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Templates', 'item' => url('/templates')],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $template['title'] ?? 'Template',
                'item' => url('/templates/'.($template['slug'] ?? '')),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>

    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Templates', 'href' => '/templates'],
            ['label' => $template['title'] ?? 'Template'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Template</div>
            <h1>{{ $template['title'] }}</h1>
            @if (! empty($template['excerpt']))
                <p>{{ $template['excerpt'] }}</p>
            @endif
            @if (! empty($template['tags']))
                <p class="sd-feature-inline-links" style="margin-top: 16px">
                    @foreach ($template['tags'] as $tag)
                        <span class="sd-blog-card-tag" style="display: inline-block; margin: 0 6px 6px 0">{{ $tag }}</span>
                    @endforeach
                </p>
            @endif
        </div>
    </div>

    @if (! empty($template['image']))
        <div class="reveal sd-article-cover">
            <img
                src="{{ $template['image'] }}"
                alt="{{ $template['image_alt'] ?? $template['title'] }}"
                width="1200"
                height="630"
                decoding="async"
            >
        </div>
    @endif

    <section class="sd-section" style="padding-top: 0">
        <div class="sd-feature-rows" style="margin-top: 0">
            <div class="reveal" style="transition-delay: 60ms">
                <div class="sd-feature-row">
                    <div class="sd-feature-row-copy">
                        <div class="sd-sec-eyebrow">Overview</div>
                        <h2>{{ $template['title'] }}</h2>
                        @if (! empty($template['description']))
                            <p>{{ $template['description'] }}</p>
                        @endif
                        @if (! empty($template['formats']))
                            <p style="margin-bottom: 8px; font-weight: 600; color: var(--sd-text)">Formats</p>
                            <ul class="sd-feature-row-list">
                                @foreach ($template['formats'] as $format)
                                    <li>{{ $format }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($template['related_use_cases']))
                            <p class="sd-feature-inline-links">
                                Related:
                                @foreach ($template['related_use_cases'] as $i => $link)
                                    @if ($i > 0) · @endif
                                    <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                                @endforeach
                            </p>
                        @endif
                    </div>
                    @if (! empty($template['steps']))
                        <div class="sd-feature-row-copy">
                            <div class="sd-sec-eyebrow">Steps</div>
                            <h2>How to run it</h2>
                            <ul class="sd-feature-row-list">
                                @foreach ($template['steps'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ul>
                            <p class="sd-feature-inline-links">
                                Next:
                                <a href="/features">Features</a> ·
                                <a href="/pricing">Pricing</a> ·
                                <a href="{{ route('register') }}">Register</a>
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if (! empty($related))
        <section class="sd-section">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">Related</div>
                <h2 class="sd-sec-title">More templates</h2>
            </div>
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($related as $item)
                    <a href="/templates/{{ $item['slug'] }}" class="sd-blog-card sd-home-blog-card">
                        @if (! empty($item['image']))
                            <div class="sd-blog-card-cover">
                                <img
                                    src="{{ $item['image'] }}"
                                    alt="{{ $item['image_alt'] ?? $item['title'] }}"
                                    loading="lazy"
                                    decoding="async"
                                    width="640"
                                    height="360"
                                >
                            </div>
                        @endif
                        <div class="sd-blog-card-body">
                            @if (! empty($item['tags'][0]))
                                <span class="sd-blog-card-tag">{{ $item['tags'][0] }}</span>
                            @endif
                            <h3>{{ $item['title'] }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sd-cta">
        <div class="reveal">
            <h2>Run this on <em>your next calendar.</em></h2>
            <p>Bring brand references and a spreadsheet. Follow the steps above.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Get started
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/templates" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">All templates</a>
            </div>
        </div>
    </section>
@endsection
