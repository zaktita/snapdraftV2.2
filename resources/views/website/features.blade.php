@extends('layouts.marketing', [
    'title' => 'Features - SnapDraft',
    'description' => 'Brand DNA, spreadsheet batch generation, and a Canvas Editor so social media managers, freelancers, and agencies get on-brand visuals in minutes. Then tweak them until they fit.',
])

@section('content')
@php
    $features = [
        [
            'eyebrow' => 'Brand DNA',
            'title' => 'Teach the brand once. Keep every batch consistent',
            'desc' => 'Upload 5–10 references from a client or your own brand. SnapDraft extracts palette, composition, lighting, and typographic mood. That profile drives every generation, so freelancers and agencies stop re-briefing designers for each post.',
            'image' => '/images/marketing/feature-brand-dna.png',
            'bullets' => [
                'Automatic palette, composition, and typography extraction',
                'One profile per project. No re-teaching the AI',
                'Update references anytime when a brand evolves',
            ],
        ],
        [
            'eyebrow' => 'Batch generation',
            'title' => 'Your content calendar becomes the brief',
            'desc' => 'Plan the way you already do - in a spreadsheet. Each row with a title, description, and format becomes one finished visual, generated in parallel. A 50-row week of posts is ready in minutes instead of sitting in a designer queue.',
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
            'desc' => 'Client feedback rarely needs a full redesign. Replace text, swap objects, erase distractions, expand the canvas, and upscale - in the browser. Close revision loops without waiting on another ticket.',
            'image' => '/images/marketing/feature-canvas.png',
            'bullets' => [
                'AI-powered inpainting, erase, and object swap',
                'Canvas expand, background removal, and AI upscale',
                'Version history on your edits',
            ],
        ],
        [
            'eyebrow' => 'Every format',
            'title' => 'Feed, story, banner - sized correctly first',
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
            <div class="sd-sec-eyebrow">Features</div>
            <h1>
                Everything between the brief and the <em>publish button</em>
            </h1>
            <p>
                Stop waiting on designers. SnapDraft learns the brand,
                turns your spreadsheet into a batch, and lets you tweak
                anything before you ship.
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
                            <h3>{{ $feature['title'] }}</h3>
                            <p>{{ $feature['desc'] }}</p>
                            <ul class="sd-feature-row-list">
                                @foreach ($feature['bullets'] as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="sd-feature-row-media">
                            <img src="{{ $feature['image'] }}" alt="{{ $feature['title'] }}">
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
