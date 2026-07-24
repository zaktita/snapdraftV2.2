@extends('layouts.marketing', [
    'title' => 'SnapDraft alternatives | Compare tools for calendar batches',
    'description' => 'Compare SnapDraft with Canva, Midjourney, Figma, Predis, Later, and more when you need Brand DNA batches from a content calendar.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Alternatives</div>
            <h1>SnapDraft vs the tools you <em>already use</em></h1>
            <p>
                Honest comparisons for people choosing between template editors,
                generative explorers, design systems, and schedulers versus a
                spreadsheet-native batch pipeline.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @if (empty($alternatives))
            <div class="reveal">
                <p class="sd-pricing-note">Comparisons coming soon.</p>
            </div>
        @else
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($alternatives as $i => $alt)
                    <div class="reveal" style="transition-delay: {{ $i * 60 }}ms">
                        <a href="/alternatives/{{ $alt['slug'] }}" class="sd-blog-card sd-home-blog-card">
                            @if (! empty($alt['image']))
                                <div class="sd-blog-card-cover sd-alt-card-cover">
                                    <img
                                        src="{{ $alt['image'] }}"
                                        alt="{{ $alt['image_alt'] ?? (($alt['name'] ?? 'Tool').' comparison mark') }}"
                                        loading="lazy"
                                        decoding="async"
                                        width="640"
                                        height="360"
                                    >
                                </div>
                            @endif
                            <div class="sd-blog-card-body">
                                <span class="sd-blog-card-tag">{{ $alt['name'] ?? 'Alternative' }}</span>
                                <h3>{{ $alt['title'] ?? ($alt['name'].' alternatives') }}</h3>
                                @if (! empty($alt['summary']))
                                    <p>{{ $alt['summary'] }}</p>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
            <p class="sd-feature-inline-links reveal" style="text-align: center; margin-top: 36px">
                Prefer a head-to-head page? Open any alternative, then jump to a compare URL.
                Example: <a href="/compare/canva-vs-snapdraft">Canva vs SnapDraft</a>.
            </p>
        @endif
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Already decided? <em>Try a batch.</em></h2>
            <p>Bring brand references and a spreadsheet into SnapDraft.</p>
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
