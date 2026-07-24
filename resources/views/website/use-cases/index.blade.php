@extends('layouts.marketing', [
    'title' => 'SnapDraft use cases | Social managers, freelancers, agencies',
    'description' => 'See how SnapDraft helps social media managers, freelancers, and agencies ship on-brand visuals from content calendars without the designer wait.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Use cases</div>
            <h1>SnapDraft for the roles that <em>publish weekly</em></h1>
            <p>
                Same product pipeline for every audience. Brand DNA, CSV batches,
                and Canvas. Different bottlenecks, different proof.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @if (empty($useCases))
            <div class="reveal">
                <p class="sd-pricing-note">Use cases coming soon.</p>
            </div>
        @else
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($useCases as $i => $useCase)
                    <div class="reveal" style="transition-delay: {{ $i * 60 }}ms">
                        <a href="/use-cases/{{ $useCase['slug'] }}" class="sd-blog-card sd-home-blog-card">
                            @if (! empty($useCase['image']))
                                <div class="sd-blog-card-cover">
                                    <img
                                        src="{{ $useCase['image'] }}"
                                        alt="{{ $useCase['image_alt'] ?? $useCase['title'] }}"
                                        loading="lazy"
                                        decoding="async"
                                        width="640"
                                        height="360"
                                    >
                                </div>
                            @endif
                            <div class="sd-blog-card-body">
                                <span class="sd-blog-card-tag">{{ $useCase['audience'] ?? 'Audience' }}</span>
                                <h3>{{ $useCase['headline'] ?? $useCase['title'] }}</h3>
                                @if (! empty($useCase['description']))
                                    <p>{{ $useCase['description'] }}</p>
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
            <h2>Ready to match SnapDraft to <em>your role?</em></h2>
            <p>Pick a use case above, or jump straight into a batch.</p>
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
