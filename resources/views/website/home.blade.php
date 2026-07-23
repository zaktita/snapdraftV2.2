@extends('layouts.marketing', [
    'title' => 'SnapDraft - On-brand visuals without waiting on designers',
    'description' => 'For social media managers, freelancers, and agencies. Upload brand references, drop in your content calendar, and get on-brand visuals in minutes. Then tweak them until they fit.',
])

@section('content')
@php
    $currencySymbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
    $featureTabs = [
        [
            'id' => 'brand',
            'label' => 'Brand DNA analysis',
            'title' => 'Learn the brand once',
            'desc' => 'Upload client or brand references. SnapDraft locks in palette, composition, and typography so every batch looks like it came from the same studio without another designer round.',
            'image' => '/images/marketing/dna.jpg',
            'icon' => 'fa-palette',
            'tone' => 'yellow',
        ],
        [
            'id' => 'batch',
            'label' => 'Batch from CSV',
            'title' => 'Calendar in, visuals out',
            'desc' => 'Drop your content sheet. Each row becomes a finished visual in minutes. Plan captions in the spreadsheet, review the set, skip the prompt gymnastics.',
            'image' => '/images/marketing/csv.jpg',
            'icon' => 'fa-table',
            'tone' => 'pink',
        ],
        [
            'id' => 'canvas',
            'label' => 'Canvas Editor',
            'title' => 'Tweak until it fits',
            'desc' => 'Swap objects, fix headlines, erase, expand, and upscale. Handle last-mile client feedback yourself instead of waiting on another revision ticket.',
            'image' => '/images/marketing/edit.jpg',
            'icon' => 'fa-wand-magic-sparkles',
            'tone' => 'blue',
        ],
        [
            'id' => 'export',
            'label' => 'Export & delivery',
            'title' => 'Ready to schedule',
            'desc' => 'Download batches sized for feed, stories, and banners. Consistent formats and naming so you can hand off or post the same day.',
            'image' => '/images/marketing/download.jpg',
            'icon' => 'fa-layer-group',
            'tone' => 'orange',
        ],
    ];
    $colorCards = [
        ['tone' => 'orange', 'title' => 'Brand DNA', 'desc' => 'Lock the look once. Every client or brand stays consistent.', 'image' => '/images/marketing/dna.jpg'],
        ['tone' => 'ink', 'title' => 'Batch from CSV', 'desc' => 'A week of posts from one spreadsheet upload.', 'image' => '/images/marketing/csv.jpg'],
        ['tone' => 'pink', 'title' => 'Canvas polish', 'desc' => 'Tweak text, objects, and framing before you ship.', 'image' => '/images/marketing/edit.jpg'],
        ['tone' => 'blue', 'title' => 'Campaign export', 'desc' => 'Download a full set ready to schedule or send to clients.', 'image' => '/images/marketing/download.jpg'],
    ];
    $testimonials = [
        ['quote' => 'I used to sit on a designer queue for a week. Now I turn a content calendar into a full Instagram set in an afternoon - and it still looks like the brand.', 'name' => 'Priya Nair', 'role' => 'Social media manager', 'initials' => 'PN', 'tone' => 'pink'],
        ['quote' => 'As a freelancer I was the bottleneck. SnapDraft lets me generate a client batch, tweak headlines in Canvas, and deliver the same day.', 'name' => 'Jordan Blake', 'role' => 'Freelance content designer', 'initials' => 'JB', 'tone' => 'blue'],
        ['quote' => 'We run multiple brands. Brand DNA keeps each account looking distinct, and CSV batches mean we stop chasing designers for every post.', 'name' => 'Maya Chen', 'role' => 'Agency account lead', 'initials' => 'MC', 'tone' => 'coral'],
        ['quote' => 'Client feedback used to mean another round. Now I swap a logo, fix a line, export. Done. Revision loops got cut in half.', 'name' => 'Sam Ortiz', 'role' => 'Social media freelancer', 'initials' => 'SO', 'tone' => 'ink'],
        ['quote' => 'Consistency across 40 posts used to be impossible for our bench. SnapDraft is how we keep calendars full without adding design headcount.', 'name' => 'Alex Rivera', 'role' => 'Agency founder', 'initials' => 'AR', 'tone' => 'orange'],
        ['quote' => 'It feels like a production pipeline, not a prompt toy. We plan in the sheet, review finished work, and only open Canvas when something needs a tweak.', 'name' => 'Chris Adeyemi', 'role' => 'In-house social lead', 'initials' => 'CA', 'tone' => 'green'],
    ];
    $fallbackPlans = [
        [
            'id' => 'starter',
            'name' => 'Launch',
            'subtitle' => 'For freelancers and solo social managers',
            'price' => 29,
            'yearly_price' => 24,
            'yearly_total' => 288,
            'currency' => 'USD',
            'popular' => false,
            'features' => ['Brand DNA extraction', 'Batch generation from CSV', 'Canvas Editor for last-mile tweaks', 'Email support'],
        ],
        [
            'id' => 'pro',
            'name' => 'Growth',
            'subtitle' => 'For managers shipping weekly calendars',
            'price' => 79,
            'yearly_price' => 66,
            'yearly_total' => 792,
            'currency' => 'USD',
            'popular' => true,
            'features' => ['Everything in Launch', 'Higher monthly credits', 'Advanced Canvas Editor', 'Priority processing'],
        ],
        [
            'id' => 'business',
            'name' => 'Scale',
            'subtitle' => 'For agencies running multiple brands',
            'price' => 199,
            'yearly_price' => 166,
            'yearly_total' => 1992,
            'currency' => 'USD',
            'popular' => false,
            'features' => ['Everything in Growth', 'Larger CSV batches', 'Higher volume credits', 'Priority support'],
        ],
    ];
    $displayPlans = ! empty($plans) ? array_slice($plans, 0, 3) : $fallbackPlans;
    $hasPopular = collect($displayPlans)->contains(fn ($p) => ! empty($p['popular']));
    $planIcons = ['fa-rocket', 'fa-chart-line', 'fa-expand'];
    $aboutStats = [
        ['val' => '1 row', 'label' => 'One spreadsheet row → one post-ready visual'],
        ['val' => 'Minutes', 'label' => 'From calendar upload to a full batch'],
        ['val' => 'On-brand', 'label' => 'Stays consistent once Brand DNA is set'],
        ['val' => 'You', 'label' => 'Handle last-mile tweaks - no designer wait'],
    ];
    $fmt = function ($n) {
        return fmod((float) $n, 1.0) === 0.0 ? (string) (int) $n : number_format((float) $n, 2);
    };
