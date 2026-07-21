import { ConfirmDialog } from '@/components/confirm-dialog';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    ArrowRight,
    CreditCard,
    ExternalLink,
    FileText,
    RefreshCw,
    RotateCcw,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Subscription', href: '/settings/subscription' },
];

interface Subscription {
    id: string;
    tier: string;
    plan_name?: string | null;
    billing_period: 'monthly' | 'yearly';
    status: string;
    price: number;
    currency: string;
    credits_remaining: number;
    credits_total: number;
    started_at: string;
    renews_at: string | null;
    ends_at: string | null;
    cancelled_at: string | null;
    auto_renew: boolean;
    on_grace_period: boolean;
    customer_portal_url: string | null;
    update_payment_url: string | null;
    update_subscription_url: string | null;
    on_trial: boolean;
}

interface SubscriptionHistory {
    id: string;
    tier: string;
    status: string;
    billing_period: string;
    started_at: string;
    ended_at: string | null;
}

interface SubscriptionPortalProps {
    subscription: Subscription | null;
    subscription_history: SubscriptionHistory[];
}

function StatusBadge({ status }: { status: string }) {
    switch (status.toLowerCase()) {
        case 'active':
            return <Badge variant="default">Active</Badge>;
        case 'cancelled':
        case 'canceled':
            return <Badge variant="secondary">Cancelling</Badge>;
        case 'expired':
            return <Badge variant="destructive">Expired</Badge>;
        case 'trial':
        case 'trialing':
            return <Badge variant="blue">Trial</Badge>;
        default:
            return (
                <Badge variant="outline" className="capitalize">
                    {status}
                </Badge>
            );
    }
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-right text-sm font-medium text-foreground">{children}</dd>
        </div>
    );
}

