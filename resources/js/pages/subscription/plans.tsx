import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    KeyRound,
    Rocket,
    Scaling,
    TrendingUp,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subscription',
        href: '/subscription/plans',
    },
    {
        title: 'Plans',
        href: '/subscription/plans',
    },
];

const CURRENCY_SYMBOLS: Record<string, string> = {
    EUR: '€',
    USD: '$',
    GBP: '£',
};

const PLAN_ICONS = [Rocket, TrendingUp, Scaling] as const;

interface Plan {
    id: string;
    name: string;
    subtitle: string;
    price: number;
    yearly_price: number;
    yearly_total?: number;
    currency: string;
    credits: number;
    max_projects: number;
    csv_max_rows: number;
    popular: boolean;
    features: string[];
    bestFor: string[];
}

interface SubscriptionPlansProps {
    plans: Plan[];
    current_tier: string | null;
    credits_remaining: number;
    credits_total: number;
    remaining_project_slots: number;
}

function formatMoney(value: number) {
    return value % 1 === 0 ? String(value) : value.toFixed(2);
}

export default function SubscriptionPlans({
    plans,
    current_tier,
    credits_remaining,
    credits_total,
    remaining_project_slots,
}: SubscriptionPlansProps) {
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>(
        'yearly',
    );
    const [showBetaCode, setShowBetaCode] = useState(false);
    const inviteForm = useForm({ code: '' });

    const redeemInvite = (e: FormEvent) => {
        e.preventDefault();
        inviteForm.post('/invite/redeem', { preserveScroll: true });
    };

    const handleUpgrade = (planId: string) => {
        router.post('/subscription/upgrade', {
            tier: planId,
            billing_period: billingPeriod,
        });
    };

    const creditsPercentage =
        credits_total > 0
            ? Math.round((credits_remaining / credits_total) * 100)
            : 0;
    const hasPopular = plans.some((p) => p.popular);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plans" />

            <div className="sd-app-pricing">
                <section className="sd-pricing-section sd-app-pricing-section">
                    <div className="sd-sec-head sd-pricing-head">
                        <div className="sd-sec-eyebrow">Pricing</div>
                        <h1 className="sd-sec-title">
                            Plans for solo operators and agencies
                        </h1>
                        <p className="sd-sec-sub">
                            Brand DNA, batch generation, and Canvas on every
                            plan. Upgrade anytime - credits and project limits
                            update with your billing cycle.
                        </p>
                        <div className="sd-pricing-toggle">
                            <button
                                type="button"
                                className={
                                    billingPeriod === 'monthly' ? 'active' : ''
                                }
                                onClick={() => setBillingPeriod('monthly')}
                            >
                                Monthly
                            </button>
                            <button
                                type="button"
                                className={
                                    billingPeriod === 'yearly' ? 'active' : ''
                                }
                                onClick={() => setBillingPeriod('yearly')}
                            >
                                Yearly
                                <span className="sd-pricing-save">
                                    2 months free
                                </span>
                            </button>
                        </div>
                    </div>

                    {current_tier && (
                        <div className="sd-app-plan-status">
                            <div className="sd-app-plan-status-item">
                                <span className="sd-app-plan-status-label">
                                    Current plan
                                </span>
                                <strong className="sd-app-plan-status-value capitalize">
                                    {current_tier}
                                </strong>
                            </div>
                            <div className="sd-app-plan-status-item">
                                <span className="sd-app-plan-status-label">
                                    Credits remaining
                                </span>
                                <strong className="sd-app-plan-status-value">
                                    {credits_remaining}
                                    <span>
                                        {' '}
                                        / {credits_total}
                                    </span>
                                </strong>
                                <div
                                    className="sd-app-plan-status-bar"
                                    aria-hidden="true"
                                >
                                    <span
                                        style={{
                                            width: `${Math.min(100, Math.max(0, creditsPercentage))}%`,
                                        }}
                                    />
                                </div>
                            </div>
                            <div className="sd-app-plan-status-item">
                                <span className="sd-app-plan-status-label">
                                    Project slots
                                </span>
                                <strong className="sd-app-plan-status-value">
                                    {remaining_project_slots}
                                    <span> remaining</span>
                                </strong>
                            </div>
                        </div>
                    )}

                    {plans.length === 0 ? (
                        <p className="sd-pricing-note">
                            Plans are being finalized — contact support for
                            current pricing.
                        </p>
                    ) : (
                        <div className="sd-pricing-shell">
                            <div className="sd-pricing-grid">
                                {plans.map((plan, i) => {
                                    const symbol =
                                        CURRENCY_SYMBOLS[plan.currency] ??
                                        plan.currency;
                                    const Icon =
                                        PLAN_ICONS[i % PLAN_ICONS.length];
                                    const featured = hasPopular
                                        ? plan.popular
                                        : i === 1;
                                    const isCurrent =
                                        current_tier?.toLowerCase() ===
                                        plan.id.toLowerCase();
                                    const monthly =
                                        billingPeriod === 'yearly'
                                            ? plan.yearly_price
                                            : plan.price;
                                    const yearlyTotal =
                                        plan.yearly_total ?? plan.price * 10;

                                    const featureList =
                                        plan.features.length > 0
                                            ? plan.features
                                            : [
                                                  `${plan.credits} credits / month`,
                                                  `Up to ${plan.max_projects} projects`,
                                                  `CSV batches up to ${plan.csv_max_rows} rows`,
                                              ];

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
                                                    {formatMoney(monthly)}
                                                </strong>
                                                <span>/month</span>
                                            </div>
                                            <p className="sd-price-subtitle">
                                                {plan.subtitle}
                                            </p>
                                            <div className="sd-price-billed">
                                                {billingPeriod === 'yearly'
                                                    ? `Billed ${symbol}${formatMoney(yearlyTotal)} yearly`
                                                    : 'Billed monthly'}
                                            </div>
                                            <ul className="sd-price-features">
                                                {featureList.map((feature) => (
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
                                            <button
                                                type="button"
                                                className={
                                                    isCurrent
                                                        ? 'sd-btn-price sd-btn-price-current'
                                                        : 'sd-btn-price'
                                                }
                                                disabled={isCurrent}
                                                onClick={() =>
                                                    handleUpgrade(plan.id)
                                                }
                                            >
                                                {isCurrent
                                                    ? 'Current plan'
                                                    : `Get ${plan.name}`}
                                            </button>
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

                    <div className="sd-app-beta-code">
                        {!showBetaCode ? (
                            <button
                                type="button"
                                className="sd-app-beta-code-toggle"
                                onClick={() => setShowBetaCode(true)}
                            >
                                Have a beta code?
                            </button>
                        ) : (
                            <form
                                onSubmit={redeemInvite}
                                className="sd-app-beta-code-form"
                            >
                                <div className="sd-app-beta-code-field">
                                    <KeyRound size={16} strokeWidth={2.25} />
                                    <input
                                        placeholder="Enter invite code"
                                        value={inviteForm.data.code}
                                        onChange={(e) =>
                                            inviteForm.setData(
                                                'code',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="off"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="sd-btn-price"
                                    disabled={inviteForm.processing}
                                >
                                    Redeem
                                </button>
                            </form>
                        )}
                        {inviteForm.errors.code && (
                            <p className="sd-app-beta-code-error">
                                {inviteForm.errors.code}
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
