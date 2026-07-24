@extends('layouts.marketing', [
    'title' => ($alternative['name'] ?? 'Tool').' vs SnapDraft - SnapDraft',
    'description' => $alternative['description'] ?? ('Compare '.($alternative['name'] ?? 'this tool').' and SnapDraft for on-brand social batches from a content calendar.'),
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
            [
                '@type' => 'ListItem',
                'position' => 4,
                'name' => ($alternative['name'] ?? 'Tool').' vs SnapDraft',
                'item' => url('/compare/'.($alternative['slug'] ?? '').'-vs-snapdraft'),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>

    @include('website.partials.breadcrumbs', [
        'breadcrumbs' => [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Alternatives', 'href' => '/alternatives'],
            ['label' => $alternative['name'] ?? 'Alternative', 'href' => '/alternatives/'.($alternative['slug'] ?? '')],
            ['label' => ($alternative['name'] ?? 'Tool').' vs SnapDraft'],
        ],
    ])

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Compare</div>
            <h1>{{ $alternative['name'] ?? 'Tool' }} vs <em>SnapDraft</em></h1>
            <p>{{ $alternative['summary'] ?? $alternative['description'] ?? '' }}</p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @include('website.partials.compare-vs', ['alternative' => $alternative])

        @if (! empty($alternative['best_for_competitor']) || ! empty($alternative['best_for_snapdraft']))
            <div class="reveal" style="max-width: 800px; margin: 48px auto 48px">
                <div class="sd-sec-head">
                    <div class="sd-sec-eyebrow">At a glance</div>
                    <h2 class="sd-sec-title">Who each tool is <em>best for</em></h2>
                </div>
                <div class="sd-prose" style="margin-top: 24px">
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
                </div>
            </div>
        @endif

        @if (! empty($alternative['matrix']))
            <div class="sd-sec-head reveal">
                <div class="sd-sec-eyebrow">Feature matrix</div>
                <h2 class="sd-sec-title">Capability by capability</h2>
                <p class="sd-sec-sub">
                    Focused on weekly publishing ops, not every feature in either product.
                </p>
            </div>
            <div class="reveal" style="overflow-x: auto; margin-top: 32px">
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
        @endif
    </section>

    <section class="sd-section" style="padding-top: 24px">
        <div class="sd-sec-head reveal">
            <div class="sd-sec-eyebrow">Decision</div>
            <h2 class="sd-sec-title">When to pick <em>SnapDraft</em></h2>
            <p class="sd-sec-sub">
                Choose SnapDraft when the job is turning a content calendar into
                on-brand feed, story, and banner visuals. Brand DNA stays locked.
                Canvas handles last-mile feedback.
            </p>
        </div>
        <div class="reveal" style="text-align: center; margin-top: 28px">
            <p class="sd-feature-inline-links">
                Also see:
                <a href="/alternatives/{{ $alternative['slug'] }}">{{ $alternative['name'] ?? 'Tool' }} overview</a> ·
                <a href="/features">Features</a> ·
                <a href="/use-cases">Use cases</a> ·
                <a href="/pricing">Pricing</a>
            </p>
        </div>
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Ready to test the <em>batch workflow?</em></h2>
            <p>Upload references and a spreadsheet. Decide with a real calendar, not a demo deck.</p>
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
