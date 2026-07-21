@extends('layouts.marketing', [
    'title' => 'FAQ - SnapDraft',
    'description' => 'How SnapDraft helps social media managers, freelancers, and agencies get on-brand visuals in minutes. Brand DNA, batch generation, Canvas tweaks, credits, and ownership.',
])

@section('content')
@php
    $faqSections = [
        [
            'heading' => 'Product',
            'items' => [
                [
                    'q' => 'What exactly does SnapDraft do?',
                    'a' => 'SnapDraft helps social media managers, freelancers, and agencies get on-brand visuals without waiting on designers. Upload brand references, drop in a content spreadsheet, and get a finished visual per row in minutes. Then tweak anything in the Canvas Editor before you publish or send to a client.',
                ],
                [
                    'q' => 'Who is SnapDraft for?',
                    'a' => 'People who ship weekly content but hit a design bottleneck: in-house social managers, freelance creators handling multiple clients, and agencies producing calendars across brands. If you already plan in a spreadsheet and need visuals that stay on-brand, you are the audience.',
                ],
                [
                    'q' => 'How should my spreadsheet be set up?',
                    'a' => 'Use title, description, and format columns. Each row becomes one generated visual matching the Brand DNA. Regenerate individual rows without redoing the whole batch. Useful when a client changes one caption mid-week.',
                ],
                [
                    'q' => 'What are brand references?',
                    'a' => 'Brand references are 5–10 images that represent the visual identity - past posts, campaign assets, or guideline exports. SnapDraft extracts color, composition, and typography cues so every batch stays consistent for that client or brand.',
                ],
                [
                    'q' => 'Can I edit the generated images?',
                    'a' => 'Yes. Canvas is built for last-mile tweaks: replace text, swap objects with AI, erase distractions, expand the canvas, remove backgrounds, and upscale - so you close feedback loops yourself instead of opening another design ticket.',
                ],
                [
                    'q' => 'What formats and sizes are supported?',
                    'a' => 'Square, portrait, and landscape aspect ratios are generated natively. No cropping after the fact. Set the format per spreadsheet row and download everything in batch for feed, stories, or banners.',
                ],
            ],
        ],
        [
            'heading' => 'Billing & account',
            'items' => [
                [
                    'q' => 'How do credits work?',
                    'a' => 'One credit generates one visual. Regenerations and variations each use a credit. Credits reset at the start of every billing cycle, and you can top up or upgrade if you run out mid-calendar.',
                ],
                [
                    'q' => 'Can I cancel anytime?',
                    'a' => 'Yes. Cancel from your billing settings and your plan stays active until the end of the current period. No emails, no phone calls.',
                ],
                [
                    'q' => 'Do you offer refunds?',
                    'a' => 'See our refund policy for the details. If something is not working, contact us first. We usually fix it faster than a refund would take.',
                ],
                [
                    'q' => 'Who owns the generated images?',
                    'a' => 'You do. Images generated from your references and spreadsheets are yours to use commercially for clients or your own brands.',
                ],
            ],
        ],
    ];
@endphp

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">FAQ</div>
            <h1>Frequently asked <em>questions</em></h1>
            <p>
                From Brand DNA to billing - and how SnapDraft fits a
                weekly publishing workflow. Can't find your answer?
                <a href="/contact">Contact us</a>.
            </p>
        </div>
    </div>

    @foreach ($faqSections as $sectionIdx => $section)
        <section class="sd-section" style="padding-top: {{ $sectionIdx === 0 ? 0 : 24 }}px">
            <div class="reveal">
                <div class="sd-sec-eyebrow">{{ $section['heading'] }}</div>
            </div>
            <div class="sd-faq-wrap" data-faq style="margin-top: 20px">
                @foreach ($section['items'] as $idx => $item)
                    <div class="reveal" style="transition-delay: {{ $idx * 40 }}ms">
                        <div class="sd-faq-item{{ $sectionIdx === 0 && $idx === 0 ? ' open' : '' }}">
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
    @endforeach

    <section class="sd-cta">
        <div class="reveal">
            <h2>Still stuck in a designer queue? <em>Try a batch.</em></h2>
            <p>The fastest answer is generating your next calendar yourself.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Generate your next batch
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/contact" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">Ask a question</a>
            </div>
        </div>
    </section>
@endsection
