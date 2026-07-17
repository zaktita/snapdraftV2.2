import { Reveal } from '@/components/marketing/reveal';
import MarketingLayout from '@/layouts/marketing-layout';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Layers,
    Palette,
    Smartphone,
    Sparkles,
    Table2,
    Wand2,
} from 'lucide-react';
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

interface BlogPostSummary {
    slug: string;
    title: string;
    excerpt: string;
    cover: string | null;
    category?: string;
    date: string | null;
    date_formatted: string | null;
    reading_minutes: number;
}

const CURRENCY_SYMBOLS: Record<string, string> = {
    EUR: '€',
    USD: '$',
    GBP: '£',
};

const FEATURE_TABS = [
    {
        id: 'brand',
        label: 'Brand DNA analysis',
        title: 'Learn the brand once',
        desc: 'Upload client or brand references. SnapDraft locks in palette, composition, and typography so every batch looks like it came from the same studio without another designer round.',
        image: '/images/marketing/feature-brand-dna.png',
        icon: Palette,
        tone: 'yellow',
    },
    {
        id: 'batch',
        label: 'Batch from CSV',
        title: 'Calendar in, visuals out',
        desc: 'Drop your content sheet. Each row becomes a finished visual in minutes. Plan captions in the spreadsheet, review the set, skip the prompt gymnastics.',
        image: '/images/marketing/feature-batch.png',
        icon: Table2,
        tone: 'pink',
    },
    {
        id: 'canvas',
        label: 'Canvas Editor',
        title: 'Tweak until it fits',
        desc: 'Swap objects, fix headlines, erase, expand, and upscale. Handle last-mile client feedback yourself instead of waiting on another revision ticket.',
        image: '/images/marketing/feature-canvas.png',
        icon: Wand2,
        tone: 'blue',
    },
    {
        id: 'export',
        label: 'Export & delivery',
        title: 'Ready to schedule',
        desc: 'Download batches sized for feed, stories, and banners. Consistent formats and naming so you can hand off or post the same day.',
        image: '/images/marketing/feature-export.png',
        icon: Layers,
        tone: 'orange',
    },
] as const;

const COLOR_CARDS = [
    {
        tone: 'orange',
        title: 'Brand DNA',
        desc: 'Lock the look once. Every client or brand stays consistent.',
        image: '/images/marketing/feature-brand-dna.png',
    },
    {
        tone: 'ink',
        title: 'Batch from CSV',
        desc: 'A week of posts from one spreadsheet upload.',
        image: '/images/marketing/feature-batch.png',
    },
    {
        tone: 'pink',
        title: 'Canvas polish',
        desc: 'Tweak text, objects, and framing before you ship.',
        image: '/images/marketing/feature-canvas.png',
    },
    {
        tone: 'blue',
        title: 'Campaign export',
        desc: 'Download a full set ready to schedule or send to clients.',
        image: '/images/marketing/feature-export.png',
    },
] as const;

const HOW_STEPS = [
    {
        id: 'upload',
        label: 'Upload references',
        title: 'Show us the brand',
        desc: 'Add 5–10 images that look like the client (or your own brand). We pull color, composition, and typography automatically.',
        image: '/images/landing/upload.png',
    },
    {
        id: 'csv',
        label: 'Drop in CSV',
        title: 'Paste your content calendar',
        desc: 'Title, description, format. Each row becomes one visual. No briefs, no waiting on a designer queue.',
        image: '/images/landing/csv.png',
    },
    {
        id: 'generate',
        label: 'Generate & refine',
        title: 'Ship, then tweak',
        desc: 'Review the batch in minutes. Fine-tune anything in Canvas before you post or send to the client.',
        image: '/images/landing/generate.png',
    },
];

const TESTIMONIALS = [
    {
        quote: 'I used to sit on a designer queue for a week. Now I turn a content calendar into a full Instagram set in an afternoon - and it still looks like the brand.',
        name: 'Priya Nair',
        role: 'Social media manager',
        initials: 'PN',
        tone: 'pink',
    },
    {
        quote: 'As a freelancer I was the bottleneck. SnapDraft lets me generate a client batch, tweak headlines in Canvas, and deliver the same day.',
        name: 'Jordan Blake',
        role: 'Freelance content designer',
        initials: 'JB',
        tone: 'blue',
    },
    {
        quote: 'We run multiple brands. Brand DNA keeps each account looking distinct, and CSV batches mean we stop chasing designers for every post.',
        name: 'Maya Chen',
        role: 'Agency account lead',
        initials: 'MC',
        tone: 'coral',
    },
    {
        quote: 'Client feedback used to mean another round. Now I swap a logo, fix a line, export. Done. Revision loops got cut in half.',
        name: 'Sam Ortiz',
        role: 'Social media freelancer',
        initials: 'SO',
        tone: 'ink',
    },
    {
        quote: 'Consistency across 40 posts used to be impossible for our bench. SnapDraft is how we keep calendars full without adding design headcount.',
        name: 'Alex Rivera',
        role: 'Agency founder',
        initials: 'AR',
        tone: 'orange',
    },
    {
        quote: 'It feels like a production pipeline, not a prompt toy. We plan in the sheet, review finished work, and only open Canvas when something needs a tweak.',
        name: 'Chris Adeyemi',
        role: 'In-house social lead',
        initials: 'CA',
        tone: 'green',
    },
];

