@extends('layouts.marketing', [
    'title' => 'Pricing - SnapDraft',
    'description' => 'Plans for freelancers, social media managers, and agencies. Every plan includes Brand DNA, spreadsheet batch generation, and Canvas tweaks.',
])

@section('content')
@php
    $currencySymbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
    $planIcons = ['fa-mobile-screen', 'fa-sparkles', 'fa-layer-group'];
    $plans = $plans ?? [];
    $hasPopular = collect($plans)->contains(fn ($p) => ! empty($p['popular']));
    $fmt = function ($n) {
        return fmod((float) $n, 1.0) === 0.0 ? (string) (int) $n : number_format((float) $n, 2);
    };
    $pricingFaq = [
        [
            'q' => 'What is a production credit?',
            'a' => 'One credit generates one visual. Regenerating a row or generating a variation uses another credit. Credits reset with each billing cycle.',
        ],
        [
            'q' => 'Can I change plans later?',
            'a' => 'Yes - upgrade or downgrade anytime from your billing settings. Upgrades take effect immediately; downgrades apply at the next renewal.',
        ],
        [
            'q' => 'Do unused credits roll over?',
            'a' => 'Credits reset at each billing cycle. If you regularly run out mid-cycle, you can purchase credit top-ups or move to a bigger plan.',
        ],
        [
            'q' => 'Is there a refund policy?',
            'a' => 'Yes - see our refund policy for details. If SnapDraft is not working out, contact us and we will make it right.',
        ],
    ];
@endphp

    <section class="sd-pricing-section" style="padding-top: 132px">
        <div class="reveal">
            <div class="sd-sec-head sd-pricing-head">
                <div class="sd-sec-eyebrow">Pricing</div>
                <h1 class="sd-sec-title">Plans for solo operators and agencies</h1>
                <p class="sd-sec-sub">
                    Brand DNA, batch generation, and Canvas on every
                    plan. So you ship on-brand visuals without waiting
                    on a designer.
                </p>
                <div class="sd-pricing-toggle" data-pricing-toggle data-pricing-target=".sd-pricing-page-grid">
                    <button type="button" data-period="monthly">Monthly</button>
                    <button type="button" data-period="yearly" class="active">
                        Yearly
                        <span class="sd-pricing-save">2 months free</span>
                    </button>
                </div>
            </div>
        </div>

        @if (count($plans) === 0)
            <div class="reveal">
                <p class="sd-pricing-note">
                    Plans are being finalized -
                    <a href="/contact">contact us</a> for current pricing.
                </p>
            </div>
        @else
            <div class="sd-pricing-shell">
                <div class="sd-pricing-grid sd-pricing-page-grid">
                    @foreach ($plans as $i => $plan)
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
                                @foreach ($plan['features'] as $feature)
                                    <li>
                                        <span class="sd-price-check">
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        </span>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" class="sd-btn-price">Get Started</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        <p class="sd-pricing-note">
            Prices exclude VAT where applicable. Cancel anytime from
            your billing settings.
        </p>
    </section>

    <section class="sd-section" style="padding-top: 0">
        <div class="reveal">
            <div class="sd-sec-eyebrow">Pricing FAQ</div>
            <h2 class="sd-sec-title">Common <em>questions</em></h2>
        </div>
        <div class="sd-faq-wrap" data-faq>
            @foreach ($pricingFaq as $idx => $item)
                <div class="sd-faq-item{{ $idx === 0 ? ' open' : '' }}">
                    <button type="button" class="sd-faq-btn">
                        <span>{{ $item['q'] }}</span>
                        <span class="sd-faq-ico">+</span>
                    </button>
                    <div class="sd-faq-ans">{{ $item['a'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="sd-cta">
        <div class="reveal">
            <h2>Try it on <em>your next calendar.</em></h2>
            <p>Lock Brand DNA once, generate a batch in minutes, tweak what needs it.</p>
            <div class="sd-cta-row">
                <a href="{{ route('register') }}" class="sd-btn-hero">
                    Generate your next batch
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
@endsection
