@extends('layouts.marketing', [
    'title' => ($useCase['title'] ?? 'Use case').' - SnapDraft',
    'description' => $useCase['description'] ?? null,
    'ogImage' => $useCase['image'] ?? '/images/marketing/og.png',
])

@section('content')
@php
    $faqs = $useCase['faqs'] ?? [];
    $schemaFaqs = [];
    foreach ($faqs as $item) {
        $schemaFaqs[] = [
            '@type' => 'Question',
            'name' => $item['q'] ?? '',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['a'] ?? '',
            ],
        ];
    }
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Use cases', 'item' => url('/use-cases')],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $useCase['title'] ?? 'Use case',
                'item' => url('/use-cases/'.($useCase['slug'] ?? '')),
            ],
        ],
    ];
@endphp
    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Use cases', 'href' => '/use-cases'],
            ['label' => $useCase['title'] ?? 'Use case'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">{{ $useCase['audience'] ?? 'Use case' }}</div>
            <h1>{{ $useCase['headline'] ?? $useCase['title'] }}</h1>
            <p>{{ $useCase['description'] ?? '' }}</p>
        </div>
    </div>

    @if (! empty($useCase['pain']) || ! empty($useCase['outcome']))
        <section class="sd-section" style="padding-top: 0">
            <div class="reveal">
                <div class="sd-prose" style="max-width: 720px; margin: 0 auto">
                    @if (! empty($useCase['pain']))
                        <h2>The bottleneck</h2>
                        <p>{{ $useCase['pain'] }}</p>
                    @endif
                    @if (! empty($useCase['outcome']))
                        <h2>What changes with SnapDraft</h2>
                        <p>{{ $useCase['outcome'] }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="sd-section" style="padding-top: 24px">
        <div class="sd-feature-rows" style="margin-top: 0">
            <div class="reveal" style="transition-delay: 60ms">
                <div class="sd-feature-row">
                    <div class="sd-feature-row-copy">
                        <div class="sd-sec-eyebrow">Workflow</div>
                        <h2>How the week usually runs</h2>
                        @if (! empty($useCase['workflow']))
                            <ul class="sd-feature-row-list">
                                @foreach ($useCase['workflow'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($useCase['evidence']))
                            <p style="margin-top: 20px; margin-bottom: 10px; font-weight: 600; color: var(--sd-text)">
                                Why it sticks
                            </p>
                            <ul class="sd-feature-row-list">
                                @foreach ($useCase['evidence'] as $point)
                                    <li>{{ $point }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="sd-feature-inline-links">
                            Related:
                            <a href="/features">Features</a> ·
                            <a href="/templates">Templates</a> ·
                            <a href="/pricing">Pricing</a>
                        </p>
                    </div>
                    @if (! empty($useCase['image']))
                        <div class="sd-feature-row-media">
                            <img
                                src="{{ $useCase['image'] }}"
                                alt="{{ $useCase['image_alt'] ?? $useCase['title'] }}"
                                width="960"
                                height="720"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="sd-section" style="padding-top: 24px">
        <div class="sd-sec-head reveal">
            <div class="sd-sec-eyebrow">Decision</div>
            <h2 class="sd-sec-title">Is this the right <em>fit?</em></h2>
            <p class="sd-sec-sub">
                Choose SnapDraft if you already plan in spreadsheets and need on-brand
                volume without reopening the design queue for every post.
            </p>
        </div>
        <div class="reveal" style="max-width: 720px; margin: 28px auto 0; text-align: center">
            <p class="sd-feature-inline-links">
                Next steps:
                <a href="/features">See features</a> ·
                <a href="/pricing">Compare plans</a> ·
                <a href="/templates">Browse templates</a> ·
                <a href="{{ route('register') }}">Create an account</a>
            </p>
        </div>
    </section>

    @if (count($faqs) > 0)
        <section class="sd-section" style="padding-top: 24px">
            <div class="sd-sec-head reveal">
                <div class="sd-sec-eyebrow">FAQ</div>
                <h2 class="sd-sec-title">Common <em>questions</em></h2>
            </div>
            <div class="sd-faq-wrap" data-faq style="margin-top: 28px">
                @foreach ($faqs as $idx => $item)
                    <div class="reveal" style="transition-delay: {{ $idx * 40 }}ms">
                        <div class="sd-faq-item{{ $idx === 0 ? ' open' : '' }}">
                            <button type="button" class="sd-faq-btn">
                                <span>{{ $item['q'] }}</span>
                                <span class="sd-faq-ico">+</span>
                            </button>
                            <div class="sd-faq-ans">{{ $item['a'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @if (count($schemaFaqs) > 0)
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $schemaFaqs,
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    @if (! empty($related))
        <section class="sd-section">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">Related</div>
                <h2 class="sd-sec-title">Other audiences</h2>
            </div>
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($related as $item)
                    <a href="/use-cases/{{ $item['slug'] }}" class="sd-blog-card sd-home-blog-card">
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
                            <span class="sd-blog-card-tag">{{ $item['audience'] ?? 'Audience' }}</span>
                            <h3>{{ $item['headline'] ?? $item['title'] }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sd-cta">
        <div class="reveal">
            <h2>Try it on <em>your next calendar.</em></h2>
            <p>Bring brand references and a spreadsheet. See a batch in minutes.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Get started
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/pricing" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">View pricing</a>
            </div>
        </div>
    </section>
@endsection
