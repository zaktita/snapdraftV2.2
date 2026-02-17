import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Crown,
    CreditCard,
    Calendar,
    AlertCircle,
    CheckCircle,
    ExternalLink,
    XCircle,
    Clock,
    Sparkles,
    TrendingUp,
    ArrowRight
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';

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
    const handleCancelSubscription = () => {
        if (confirm('Are you sure you want to cancel your subscription? You will lose access at the end of your billing period.')) {
            router.post('/subscription/downgrade');
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status.toLowerCase()) {
            case 'active':
                return (
                    <Badge className="bg-green-500/10 text-green-700 border-green-500/20">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Active
                    </Badge>
                );
            case 'cancelled':
            case 'canceled':
                return (
                    <Badge className="bg-yellow-500/10 text-yellow-700 border-yellow-500/20">
                        <AlertCircle className="h-3 w-3 mr-1" />
                        Cancelled
                    </Badge>
                );
            case 'expired':
                return (
                    <Badge className="bg-red-500/10 text-red-700 border-red-500/20">
                        <XCircle className="h-3 w-3 mr-1" />
                        Expired
                    </Badge>
                );
            case 'trial':
                return (
                    <Badge className="bg-blue-500/10 text-blue-700 border-blue-500/20">
                        <Clock className="h-3 w-3 mr-1" />
                        Trial
                    </Badge>
                );
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const creditsPercentage = subscription && subscription.credits_total > 0
        ? Math.round((subscription.credits_remaining / subscription.credits_total) * 100)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Subscription" />
            
            <div className="p-6 md:p-8 max-w-[1200px] mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary/10 to-primary/5">
                                <Crown className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">Manage Subscription</h1>
                                <p className="text-sm text-muted-foreground mt-0.5">
                                    View and manage your subscription details
                                </p>
                            </div>
                        </div>
                        <Link href="/subscription/plans">
                            <Button variant="outline" className="gap-2">
                                <TrendingUp className="h-4 w-4" />
                                View All Plans
                            </Button>
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
                                        <div className="text-2xl font-bold capitalize">{subscription.tier}</div>
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
                                            className="gap-2 justify-start text-red-600 hover:text-red-700"
                                            onClick={handleCancelSubscription}
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
                    /* No Active Subscription */
                    <Card>
                        <CardContent className="pt-12 pb-12 text-center">
                            <div className="flex justify-center mb-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                    <Crown className="h-8 w-8 text-muted-foreground" />
                                </div>
                            </div>
                            <h3 className="text-xl font-semibold mb-2">No Active Subscription</h3>
                            <p className="text-muted-foreground mb-6">
                                You don't have an active subscription. Choose a plan to get started with AI-powered visual content generation.
                            </p>
                            <Button asChild size="lg" className="gap-2">
                                <Link href="/subscription/plans">
                                    View Plans
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
