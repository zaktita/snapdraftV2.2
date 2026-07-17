import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { ConfirmDialog } from '@/components/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Crown,
    CreditCard,
    Calendar,
    AlertCircle,
    ExternalLink,
    Sparkles,
    TrendingUp,
    ArrowRight,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subscription',
        href: '/subscription/plans',
    },
    {
        title: 'Manage',
        href: '/subscription/portal',
    },
];

interface Subscription {
    id: string;
    tier: string;
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
    customer_portal_url: string | null;
    update_payment_url: string | null;
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

export default function SubscriptionPortal({ subscription, subscription_history }: SubscriptionPortalProps) {
    const [cancelOpen, setCancelOpen] = useState(false);

    const handleCancelSubscription = () => {
        router.post('/subscription/downgrade', {}, {
            onFinish: () => setCancelOpen(false),
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status.toLowerCase()) {
            case 'active':
                return <Badge variant="yellow">Active</Badge>;
            case 'cancelled':
            case 'canceled':
                return <Badge variant="secondary">Cancelled</Badge>;
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
    };

    const creditsPercentage = subscription && subscription.credits_total > 0
        ? Math.round((subscription.credits_remaining / subscription.credits_total) * 100)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Subscription" />
            
            <div className="sd-app-pricing">
                <section className="sd-pricing-section sd-app-pricing-section">
                    <div className="sd-sec-head sd-pricing-head">
                        <div className="sd-sec-eyebrow">Subscription</div>
                        <h1 className="sd-sec-title">Manage your plan</h1>
                        <p className="sd-sec-sub">
                            View billing details, credits, and renewal settings
                            for your SnapDraft subscription.
                        </p>
                        <div className="sd-cta-row" style={{ marginTop: 20 }}>
                            <Link href="/subscription/plans" className="sd-btn-sm">
                                View all plans
                                <TrendingUp size={14} />
                            </Link>
                        </div>
                    </div>

                {subscription ? (
                    <>
                        {/* Current Subscription Overview */}
                        <div className="grid gap-6 md:grid-cols-2 mb-6">
                            {/* Plan Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        <span>Current Plan</span>
                                        {getStatusBadge(subscription.status)}
                                    </CardTitle>
                                    <CardDescription>Your active subscription details</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Plan</div>
                                        <div className="font-display text-3xl font-normal capitalize tracking-tight">
                                            {subscription.tier}
                                        </div>
                                    </div>
                                    
                                    <Separator />
                                    
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <div className="text-sm text-muted-foreground mb-1">Billing</div>
                                            <div className="font-medium capitalize">{subscription.billing_period}</div>
                                        </div>
                                        <div>
                                            <div className="text-sm text-muted-foreground mb-1">Price</div>
                                            <div className="font-medium">
                                                {subscription.currency} {subscription.price}
                                                <span className="text-sm text-muted-foreground">
                                                    /{subscription.billing_period === 'yearly' ? 'year' : 'month'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Started</div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {format(new Date(subscription.started_at), 'MMM dd, yyyy')}
                                            </span>
                                        </div>
                                    </div>

                                    {subscription.on_trial && (
                                        <div className="rounded-lg bg-blue-500/10 p-3">
                                            <div className="flex items-center gap-2 text-sm font-medium text-blue-700">
                                                <Clock className="h-4 w-4" />
                                                Trial Period Active
                                            </div>
                                        </div>
                                    )}

                                    {subscription.renews_at && subscription.auto_renew && (
                                        <div>
                                            <div className="text-sm text-muted-foreground mb-1">Next Renewal</div>
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span className="font-medium">
                                                    {format(new Date(subscription.renews_at), 'MMM dd, yyyy')}
                                                </span>
                                            </div>
                                        </div>
                                    )}

                                    {subscription.cancelled_at && subscription.ends_at && (
                                        <div className="rounded-lg bg-yellow-500/10 p-3">
                                            <div className="text-sm font-medium text-yellow-700 mb-1">
                                                Subscription Cancelled
                                            </div>
                                            <div className="text-xs text-yellow-600">
                                                Access until {format(new Date(subscription.ends_at), 'MMM dd, yyyy')}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Credits Usage */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Sparkles className="h-5 w-5 text-yellow-500" />
                                        Credits Usage
                                    </CardTitle>
                                    <CardDescription>Your monthly credit allocation</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <div className="flex items-baseline gap-2 mb-3">
                                            <span className="text-4xl font-bold">{subscription.credits_remaining}</span>
                                            <span className="text-lg text-muted-foreground">/ {subscription.credits_total}</span>
                                        </div>
                                        <Progress value={creditsPercentage} className="h-3" />
                                        <div className="flex justify-between text-xs text-muted-foreground mt-2">
                                            <span>{creditsPercentage}% remaining</span>
                                            <span>{subscription.credits_total - subscription.credits_remaining} used</span>
                                        </div>
                                    </div>

                                    <Separator />

                                    {creditsPercentage < 20 && (
                                        <div className="rounded-lg bg-orange-500/10 p-3">
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="h-4 w-4 text-orange-600 mt-0.5" />
                                                <div>
                                                    <div className="text-sm font-medium text-orange-700">
                                                        Running Low on Credits
                                                    </div>
                                                    <p className="text-xs text-orange-600 mt-1">
                                                        Consider upgrading your plan or purchasing additional credits
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-2 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Credits reset</span>
                                            <span className="font-medium">
                                                {subscription.renews_at 
                                                    ? format(new Date(subscription.renews_at), 'MMM dd') 
                                                    : 'N/A'}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter>
                                    <Button variant="outline" className="w-full gap-2" asChild>
                                        <Link href="/subscription/plans">
                                            <TrendingUp className="h-4 w-4" />
                                            Upgrade Plan
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>

                        {/* Quick Actions */}
                        <Card className="mb-6">
                            <CardHeader>
                                <CardTitle>Manage Your Subscription</CardTitle>
                                <CardDescription>Update payment method, billing details, or cancel subscription</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 md:grid-cols-3">
                                    {subscription.customer_portal_url && (
                                        <Button 
                                            variant="outline" 
                                            className="gap-2 justify-start"
                                            asChild
                                        >
                                            <a href={subscription.customer_portal_url} target="_blank" rel="noopener noreferrer">
                                                <CreditCard className="h-4 w-4" />
                                                Customer Portal
                                                <ExternalLink className="h-3 w-3 ml-auto" />
                                            </a>
                                        </Button>
                                    )}

                                    {subscription.update_payment_url && (
                                        <Button 
                                            variant="outline" 
                                            className="gap-2 justify-start"
                                            asChild
                                        >
                                            <a href={subscription.update_payment_url} target="_blank" rel="noopener noreferrer">
                                                <CreditCard className="h-4 w-4" />
                                                Update Payment
                                                <ExternalLink className="h-3 w-3 ml-auto" />
                                            </a>
                                        </Button>
                                    )}

                                    {subscription.auto_renew && !subscription.cancelled_at && (
                                        <Button 
                                            variant="outline" 
                                            className="gap-2 justify-start text-destructive hover:text-destructive"
                                            onClick={() => setCancelOpen(true)}
                                        >
                                            <XCircle className="h-4 w-4" />
                                            Cancel Subscription
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Subscription History */}
                        {subscription_history.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Subscription History</CardTitle>
                                    <CardDescription>Your past and current subscriptions</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {subscription_history.map((hist, index) => (
                                            <div 
                                                key={hist.id}
                                                className={cn(
                                                    "flex items-center justify-between p-3 rounded-lg border",
                                                    index === 0 && "border-primary bg-primary/5"
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted">
                                                        <Crown className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <div className="font-medium capitalize">{hist.tier}</div>
                                                        <div className="text-xs text-muted-foreground capitalize">
                                                            {hist.billing_period}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="text-right text-sm">
                                                        <div className="text-muted-foreground">
                                                            {format(new Date(hist.started_at), 'MMM dd, yyyy')}
                                                        </div>
                                                        {hist.ended_at && (
                                                            <div className="text-xs text-muted-foreground">
                                                                to {format(new Date(hist.ended_at), 'MMM dd, yyyy')}
                                                            </div>
                                                        )}
                                                    </div>
                                                    {getStatusBadge(hist.status)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </>
                ) : (
                    <div className="sd-pricing-shell" style={{ marginTop: 28, padding: 48, textAlign: 'center' }}>
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--sd-or-pale)] text-primary">
                                <Crown className="h-7 w-7" />
                            </div>
                            <h3 className="font-display text-2xl font-normal tracking-tight mb-2">
                                No active subscription
                            </h3>
                            <p className="sd-sec-sub" style={{ marginBottom: 24, maxWidth: 420, marginLeft: 'auto', marginRight: 'auto' }}>
                                Choose a plan to unlock Brand DNA, batch generation, and Canvas polish.
                            </p>
                            <Link href="/subscription/plans" className="sd-btn-price" style={{ width: 'auto', display: 'inline-flex', paddingInline: 28 }}>
                                View plans
                                <ArrowRight size={16} />
                            </Link>
                    </div>
                )}
                </section>
            </div>

            <ConfirmDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                title="Cancel subscription?"
                description="You will keep access until the end of your current billing period. After that, your plan benefits and remaining credits will stop renewing."
                confirmLabel="Cancel subscription"
                cancelLabel="Keep plan"
                variant="destructive"
                onConfirm={handleCancelSubscription}
            />
        </AppLayout>
    );
}