@endphp

    <section class="sd-hero-shell sd-hero-shell-centered">
        <div class="sd-hero sd-hero-centered">
            <div class="sd-hero-floaters" aria-hidden="true">
                <div class="sd-hero-float sd-hero-float-tl">
                    <img src="/images/marketing/poster1.jpg" alt="">
                </div>
                <div class="sd-hero-float sd-hero-float-tr">
                    <img src="/images/marketing/poster2.jpg" alt="">
                </div>
                <div class="sd-hero-float sd-hero-float-bl">
                    <img src="/images/marketing/poster3.jpg" alt="">
                </div>
                <div class="sd-hero-float sd-hero-float-br">
                    <img src="/images/marketing/poster4.jpg" alt="">
                </div>
            </div>

            <div class="sd-hero-center">
                <div class="reveal">
                    <h1>Stop waiting on designers. Get the week's posts today.</h1>
                </div>
                <div class="reveal" style="transition-delay: 80ms">
                    <p class="sd-hero-desc">
                        Built for social media managers, freelancers,
                        and agencies. Turn brand references and a
                        content spreadsheet into a ready-to-post batch.
                        Then tweak anything until it fits.
                    </p>
                </div>
                <div class="reveal" style="transition-delay: 140ms">
                    <div class="sd-hero-cta">
                        <a href="{{ route('register') }}" class="sd-btn-hero sd-btn-hero-pill">
                            Generate your next batch
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="sd-trust-bar">
        <div class="sd-trust-bar-inner">
            <div class="sd-trust-bar-label">Built for people who ship weekly</div>
            Social media managers · freelancers · agencies
        </div>
    </div>

    <section class="sd-about-section">
        <div class="reveal">
            <div class="sd-about-intro">
                <div class="sd-sec-eyebrow">The bottleneck</div>
                <h2 class="sd-about-title">
                    Your calendar moves faster than the design queue.
                    SnapDraft closes that gap. On-brand visuals in
                    minutes, with room to tweak before you publish.
                </h2>
            </div>
        </div>
        <div class="sd-about-grid">
            @foreach ($aboutStats as $i => $s)
                @if ($i > 0)
                    <div class="sd-about-divider" aria-hidden="true">
                        <div class="sd-about-divider-space"></div>
                        <div class="sd-about-divider-center"></div>
                        <div class="sd-about-divider-space"></div>
                    </div>
                @endif
                <article class="sd-about-card">
                    <p class="sd-about-card-text">{{ $s['label'] }}</p>
                    <div class="sd-about-card-stat">{{ $s['val'] }}</div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="sd-feat-section" data-feat-tabs>
        <div class="reveal">
            <div class="sd-feat-head">
                <div class="sd-sec-eyebrow">How it works</div>
                <h2 class="sd-sec-title">From brief to batch without the wait</h2>
                <p class="sd-sec-sub">
                    Learn the brand, generate from your spreadsheet,
                    tweak in Canvas, and export. Built for calendar
                    speed, not design tickets.
                </p>
            </div>
        </div>

        <div class="sd-feat-tab-list" role="tablist">
            @foreach ($featureTabs as $i => $tab)
                <button
                    type="button"
                    role="tab"
                    data-feat-tab
                    data-tone="{{ $tab['tone'] }}"
                    class="sd-feat-tab{{ $i === 0 ? ' active sd-feat-tab-'.$tab['tone'] : '' }}"
                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>

        @foreach ($featureTabs as $i => $feature)
            <div
                class="sd-feat-panel sd-feat-panel-{{ $feature['tone'] }}"
                role="tabpanel"
                data-feat-panel
                @if ($i !== 0) hidden @endif
            >
                <div class="sd-feat-panel-copy">
                    <span class="sd-feat-panel-ico">
                        <i class="fa-solid {{ $feature['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <div class="sd-feat-panel-text">
                        <h3>{{ $feature['title'] }}</h3>
                        <p>{{ $feature['desc'] }}</p>
                    </div>
                </div>
                <div class="sd-feat-panel-media">
                    <img src="{{ $feature['image'] }}" alt="">
                </div>
            </div>
        @endforeach
    </section>

    <div class="sd-color-cards">
        @foreach ($colorCards as $i => $card)
            <div class="reveal" style="transition-delay: {{ $i * 60 }}ms">
                <div class="sd-color-card sd-color-card-{{ $card['tone'] }}">
                    <div class="sd-color-card-media">
                        <img src="{{ $card['image'] }}" alt="">
                    </div>
                    <div>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['desc'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="sd-pricing-section">
        <div class="reveal">
            <div class="sd-sec-head sd-pricing-head">
                <div class="sd-sec-eyebrow">Pricing</div>
                <h2 class="sd-sec-title">Plans for solo operators and agencies</h2>
                <p class="sd-sec-sub">
                    Brand DNA, batch generation, and Canvas tweaks on
                    every plan. Priced for freelancers, social managers,
                    and multi-brand teams.
                </p>
                <div class="sd-pricing-toggle" data-pricing-toggle data-pricing-target=".sd-home-pricing-grid">
                    <button type="button" data-period="monthly">Monthly</button>
                    <button type="button" data-period="yearly" class="active">
                        Yearly
                        <span class="sd-pricing-save">2 months free</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="sd-pricing-shell">
            <div class="sd-pricing-grid sd-home-pricing-grid">
                @foreach ($displayPlans as $i => $plan)
                    @php
                        $symbol = $currencySymbols[$plan['currency']] ?? $plan['currency'];
                        $featured = $hasPopular ? ! empty($plan['popular']) : $i === 1;
                        $icon = $planIcons[$i % count($planIcons)];
                    @endphp
                    <div
                        class="sd-price-card{{ $featured ? ' sd-price-card-featured' : '' }}"
                        data-price-card
                        data-monthly="{{ $fmt($plan['price']) }}"
                        data-yearly="{{ $fmt($plan['yearly_price']) }}"
                        data-yearly-total="{{ $fmt($plan['yearly_total']) }}"
                        data-symbol="{{ $symbol }}"
                    >
                        <div class="sd-price-plan-head">
                            <span class="sd-price-ico">
                                <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                            </span>
                            <span class="sd-price-name">{{ $plan['name'] }} plan</span>
                        </div>
                        <div class="sd-price-amount">
                            <strong data-price-amount>{{ $symbol }}{{ $fmt($plan['yearly_price']) }}</strong>
                            <span>/month</span>
                        </div>
                        <p class="sd-price-subtitle">{{ $plan['subtitle'] }}</p>
                        <div class="sd-price-billed" data-price-billed>
                            Billed {{ $symbol }}{{ $fmt($plan['yearly_total']) }} yearly
                        </div>
                        <ul class="sd-price-features">
                            @foreach ($plan['features'] as $f)
                                <li>
                                    <span class="sd-price-check">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    </span>
                                    {{ $f }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" class="sd-btn-price">Get Started</a>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="sd-cta-row" style="margin-top: 28px">
            <a href="/pricing" class="sd-btn-hero-ghost">
                Compare all plans
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="sd-quotes">
        <div class="reveal">
            <div class="sd-sec-head sd-quotes-head">
                <div class="sd-sec-eyebrow">Testimonials</div>
                <h2 class="sd-sec-title">Less queue time. More posts shipped.</h2>
            </div>
        </div>
        <div class="sd-quotes-grid">
            @foreach ($testimonials as $t)
                <article class="sd-quote-card">
                    <div class="sd-quote-mark" aria-hidden="true">&rdquo;</div>
                    <p>{{ $t['quote'] }}</p>
                    <div class="sd-quote-author">
                        <div class="sd-quote-avatar sd-quote-avatar-{{ $t['tone'] }}" aria-hidden="true">
                            {{ $t['initials'] }}
                        </div>
                        <div>
                            <strong>{{ $t['name'] }}</strong>
                            <span>{{ $t['role'] }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="sd-home-blog">
        <div class="sd-home-blog-head">
            <div>
                <div class="sd-sec-eyebrow">From the blog</div>
                <h2 class="sd-sec-title">Workflow tips for people who publish weekly</h2>
            </div>
            <a href="/blog" class="sd-btn-sm sd-home-blog-cta">Read the blog</a>
        </div>
        @if (empty($posts))
            <p class="sd-pricing-note">
                New posts coming soon. <a href="/blog">Visit the blog</a>.
            </p>
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
