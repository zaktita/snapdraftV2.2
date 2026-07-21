@extends('layouts.marketing', [
    'title' => 'Blog - SnapDraft',
    'description' => 'Workflow tips for social media managers, freelancers, and agencies. Brand consistency, batch calendars, and shipping visuals without the designer wait.',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <div class="sd-sec-eyebrow">From the blog</div>
            <h1>Tips for people who publish weekly</h1>
            <p>
                Brand consistency, spreadsheet-to-visual workflows, and
                how to close revision loops without waiting on a
                designer.
            </p>
        </div>
    </div>

    <section class="sd-section" style="padding-top: 0">
        @if (empty($posts))
            <div class="reveal">
                <p class="sd-pricing-note">No posts yet - check back soon.</p>
            </div>
        @else
            <div class="sd-blog-grid sd-home-blog-grid">
                @foreach ($posts as $i => $post)
                    <div class="reveal" style="transition-delay: {{ $i * 80 }}ms">
                        <a href="/blog/{{ $post['slug'] }}" class="sd-blog-card sd-home-blog-card">
                            @if (! empty($post['cover']))
                                <div class="sd-blog-card-cover">
                                    <img src="{{ $post['cover'] }}" alt="" loading="lazy">
                                </div>
                            @endif
                            <div class="sd-blog-card-body">
                                <span class="sd-blog-card-tag">{{ $post['category'] ?? 'News' }}</span>
                                <h3>{{ $post['title'] }}</h3>
                                @if (! empty($post['date_formatted']))
                                    <div class="sd-blog-card-date">{{ $post['date_formatted'] }}</div>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
