@extends('layouts.marketing', [
    'title' => ($post['title'] ?? 'Article').' - SnapDraft Blog',
    'description' => $post['excerpt'] ?? null,
    'ogImage' => $post['cover'] ?? '/images/marketing/og.png',
    'ogType' => 'article',
    'articlePublished' => $post['date_iso'] ?? ($post['date'] ?? null),
    'articleModified' => $post['date_iso'] ?? ($post['date'] ?? null),
])

@section('content')
@php
    $blogSchemas = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'] ?? '',
            'description' => $post['excerpt'] ?? '',
            'image' => ! empty($post['cover']) ? url($post['cover']) : url('/images/marketing/og.png'),
            'datePublished' => $post['date'] ?? null,
            'dateModified' => $post['date'] ?? null,
            'author' => [
                '@type' => 'Organization',
                'name' => 'SnapDraft',
                'url' => url('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'SnapDraft',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => url('/SnapdraftLogoBlack.svg'),
                ],
            ],
            'mainEntityOfPage' => url('/blog/'.($post['slug'] ?? '')),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => url('/blog')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'] ?? 'Article', 'item' => url('/blog/'.($post['slug'] ?? ''))],
            ],
        ],
    ];
@endphp
@foreach ($blogSchemas as $schemaNode)
    <script type="application/ld+json">{!! json_encode($schemaNode, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Blog', 'href' => '/blog'],
            ['label' => $post['title'] ?? 'Article'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <h1>{{ $post['title'] }}</h1>
            <p>
                @if (! empty($post['date_formatted']))
                    {{ $post['date_formatted'] }} ·
                @endif
                {{ $post['reading_minutes'] }} min read
            </p>
        </div>
    </div>

    @if (! empty($post['cover']))
        <div class="reveal sd-article-cover">
            <img src="{{ $post['cover'] }}" alt="{{ $post['title'] }}" width="1200" height="630" decoding="async">
        </div>
    @endif

    <article class="sd-article">
        <a href="/blog" class="sd-article-back">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            All articles
        </a>
        <div class="sd-prose">
            {!! $post['html'] !!}
        </div>
        <p class="sd-article-more-links">
            Keep building the workflow:
            <a href="/features">Features</a> ·
            <a href="/use-cases">Use cases</a> ·
            <a href="/templates">Templates</a> ·
            <a href="/pricing">Pricing</a>
        </p>
    </article>

    @if (! empty($related))
        <section class="sd-section">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">Related</div>
                <h2 class="sd-sec-title">Keep reading</h2>
            </div>
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($related as $item)
                    <a href="/blog/{{ $item['slug'] }}" class="sd-blog-card sd-home-blog-card">
                        @if (! empty($item['cover']))
                            <div class="sd-blog-card-cover">
                                <img src="{{ $item['cover'] }}" alt="{{ $item['title'] }}" loading="lazy" decoding="async" width="640" height="360">
                            </div>
                        @endif
                        <div class="sd-blog-card-body">
                            <span class="sd-blog-card-tag">{{ $item['category'] ?? 'News' }}</span>
                            <h3>{{ $item['title'] }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sd-cta">
        <div class="reveal">
            <h2>Put it into <em>practice.</em></h2>
            <p>Turn your next content calendar into a batch of on-brand visuals.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Get started
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/features" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">See features</a>
            </div>
        </div>
    </section>
@endsection
