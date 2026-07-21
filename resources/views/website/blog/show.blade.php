@extends('layouts.marketing', [
    'title' => ($post['title'] ?? 'Article').' - SnapDraft Blog',
    'description' => $post['excerpt'] ?? null,
    'ogImage' => $post['cover'] ?? '/images/marketing/og.png',
    'ogType' => 'article',
])

@section('content')
    <div class="sd-page-hero">
        <div class="reveal">
            <h1>{{ $post['title'] }}</h1>
            <p>
                @if (! empty($post['date_formatted']))
                    {{ $post['date_formatted'] }} ·
                @endif
                {{ $post['reading_minutes'] }} min read
            </p>
        </div>
    </div>

    @if (! empty($post['cover']))
        <div class="reveal sd-article-cover">
            <img src="{{ $post['cover'] }}" alt="">
        </div>
    @endif

    <article class="sd-article">
        <a href="/blog" class="sd-article-back">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            All articles
        </a>
        <div class="sd-prose">
            {!! $post['html'] !!}
        </div>
    </article>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Put it into <em>practice.</em></h2>
            <p>Turn your next content calendar into a batch of on-brand visuals.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Get started
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
@endsection