export default function SubscriptionPortal({ subscription, subscription_history }: SubscriptionPortalProps) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [resumeOpen, setResumeOpen] = useState(false);

    const handleCancelSubscription = () => {
        router.post('/subscription/downgrade', {}, {
            onFinish: () => setCancelOpen(false),
        });
    };

    const handleResumeSubscription = () => {
        router.post('/subscription/resume', {}, {
            onFinish: () => setResumeOpen(false),
        });
    };

    const creditsPercentage = subscription && subscription.credits_total > 0
        ? Math.round((subscription.credits_remaining / subscription.credits_total) * 100)
        : 0;

    const cancelDescription = subscription?.billing_period === 'yearly'
        ? `Your yearly plan stays active until ${format(new Date(subscription.ends_at ?? subscription.renews_at ?? new Date()), 'MMM dd, yyyy')}. You won’t be charged again. Unused months aren’t refunded.`
        : `You keep access until the end of your current billing period${subscription?.renews_at ? ` (${format(new Date(subscription.renews_at), 'MMM dd, yyyy')})` : ''}. After that, credits stop renewing.`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscription" />

            <SettingsLayout wide>
                {/* Current plan */}
                <div className="space-y-6">
                    <HeadingSmall
                        title="Subscription"
                        description="Manage your plan, credits, payment method, and renewals"
                    />

                    {subscription ? (
                        <div className="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-xs">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">Current plan</p>
                                    <h4 className="mt-1 font-display text-2xl font-normal capitalize tracking-tight text-foreground">
                                        {subscription.plan_name ?? subscription.tier}
                                    </h4>
                                </div>
                                <StatusBadge status={subscription.status} />
                            </div>

                            <dl className="divide-y divide-border border-t border-border">
                                <DetailRow label="Billing">
                                    <span className="capitalize">{subscription.billing_period}</span>
                                </DetailRow>
                                <DetailRow label="Price">
                                    {subscription.currency} {Number(subscription.price).toFixed(2)}
                                    <span className="font-normal text-muted-foreground">
                                        /{subscription.billing_period === 'yearly' ? 'year' : 'month'}
                                    </span>
                                </DetailRow>
                                <DetailRow label="Started">
                                    {format(new Date(subscription.started_at), 'MMM dd, yyyy')}
                                </DetailRow>
                                {subscription.renews_at && subscription.auto_renew && (
                                    <DetailRow label="Next renewal">
                                        {format(new Date(subscription.renews_at), 'MMM dd, yyyy')}
                                    </DetailRow>
                                )}
                                {subscription.on_grace_period && subscription.ends_at && (
                                    <DetailRow label="Access until">
                                        {format(new Date(subscription.ends_at), 'MMM dd, yyyy')}
                                    </DetailRow>
                                )}
                            </dl>

                            {subscription.on_grace_period && subscription.ends_at && (
                                <div className="rounded-xl border border-[var(--sd-or-mid)] bg-[var(--sd-or-pale)] px-4 py-3">
                                    <p className="text-sm font-medium text-[var(--sd-or-soft)]">
                                        Subscription cancelled
                                    </p>
                                    <p className="mt-0.5 text-sm text-[var(--sd-or-soft)]/80">
                                        Full access until {format(new Date(subscription.ends_at), 'MMM dd, yyyy')}. You can resume anytime before then.
                                    </p>
                                </div>
                            )}

                            <div className="border-t border-border pt-4">
                                <div className="mb-3 flex items-end justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Credits remaining</p>
                                        <p className="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                                            {subscription.credits_remaining}
                                            <span className="text-base font-normal text-muted-foreground">
                                                {' '}/ {subscription.credits_total}
                                            </span>
                                        </p>
                                    </div>
                                    <p className="text-sm text-muted-foreground">{creditsPercentage}% left</p>
                                </div>
                                <Progress
                                    value={creditsPercentage}
                                    className="h-1.5 bg-muted"
                                    indicatorClassName="bg-primary"
                                />
                                {creditsPercentage < 20 && (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        Running low — consider changing your plan before the next billing cycle.
                                    </p>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-border bg-card p-6 shadow-xs sm:p-8">
                            <h4 className="font-display text-2xl font-normal tracking-tight text-foreground">
                                No active subscription
                            </h4>
                            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                Choose a plan to unlock Brand DNA, batch generation, and Canvas polish.
                            </p>
                            <Button className="mt-6" asChild>
                                <Link href="/subscription/plans">
                                    View plans
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Actions */}
                {subscription && (
                    <div className="space-y-6">
                        <HeadingSmall
                            title="Manage"
                            description="Update payment or plan in Lemon Squeezy. Cancel or resume here."
                        />

                        <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                            <div className="divide-y divide-border">
                                {subscription.update_subscription_url && (
                                    <a
                                        href={subscription.update_subscription_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 px-6 py-4 transition-colors hover:bg-muted/40"
                                    >
                                        <RefreshCw className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">Change plan or billing period</p>
                                            <p className="text-sm text-muted-foreground">Opens Lemon Squeezy</p>
                                        </div>
                                        <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </a>
                                )}

                                {subscription.update_payment_url && (
                                    <a
                                        href={subscription.update_payment_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 px-6 py-4 transition-colors hover:bg-muted/40"
                                    >
                                        <CreditCard className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">Update payment method</p>
                                            <p className="text-sm text-muted-foreground">Opens Lemon Squeezy</p>
                                        </div>
                                        <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </a>
                                )}

                                {subscription.customer_portal_url && (
                                    <a
                                        href={subscription.customer_portal_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-3 px-6 py-4 transition-colors hover:bg-muted/40"
                                    >
                                        <CreditCard className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">Customer portal</p>
                                            <p className="text-sm text-muted-foreground">Billing history and account</p>
                                        </div>
                                        <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </a>
                                )}

                                <Link
                                    href="/settings/invoices"
                                    className="flex items-center gap-3 px-6 py-4 transition-colors hover:bg-muted/40"
                                >
                                    <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">View invoices</p>
                                        <p className="text-sm text-muted-foreground">Download receipts and PDFs</p>
                                    </div>
                                    <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                </Link>

                                <Link
                                    href="/subscription/plans"
                                    className="flex items-center gap-3 px-6 py-4 transition-colors hover:bg-muted/40"
                                >
                                    <TrendingUp className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">Compare plans</p>
                                        <p className="text-sm text-muted-foreground">See all tiers and pricing</p>
                                    </div>
                                    <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </div>
                        </div>

                        {(subscription.on_grace_period || subscription.auto_renew) && (
                            <div
                                className={cn(
                                    'rounded-2xl border p-6',
                                    subscription.on_grace_period
                                        ? 'border-border bg-card shadow-xs'
                                        : 'border-red-100 bg-red-50 dark:border-red-200/10 dark:bg-red-700/10'
                                )}
                            >
                                {subscription.on_grace_period ? (
                                    <>
                                        <HeadingSmall
                                            title="Resume subscription"
                                            description="Billing will continue on your current plan with uninterrupted access."
                                        />
                                        <Button className="mt-4" onClick={() => setResumeOpen(true)}>
                                            <RotateCcw className="h-4 w-4" />
                                            Resume subscription
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                                            <p className="font-medium">Cancel subscription</p>
                                            <p className="text-sm">
                                                You’ll keep access until the end of the paid period. Unused time isn’t refunded.
                                            </p>
                                        </div>
                                        <Button
                                            variant="destructive"
                                            className="mt-4"
                                            onClick={() => setCancelOpen(true)}
                                        >
                                            <XCircle className="h-4 w-4" />
                                            Cancel subscription
                                        </Button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* History */}
                {subscription_history.length > 0 && (
                    <div className="space-y-6">
                        <HeadingSmall
                            title="History"
                            description="Past and current subscriptions on your account"
                        />

                        <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                            <ul className="divide-y divide-border">
                                {subscription_history.map((hist, index) => (
                                    <li
                                        key={hist.id}
                                        className={cn(
                                            'flex items-center justify-between gap-4 px-6 py-4',
                                            index === 0 && 'bg-[var(--sd-or-pale)]/40'
                                        )}
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium capitalize text-foreground">
                                                {hist.tier}
                                            </p>
                                            <p className="text-sm capitalize text-muted-foreground">
                                                {hist.billing_period}
                                                {' · '}
                                                {format(new Date(hist.started_at), 'MMM dd, yyyy')}
                                                {hist.ended_at && (
                                                    <> – {format(new Date(hist.ended_at), 'MMM dd, yyyy')}</>
                                                )}
                                            </p>
                                        </div>
                                        <StatusBadge status={hist.status} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                <ConfirmDialog
                    open={cancelOpen}
                    onOpenChange={setCancelOpen}
                    title="Cancel subscription?"
                    description={cancelDescription}
                    confirmLabel="Cancel subscription"
                    cancelLabel="Keep plan"
                    variant="destructive"
                    onConfirm={handleCancelSubscription}
                />

                <ConfirmDialog
                    open={resumeOpen}
                    onOpenChange={setResumeOpen}
                    title="Resume subscription?"
                    description="Billing will continue on your current plan. Your access and credits stay uninterrupted."
                    confirmLabel="Resume subscription"
                    cancelLabel="Not now"
                    onConfirm={handleResumeSubscription}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
