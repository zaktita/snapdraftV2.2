@extends('layouts.marketing', [
    'title' => 'SnapDraft Features | Brand DNA, CSV batches, Canvas Editor',
    'description' => 'See how SnapDraft works: Brand DNA locks the look, CSV batch generation turns your calendar into visuals, and Canvas finishes last-mile edits before you publish.',
])

@section('content')
@php
    $features = [
        [
            'eyebrow' => 'Brand DNA',
            'title' => 'Teach the brand once. Keep every batch consistent',
            'desc' => 'Upload 5-10 references from a client or your own brand. SnapDraft extracts palette, composition, lighting, and typographic mood. That profile drives every generation, so freelancers and agencies stop re-briefing designers for each post.',
            'image' => '/images/marketing/feature-brand-dna.png',
            'bullets' => [
                'Automatic palette, composition, and typography extraction',
                'One profile per project. No re-teaching the model',
                'Update references anytime when a brand evolves',
            ],
        ],
        [
            'eyebrow' => 'Batch generation',
            'title' => 'Your content calendar becomes the brief',
            'desc' => 'Plan the way you already do, in a spreadsheet. Each row with a title, description, and format becomes one finished visual, generated in parallel. A 50-row week of posts is ready for review instead of sitting in a designer queue.',
            'image' => '/images/marketing/feature-batch.png',
            'bullets' => [
                'CSV upload with title, description, and format columns',
                'Parallel generation with per-row progress',
                'Regenerate any row without redoing the batch',
            ],
        ],
        [
            'eyebrow' => 'Canvas Editor',
            'title' => 'Tweak the last mile yourself',
            'desc' => 'Client feedback rarely needs a full redesign. Replace text, swap objects, erase distractions, expand the canvas, and upscale in the browser. Close revision loops without waiting on another ticket.',
            'image' => '/images/marketing/feature-canvas.png',
            'bullets' => [
                'AI-powered inpainting, erase, and object swap',
                'Canvas expand, background removal, and AI upscale',
                'Version history on your edits',
            ],
        ],
        [
            'eyebrow' => 'Every format',
            'title' => 'Feed, story, banner. Sized correctly first',
            'desc' => 'Set the format per row and SnapDraft generates each visual in the right aspect ratio. Social managers and agencies download a set ready to schedule or hand off. No awkward crops.',
            'image' => '/images/marketing/feature-export.png',
            'bullets' => [
                'Square, portrait, and landscape aspect ratios',
                'Batch download of finished assets',
                'High-resolution output ready for web or print',
            ],
        ],
    ];
@endphp

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">SnapDraft features</div>
            <h1>
                Everything between the brief and the <em>publish button</em>
            </h1>
            <p>
                SnapDraft learns the brand, turns your spreadsheet into a batch,
                and lets you tweak anything before you ship. Built for calendar
                speed, not design tickets.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 24px">
        <div class="sd-feature-rows">
            @foreach ($features as $i => $feature)
                <div class="reveal" style="transition-delay: 60ms">
                    <div class="sd-feature-row{{ $i % 2 === 1 ? ' sd-feature-row-reversed' : '' }}">
                        <div class="sd-feature-row-copy">
                            <div class="sd-sec-eyebrow">{{ $feature['eyebrow'] }}</div>
                            <h2>{{ $feature['title'] }}</h2>
                            <p>{{ $feature['desc'] }}</p>
                            <ul class="sd-feature-row-list">
                                @foreach ($feature['bullets'] as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                            @if ($i === 0)
                                <p class="sd-feature-inline-links">
                                    Related:
                                    <a href="/glossary/brand-dna">Brand DNA</a> ·
                                    <a href="/faq">FAQ</a> ·
                                    <a href="/blog/why-brand-consistency-beats-volume">Consistency vs volume</a>
                                </p>
                            @elseif ($i === 1)
                                <p class="sd-feature-inline-links">
                                    Related:
                                    <a href="/glossary/csv-batch-generation">CSV batch generation</a> ·
                                    <a href="/blog/from-spreadsheet-to-campaign">Spreadsheet to campaign</a> ·
                                    <a href="/templates/csv-weekly-calendar">Weekly calendar template</a>
                                </p>
                            @elseif ($i === 2)
                                <p class="sd-feature-inline-links">
                                    Related:
                                    <a href="/glossary/canvas-editor">Canvas Editor</a> ·
                                    <a href="/use-cases/freelancers">For freelancers</a> ·
                                    <a href="/faq">Editing FAQ</a>
                                </p>
                            @else
                                <p class="sd-feature-inline-links">
                                    Related:
                                    <a href="/templates/multi-format-export">Multi-format export</a> ·
                                    <a href="/pricing">Pricing</a>
                                </p>
                            @endif
                        </div>
                        <div class="sd-feature-row-media">
                            <img src="{{ $feature['image'] }}" alt="{{ $feature['title'] }}" width="960" height="720" loading="lazy" decoding="async">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Try it on <em>your next calendar.</em></h2>
            <p>Bring brand references and a spreadsheet. See a batch in minutes.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Generate your next batch
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/pricing" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">View pricing</a>
            </div>
        </div>
    </section>
@endsection
