@extends('layouts.marketing', [
    'title' => ($term['title'] ?? ($term['term'] ?? 'Glossary')).' - SnapDraft',
    'description' => $term['description'] ?? ($term['definition'] ?? null),
])

@section('content')
@php
    $glossarySchemas = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Glossary', 'item' => url('/glossary')],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $term['term'] ?? $term['title'] ?? 'Term',
                    'item' => url('/glossary/'.($term['slug'] ?? '')),
                ],
            ],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTerm',
            'name' => $term['term'] ?? $term['title'] ?? '',
            'description' => $term['definition'] ?? $term['description'] ?? '',
            'inDefinedTermSet' => url('/glossary'),
            'url' => url('/glossary/'.($term['slug'] ?? '')),
        ],
    ];
@endphp
@foreach ($glossarySchemas as $schemaNode)
    <script type="application/ld+json">{!! json_encode($schemaNode, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Glossary', 'href' => '/glossary'],
            ['label' => $term['term'] ?? $term['title'] ?? 'Term'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Glossary</div>
            <h1>{{ $term['title'] ?? $term['term'] }}</h1>
            @if (! empty($term['description']))
                <p>{{ $term['description'] }}</p>
            @endif
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        <article class="sd-article" style="padding-top: 0">
            <a href="/glossary" class="sd-article-back">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                All terms
            </a>
            <div class="sd-prose">
                @if (! empty($term['definition']))
                    <h2>Definition</h2>
                    <p>{{ $term['definition'] }}</p>
                @endif

                @if (! empty($term['how_snapdraft']))
                    <h2>How SnapDraft uses it</h2>
                    <p>{{ $term['how_snapdraft'] }}</p>
                @endif

                @if (! empty($term['related']))
                    <h2>Related</h2>
                    <ul>
                        @foreach ($term['related'] as $link)
                            <li>
                                <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <p class="sd-article-more-links">
                Keep going:
                <a href="/features">Features</a> ·
                <a href="/templates">Templates</a> ·
                <a href="/use-cases">Use cases</a> ·
                <a href="/pricing">Pricing</a>
            </p>
        </article>
    </section>

    @if (! empty($others))
        <section class="sd-section">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">More terms</div>
                <h2 class="sd-sec-title">Continue in the glossary</h2>
            </div>
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($others as $item)
                    <a href="/glossary/{{ $item['slug'] }}" class="sd-blog-card sd-home-blog-card">
                        <div class="sd-blog-card-body">
                            <span class="sd-blog-card-tag">Term</span>
                            <h3>{{ $item['term'] ?? $item['title'] }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sd-cta">
        <div class="reveal">
            <h2>Put the term into <em>practice.</em></h2>
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
