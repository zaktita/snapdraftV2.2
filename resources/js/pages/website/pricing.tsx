import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Layers, Smartphone, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface MarketingPlan {
    id: string;
    name: string;
    subtitle: string;
    price: number;
    yearly_price: number;
    yearly_total: number;
    currency: string;
    popular: boolean;
    features: string[];
}

const CURRENCY_SYMBOLS: Record<string, string> = {
    EUR: '€',
    USD: '$',
    GBP: '£',
};

const PLAN_ICONS = [Smartphone, Sparkles, Layers] as const;

const PRICING_FAQ = [
    {
        q: 'What is a production credit?',
        a: 'One credit generates one visual. Regenerating a row or generating a variation uses another credit. Credits reset with each billing cycle.',
    },
    {
        q: 'Can I change plans later?',
        a: 'Yes - upgrade or downgrade anytime from your billing settings. Upgrades take effect immediately; downgrades apply at the next renewal.',
    },
    {
        q: 'Do unused credits roll over?',
        a: 'Credits reset at each billing cycle. If you regularly run out mid-cycle, you can purchase credit top-ups or move to a bigger plan.',
    },
    {
        q: 'Is there a refund policy?',
        a: 'Yes - see our refund policy for details. If SnapDraft is not working out, contact us and we will make it right.',
    },
];

export default function PricingPage({ plans }: { plans: MarketingPlan[] }) {
    const [period, setPeriod] = useState<'monthly' | 'yearly'>('yearly');
    const [openFaq, setOpenFaq] = useState(0);
    const hasPopular = plans.some((p) => p.popular);

    return (
        <MarketingLayout
            title="Pricing - SnapDraft"
            description="Plans for freelancers, social media managers, and agencies. Every plan includes Brand DNA, spreadsheet batch generation, and Canvas tweaks."
        >
            <section className="sd-pricing-section" style={{ paddingTop: 132 }}>
                <Reveal>
                    <div className="sd-sec-head sd-pricing-head">
                        <div className="sd-sec-eyebrow">Pricing</div>
                        <h1 className="sd-sec-title">
                            Plans for solo operators and agencies
                        </h1>
                        <p className="sd-sec-sub">
                            Brand DNA, batch generation, and Canvas on every
                            plan. So you ship on-brand visuals without waiting
                            on a designer.
                        </p>
                        <div className="sd-pricing-toggle">
                            <button
                                type="button"
                                className={period === 'monthly' ? 'active' : ''}
                                onClick={() => setPeriod('monthly')}
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                className={period === 'yearly' ? 'active' : ''}
                                onClick={() => setPeriod('yearly')}
                            >
                                Yearly
                                <span className="sd-pricing-save">
                                    2 months free
                                </span>
                            </button>
                        </div>
                    </div>
                </Reveal>

                {plans.length === 0 ? (
                    <Reveal>
                        <p className="sd-pricing-note">
                            Plans are being finalized -{' '}
                            <Link href="/contact">contact us</Link> for current
                            pricing.
                        </p>
                    </Reveal>
                ) : (
                    <div className="sd-pricing-shell">
                        <div className="sd-pricing-grid">
                            {plans.map((plan, i) => {
                                const symbol =
                                    CURRENCY_SYMBOLS[plan.currency] ??
                                    plan.currency;
                                const monthly =
                                    period === 'yearly'
                                        ? plan.yearly_price
                                        : plan.price;
                                const Icon = PLAN_ICONS[i % PLAN_ICONS.length];
                                const featured = hasPopular
                                    ? plan.popular
                                    : i === 1;
                                return (
                                    <div
                                        key={plan.id}
                                        className={
                                            featured
                                                ? 'sd-price-card sd-price-card-featured'
                                                : 'sd-price-card'
                                        }
                                    >
                                        <div className="sd-price-plan-head">
                                            <span className="sd-price-ico">
                                                <Icon
                                                    size={16}
                                                    strokeWidth={2.25}
                                                />
                                            </span>
                                            <span className="sd-price-name">
                                                {plan.name} plan
                                            </span>
                                        </div>
                                        <div className="sd-price-amount">
                                            <strong>
                                                {symbol}
                                                {monthly % 1 === 0
                                                    ? monthly
                                                    : monthly.toFixed(2)}
                                            </strong>
                                            <span>/month</span>
                                        </div>
                                        <p className="sd-price-subtitle">
                                            {plan.subtitle}
                                        </p>
                                        <div className="sd-price-billed">
                                            {period === 'yearly'
                                                ? `Billed ${symbol}${plan.yearly_total % 1 === 0 ? plan.yearly_total : plan.yearly_total.toFixed(2)} yearly`
                                                : 'Billed monthly'}
                                        </div>
                                        <ul className="sd-price-features">
                                            {plan.features.map((feature) => (
                                                <li key={feature}>
                                                    <span className="sd-price-check">
                                                        <Check
                                                            size={12}
                                                            strokeWidth={3}
                                                        />
                                                    </span>
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={register().url}
                                            className="sd-btn-price"
                                        >
                                            Get Started
                                        </Link>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
                <p className="sd-pricing-note">
                    Prices exclude VAT where applicable. Cancel anytime from
                    your billing settings.
                </p>
            </section>

            <section className="sd-section" style={{ paddingTop: 0 }}>
                <Reveal>
                    <div className="sd-sec-eyebrow">Pricing FAQ</div>
                    <h2 className="sd-sec-title">
                        Common <em>questions</em>
                    </h2>
                </Reveal>
                <div className="sd-faq-wrap">
                    {PRICING_FAQ.map((item, idx) => {
                        const isOpen = openFaq === idx;
                        return (
                            <div
                                key={item.q}
                                className={
                                    isOpen ? 'sd-faq-item open' : 'sd-faq-item'
                                }
                            >
                                <button
                                    type="button"
                                    className="sd-faq-btn"
                                    onClick={() =>
                                        setOpenFaq(isOpen ? -1 : idx)
                                    }
                                >
                                    <span>{item.q}</span>
                                    <span className="sd-faq-ico">+</span>
                                </button>
                                <div className="sd-faq-ans">{item.a}</div>
                            </div>
                        );
                    })}
                </div>
            </section>

            <section className="sd-cta">
                <Reveal>
                    <h2>
                        Try it on <em>your next calendar.</em>
                    </h2>
                    <p>
                        Lock Brand DNA once, generate a batch in minutes, tweak
                        what needs it.
                    </p>
                    <div className="sd-cta-row">
                        <Link href={register().url} className="sd-btn-hero">
                            Generate your next batch
                            <ArrowRight size={16} />
                        </Link>
                    </div>
                </Reveal>
            </section>
        </MarketingLayout>
    );
}
