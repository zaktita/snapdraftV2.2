@extends('layouts.marketing', [
    'title' => 'About SnapDraft | On-brand visuals at calendar speed',
    'description' => 'SnapDraft is the production tool for social media managers, freelancers, and agencies who need on-brand visuals from a content calendar. Brand DNA, CSV batches, and Canvas edits.',
])

@section('content')
@php
    $aboutSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'About SnapDraft',
        'url' => url('/about'),
        'description' => 'SnapDraft helps social media managers, freelancers, and agencies ship on-brand visuals without waiting on designers.',
        'mainEntity' => [
            '@id' => url('/').'#organization',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($aboutSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>

    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">About SnapDraft</div>
            <h1>We built SnapDraft for the <em>design queue</em> problem</h1>
            <p>
                Your content calendar moves every week. Design capacity does not.
                SnapDraft closes that gap with on-brand batches you can finish yourself.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        <div class="reveal">
            <div class="sd-sec-head">
                <div class="sd-sec-eyebrow">Mission</div>
                <h2 class="sd-sec-title">On-brand visuals at <em>calendar speed</em></h2>
                <p class="sd-sec-sub">
                    SnapDraft turns brand references and a content spreadsheet into a finished
                    batch, then lets you tweak the last mile before you publish or deliver.
                </p>
            </div>
            <div class="sd-prose" style="max-width: 720px; margin: 32px auto 0">
                <p>
                    <strong>SnapDraft</strong> is a web app for weekly visual production.
                    Social teams already plan in sheets. Freelancers juggle multiple client looks.
                    Agencies know volume breaks when every post is a fresh design ticket.
                </p>
                <p>
                    The product workflow is intentional: lock <a href="/glossary/brand-dna">Brand DNA</a> once,
                    generate from <a href="/glossary/csv-batch-generation">CSV rows</a>, polish in the
                    <a href="/glossary/canvas-editor">Canvas Editor</a>, then export.
                    That is the pipeline. Not another prompt toy.
                </p>
                <p>
                    We are not trying to replace craft for big campaigns. We remove the wait
                    for routine calendar work that usually clogs the queue, so you can spend
                    design attention where it actually matters.
                </p>
            </div>
        </div>
    </section>

    <section class="sd-section" style="padding-top: 24px">
        <div class="sd-sec-head reveal">
            <div class="sd-sec-eyebrow">Who it is for</div>
            <h2 class="sd-sec-title">Built for people who <em>publish weekly</em></h2>
            <p class="sd-sec-sub">
                Same SnapDraft pipeline for every audience. Brand DNA, batch generation, and Canvas,
                tuned to how each role works.
            </p>
        </div>
        <div class="sd-blog-grid sd-home-blog-grid" style="margin-top: 40px">
            <div class="reveal" style="transition-delay: 0ms">
                <a href="/use-cases/social-media-managers" class="sd-blog-card sd-home-blog-card">
                    <div class="sd-blog-card-body">
                        <span class="sd-blog-card-tag">Use case</span>
                        <h3>Social media managers</h3>
                        <p>Ship the week's posts without waiting on design.</p>
                    </div>
                </a>
            </div>
            <div class="reveal" style="transition-delay: 80ms">
                <a href="/use-cases/freelancers" class="sd-blog-card sd-home-blog-card">
                    <div class="sd-blog-card-body">
                        <span class="sd-blog-card-tag">Use case</span>
                        <h3>Freelancers</h3>
                        <p>Deliver client batches the same day, without being the bottleneck.</p>
                    </div>
                </a>
            </div>
            <div class="reveal" style="transition-delay: 160ms">
                <a href="/use-cases/agencies" class="sd-blog-card sd-home-blog-card">
                    <div class="sd-blog-card-body">
                        <span class="sd-blog-card-tag">Use case</span>
                        <h3>Agencies</h3>
                        <p>Keep multi-brand calendars full without adding design headcount.</p>
                    </div>
                </a>
            </div>
        </div>
        <p class="sd-feature-inline-links reveal" style="text-align: center; margin-top: 28px">
            Dig deeper:
            <a href="/features">Features</a> ·
            <a href="/pricing">Pricing</a> ·
            <a href="/use-cases">All use cases</a> ·
            <a href="/contact">Contact</a>
        </p>
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Ready to try it on <em>your next calendar?</em></h2>
            <p>Bring brand references and a spreadsheet. Generate a SnapDraft batch in minutes.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Generate your next batch
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="/features" class="sd-btn-hero-ghost sd-btn-hero-ghost-inv">See features</a>
            </div>
        </div>
    </section>
@endsection
