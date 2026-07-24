@extends('layouts.marketing', [
    'title' => 'SnapDraft templates | Brand DNA, CSV, Canvas workflows',
    'description' => 'Repeatable SnapDraft workflows for Brand DNA setup, CSV weekly calendars, Canvas polish, multi-format export, and client delivery batches.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Templates</div>
            <h1>Workflows you can <em>repeat</em></h1>
            <p>
                Product-backed patterns from locking Brand DNA to delivering a
                client-ready batch. So you do not reinvent the calendar every week.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @if (empty($templates))
            <div class="reveal">
                <p class="sd-pricing-note">Templates coming soon.</p>
            </div>
        @else
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($templates as $i => $template)
                    <div class="reveal" style="transition-delay: {{ $i * 80 }}ms">
                        <a href="/templates/{{ $template['slug'] }}" class="sd-blog-card sd-home-blog-card">
                            @if (! empty($template['image']))
                                <div class="sd-blog-card-cover">
                                    <img
                                        src="{{ $template['image'] }}"
                                        alt="{{ $template['image_alt'] ?? $template['title'] }}"
                                        loading="lazy"
                                        decoding="async"
                                        width="640"
                                        height="360"
                                    >
                                </div>
                            @endif
                            <div class="sd-blog-card-body">
                                @if (! empty($template['tags'][0]))
                                    <span class="sd-blog-card-tag">{{ $template['tags'][0] }}</span>
                                @else
                                    <span class="sd-blog-card-tag">Template</span>
                                @endif
                                <h3>{{ $template['title'] }}</h3>
                                @if (! empty($template['excerpt']))
                                    <p>{{ $template['excerpt'] }}</p>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Ready to run one on <em>your brand?</em></h2>
            <p>Start with Brand DNA, then upload the week’s sheet.</p>
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