const FALLBACK_PLANS: MarketingPlan[] = [
    {
        id: 'starter',
        name: 'Starter',
        subtitle: 'For freelancers and solo social managers',
        price: 29,
        yearly_price: 24,
        yearly_total: 288,
        currency: 'USD',
        popular: false,
        features: [
            'Brand DNA extraction',
            'Batch generation from CSV',
            'Canvas Editor for last-mile tweaks',
            'Email support',
        ],
    },
    {
        id: 'pro',
        name: 'Professional',
        subtitle: 'For managers shipping weekly calendars',
        price: 79,
        yearly_price: 66,
        yearly_total: 792,
        currency: 'USD',
        popular: true,
        features: [
            'Everything in Starter',
            'Higher monthly credits',
            'Advanced Canvas Editor',
            'Priority processing',
        ],
    },
    {
        id: 'business',
        name: 'Enterprise',
        subtitle: 'For agencies running multiple brands',
        price: 199,
        yearly_price: 166,
        yearly_total: 1992,
        currency: 'USD',
        popular: false,
        features: [
            'Everything in Professional',
            'Larger CSV batches',
            'Higher volume credits',
            'Priority support',
        ],
    },
];

const PLAN_ICONS = [Smartphone, Sparkles, Layers] as const;

export default function HomePage({
    plans = [],
    posts = [],
}: {
    plans?: MarketingPlan[];
    posts?: BlogPostSummary[];
}) {
    const [activeFeature, setActiveFeature] = useState(0);
    const [activeHow, setActiveHow] = useState(0);
    const [pricingPeriod, setPricingPeriod] = useState<'monthly' | 'yearly'>(
        'yearly',
    );

    const displayPlans =
        plans.length > 0 ? plans.slice(0, 3) : FALLBACK_PLANS;
    const feature = FEATURE_TABS[activeFeature];
    const FeatureIcon = feature.icon;
    const how = HOW_STEPS[activeHow];

    return (
        <MarketingLayout
            title="SnapDraft - On-brand visuals without waiting on designers"
            description="For social media managers, freelancers, and agencies. Upload brand references, drop in your content calendar, and get on-brand visuals in minutes. Then tweak them until they fit."
        >
            {/* Hero */}
            <section className="sd-hero-shell sd-hero-shell-centered">
                <div className="sd-hero sd-hero-centered">
                    <div className="sd-hero-floaters" aria-hidden="true">
                        <div className="sd-hero-float sd-hero-float-tl">
                            <img
                                src="/images/marketing/poster1.jpg"
                                alt=""
                            />
                        </div>
                        <div className="sd-hero-float sd-hero-float-tr">
                            <img
                                src="/images/marketing/poster2.jpg"
                                alt=""
                            />
                        </div>
                        <div className="sd-hero-float sd-hero-float-bl">
                            <img
                                src="/images/marketing/poster3.jpg"
                                alt=""
                            />
                        </div>
                        <div className="sd-hero-float sd-hero-float-br">
                            <img
                                src="/images/marketing/poster4.jpg"
                                alt=""
                            />
                        </div>
                    </div>

                    <div className="sd-hero-center">
                        <Reveal>
                            <h1>
                                Stop waiting on designers.
                                Ship the week&apos;s posts today.
                            </h1>
                        </Reveal>
                        <Reveal delay={80}>
                            <p className="sd-hero-desc">
                                Built for social media managers, freelancers,
                                and agencies. Turn brand references and a
                                content spreadsheet into a ready-to-post batch.
                                Then tweak anything until it fits.
                            </p>
                        </Reveal>
                        <Reveal delay={140}>
                            <div className="sd-hero-cta">
                                <Link
                                    href={register().url}
                                    className="sd-btn-hero sd-btn-hero-pill"
                                >
                                    Generate your next batch
                                    <ArrowRight size={18} strokeWidth={2.5} />
                                </Link>
                            </div>
                        </Reveal>
                    </div>
                </div>
            </section>

            {/* Trust */}
            <div className="sd-trust-bar">
                <div className="sd-trust-bar-inner">
                    <div className="sd-trust-bar-label">
                        Built for people who ship weekly
                    </div>
                    Social media managers · freelancers · agencies
                </div>
            </div>

            {/* About + Stats */}
            <section className="sd-about-section">
                <Reveal>
                    <div className="sd-about-intro">
                        <div className="sd-sec-eyebrow">The bottleneck</div>
                        <h2 className="sd-about-title">
                            Your calendar moves faster than the design queue.
                            SnapDraft closes that gap. On-brand visuals in
                            minutes, with room to tweak before you publish.
                        </h2>
                    </div>
                </Reveal>
                <div className="sd-about-grid">
                    {[
                        {
                            val: '1 row',
                            label: 'One spreadsheet row → one post-ready visual',
                        },
                        {
                            val: 'Minutes',
                            label: 'From calendar upload to a full batch',
                        },
                        {
                            val: 'On-brand',
                            label: 'Stays consistent once Brand DNA is set',
                        },
                        {
                            val: 'You',
                            label: 'Handle last-mile tweaks - no designer wait',
                        },
                    ].flatMap((s, i) => {
                        const nodes = [];
                        if (i > 0) {
                            nodes.push(
                                <div
                                    className="sd-about-divider"
                                    aria-hidden="true"
                                    key={`div-${s.label}`}
                                >
                                    <div className="sd-about-divider-space"/>
                                    <div className="sd-about-divider-center" />
                                    <div className="sd-about-divider-space"/>
                                    
                                </div>,
                            );
                        }
                        nodes.push(
                            <article className="sd-about-card" key={s.label}>
                                <p className="sd-about-card-text">{s.label}</p>
                                <div className="sd-about-card-stat">{s.val}</div>
                            </article>,
                        );
                        return nodes;
                    })}
                </div>
            </section>

            {/* Core features */}
            <section className="sd-feat-section">
                <Reveal>
                    <div className="sd-feat-head">
                        <div className="sd-sec-eyebrow">How it works</div>
                        <h2 className="sd-sec-title">
                            From brief to batch without the wait
                        </h2>
                        <p className="sd-sec-sub">
                            Learn the brand, generate from your spreadsheet,
                            tweak in Canvas, and export. Built for calendar
                            speed, not design tickets.
                        </p>
                    </div>
                </Reveal>

                <div className="sd-feat-tab-list" role="tablist">
                    {FEATURE_TABS.map((tab, i) => (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={i === activeFeature}
                            className={
                                i === activeFeature
                                    ? `sd-feat-tab active sd-feat-tab-${tab.tone}`
                                    : 'sd-feat-tab'
                            }
                            onClick={() => setActiveFeature(i)}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div
                    className={`sd-feat-panel sd-feat-panel-${feature.tone}`}
                    role="tabpanel"
                >
                    <div className="sd-feat-panel-copy">
                        <span className="sd-feat-panel-ico">
                            <FeatureIcon size={22} strokeWidth={2} />
                        </span>
                        <div className="sd-feat-panel-text">
                            <h3>{feature.title}</h3>
                            <p>{feature.desc}</p>
                        </div>
                    </div>
                    <div className="sd-feat-panel-media">
                        <img src={feature.image} alt="" />
                    </div>
                </div>
            </section>

            {/* Color feature cards */}
            <div className="sd-color-cards">
                {COLOR_CARDS.map((card, i) => (
                    <Reveal key={card.title} delay={i * 60}>
                        <div className={`sd-color-card sd-color-card-${card.tone}`}>
                            <div className="sd-color-card-media">
                                <img src={card.image} alt="" />
                            </div>
                            <div>
                                <h3>{card.title}</h3>
                                <p>{card.desc}</p>
                            </div>
                        </div>
                    </Reveal>
                ))}
            </div>


            {/* Pricing */}
            <section className="sd-pricing-section">
                <Reveal>
                    <div className="sd-sec-head sd-pricing-head">
                        <div className="sd-sec-eyebrow">Pricing</div>
                        <h2 className="sd-sec-title">
                            Plans for solo operators and agencies
                        </h2>
                        <p className="sd-sec-sub">
                            Brand DNA, batch generation, and Canvas tweaks on
                            every plan. Priced for freelancers, social managers,
                            and multi-brand teams.
                        </p>
                        <div className="sd-pricing-toggle">
                            <button
                                type="button"
                                className={
                                    pricingPeriod === 'monthly' ? 'active' : ''
                                }
                                onClick={() => setPricingPeriod('monthly')}
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                className={
                                    pricingPeriod === 'yearly' ? 'active' : ''
                                }
                                onClick={() => setPricingPeriod('yearly')}
                            >
                                Yearly
                                <span className="sd-pricing-save">
                                    2 months free
                                </span>
                            </button>
                        </div>
                    </div>
                </Reveal>
                <div className="sd-pricing-shell">
                    <div className="sd-pricing-grid">
                        {displayPlans.map((plan, i) => {
                            const symbol =
                                CURRENCY_SYMBOLS[plan.currency] ??
                                plan.currency;
                            const Icon = PLAN_ICONS[i % PLAN_ICONS.length];
                            const hasPopular = displayPlans.some(
                                (p) => p.popular,
                            );
                            const featured = hasPopular
                                ? plan.popular
                                : i === 1;
                            const monthly =
                                pricingPeriod === 'yearly'
                                    ? plan.yearly_price
                                    : plan.price;
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
                                            <Icon size={16} strokeWidth={2.25} />
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
                                        {pricingPeriod === 'yearly'
                                            ? `Billed ${symbol}${plan.yearly_total % 1 === 0 ? plan.yearly_total : plan.yearly_total.toFixed(2)} yearly`
                                            : 'Billed monthly'}
                                    </div>
                                    <ul className="sd-price-features">
                                        {plan.features.map((f) => (
                                            <li key={f}>
                                                <span className="sd-price-check">
                                                    <Check
                                                        size={12}
                                                        strokeWidth={3}
                                                    />
                                                </span>
                                                {f}
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
                <div className="sd-cta-row" style={{ marginTop: 28 }}>
                    <Link href="/pricing" className="sd-btn-hero-ghost">
                        Compare all plans
                        <ArrowRight size={16} />
                    </Link>
                </div>
            </section>

            {/* Testimonials */}
            <section className="sd-quotes">
                <Reveal>
                    <div className="sd-sec-head sd-quotes-head">
                        <div className="sd-sec-eyebrow">Testimonials</div>
                        <h2 className="sd-sec-title">
                            Less queue time. More posts shipped.
                        </h2>
                    </div>
                </Reveal>
                <div className="sd-quotes-grid">
                    {TESTIMONIALS.map((t) => (
                        <article className="sd-quote-card" key={t.name}>
                            <div className="sd-quote-mark" aria-hidden="true">
                                &rdquo;
                            </div>
                            <p>{t.quote}</p>
                            <div className="sd-quote-author">
                                <div
                                    className={`sd-quote-avatar sd-quote-avatar-${t.tone}`}
                                    aria-hidden="true"
                                >
                                    {t.initials}
                                </div>
                                <div>
                                    <strong>{t.name}</strong>
                                    <span>{t.role}</span>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </section>

            {/* Blog */}
            <section className="sd-home-blog">
                <div className="sd-home-blog-head">
                    <div>
                        <div className="sd-sec-eyebrow">From the blog</div>
                        <h2 className="sd-sec-title">
                            Workflow tips for people who publish weekly
                        </h2>
                    </div>
                    <Link href="/blog" className="sd-btn-sm sd-home-blog-cta">
                        Read the blog
                    </Link>
                </div>
                {posts.length === 0 ? (
                    <p className="sd-pricing-note">
                        New posts coming soon.{' '}
                        <Link href="/blog">Visit the blog</Link>.
                    </p>
                ) : (
                    <div className="sd-blog-grid sd-home-blog-grid">
                        {posts.map((post, i) => (
                            <Reveal key={post.slug} delay={i * 80}>
                                <Link
                                    href={`/blog/${post.slug}`}
                                    className="sd-blog-card sd-home-blog-card"
                                >
                                    {post.cover && (
                                        <div className="sd-blog-card-cover">
                                            <img
                                                src={post.cover}
                                                alt=""
                                                loading="lazy"
                                            />
                                        </div>
                                    )}
                                    <div className="sd-blog-card-body">
                                        <span className="sd-blog-card-tag">
                                            {post.category ?? 'News'}
                                        </span>
                                        <h3>{post.title}</h3>
                                        {post.date_formatted && (
                                            <div className="sd-blog-card-date">
                                                {post.date_formatted}
                                            </div>
                                        )}
                                    </div>
                                </Link>
                            </Reveal>
                        ))}
                    </div>
                )}
            </section>
        </MarketingLayout>
    );
}
