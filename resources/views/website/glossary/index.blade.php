@extends('layouts.marketing', [
    'title' => 'SnapDraft glossary | Brand DNA, CSV batches, Canvas',
    'description' => 'Definitions for Brand DNA, CSV batch generation, Canvas Editor, on-brand consistency, and content calendar to visuals. SnapDraft production vocabulary.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Glossary</div>
            <h1>Words we use on <em>purpose</em></h1>
            <p>
                Short definitions for the concepts behind the SnapDraft workflow,
                so Brand DNA and CSV batch mean the same thing on every page.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @if (empty($terms))
            <div class="reveal">
                <p class="sd-pricing-note">Glossary terms coming soon.</p>
            </div>
        @else
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($terms as $i => $term)
                    <div class="reveal" style="transition-delay: {{ $i * 60 }}ms">
                        <a href="/glossary/{{ $term['slug'] }}" class="sd-blog-card sd-home-blog-card">
                            <div class="sd-blog-card-body">
                                <span class="sd-blog-card-tag">Term</span>
                                <h3>{{ $term['term'] ?? $term['title'] }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($term['definition'] ?? $term['description'] ?? '', 140) }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>See the words in <em>the product.</em></h2>
            <p>Brand DNA, batches, and Canvas, shown in the SnapDraft flow.</p>
            <div class="sd-cta-row">
                <a href="/features" class="sd-btn-hero">
                    Explore features
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/templates" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">Browse templates</a>
            </div>
        </div>
    </section>
@endsection
