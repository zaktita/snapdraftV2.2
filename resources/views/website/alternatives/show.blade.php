@extends('layouts.marketing', [
    'title' => ($alternative['title'] ?? (($alternative['name'] ?? 'Alternative').' alternatives')).' - SnapDraft',
    'description' => $alternative['description'] ?? ($alternative['summary'] ?? null),
    'ogImage' => $alternative['compare_image'] ?? $alternative['image'] ?? '/images/marketing/og.png',
])

@section('content')
@php
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Alternatives', 'item' => url('/alternatives')],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $alternative['name'] ?? 'Alternative',
                'item' => url('/alternatives/'.($alternative['slug'] ?? '')),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>

    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Alternatives', 'href' => '/alternatives'],
            ['label' => $alternative['name'] ?? 'Alternative'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">{{ $alternative['name'] ?? 'Alternative' }}</div>
            <h1>{{ $alternative['headline'] ?? $alternative['title'] }}</h1>
            <p>{{ $alternative['description'] ?? $alternative['summary'] ?? '' }}</p>
            <div class="sd-cta-row" style="justify-content: center; margin-top: 24px">
                <a href="/compare/{{ $alternative['slug'] }}-vs-snapdraft" class="sd-btn-hero-ghost">
                    {{ $alternative['name'] ?? 'Tool' }} vs SnapDraft
                </a>
            </div>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @include('website.partials.compare-vs', ['alternative' => $alternative])

        <div class="reveal" style="margin-top: 40px">
            <div class="sd-prose" style="max-width: 720px; margin: 0 auto 40px">
                <p>{{ $alternative['summary'] ?? '' }}</p>
                @if (! empty($alternative['best_for_competitor']) || ! empty($alternative['best_for_snapdraft']))
                    <h2>Best fit</h2>
                    <ul>
                        @if (! empty($alternative['best_for_competitor']))
                            <li>
                                <strong>{{ $alternative['name'] ?? 'Competitor' }}:</strong>
                                {{ $alternative['best_for_competitor'] }}
                            </li>
                        @endif
                        @if (! empty($alternative['best_for_snapdraft']))
                            <li>
                                <strong>SnapDraft:</strong>
                                {{ $alternative['best_for_snapdraft'] }}
                            </li>
                        @endif
                    </ul>
                @endif
            </div>
        </div>

        @if (! empty($alternative['matrix']))
            <div class="reveal" style="overflow-x: auto">
                <table class="sd-compare-table">
                    <thead>
                        <tr>
                            <th scope="col">Capability</th>
                            <th scope="col">{{ $alternative['name'] ?? 'Competitor' }}</th>
                            <th scope="col">SnapDraft</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($alternative['matrix'] as $criterion => $cells)
                            <tr>
                                <th scope="row">{{ $criterion }}</th>
                                <td>{{ $cells['competitor'] ?? 'n/a' }}</td>
                                <td>{{ $cells['snapdraft'] ?? 'n/a' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="sd-feature-inline-links reveal" style="text-align: center; margin-top: 24px">
                Full side-by-side:
                <a href="/compare/{{ $alternative['slug'] }}-vs-snapdraft">
                    {{ $alternative['name'] ?? 'Tool' }} vs SnapDraft
                </a>
                ·
                <a href="/features">Features</a>
                ·
                <a href="/pricing">Pricing</a>
            </p>
        @endif
    </section>

    @if (! empty($related))
        <section class="sd-section">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">Related</div>
                <h2 class="sd-sec-title">More comparisons</h2>
            </div>
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($related as $item)
                    <a href="/alternatives/{{ $item['slug'] }}" class="sd-blog-card sd-home-blog-card">
                        @if (! empty($item['image']))
                            <div class="sd-blog-card-cover sd-alt-card-cover">
                                <img
                                    src="{{ $item['image'] }}"
                                    alt="{{ $item['image_alt'] ?? (($item['name'] ?? 'Tool').' comparison mark') }}"
                                    loading="lazy"
                                    decoding="async"
                                    width="640"
                                    height="360"
                                >
                            </div>
                        @endif
                        <div class="sd-blog-card-body">
                            <span class="sd-blog-card-tag">{{ $item['name'] ?? 'Alternative' }}</span>
                            <h3>{{ $item['title'] ?? $item['name'] }}</h3>
                            @if (! empty($item['summary']))
                                <p>{{ \Illuminate\Support\Str::limit($item['summary'], 120) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sd-cta">
        <div class="reveal">
            <h2>Need calendar batches, not another <em>editor?</em></h2>
            <p>Try Brand DNA + CSV generation on your next week of posts.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Generate your next batch
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/compare/{{ $alternative['slug'] }}-vs-snapdraft" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">
                    Full comparison
                </a>
            </div>
        </div>
    </section>
@endsection
