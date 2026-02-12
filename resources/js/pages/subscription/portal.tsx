import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    CreditCard,
    Calendar,
    Zap,
    ArrowUpCircle,
    ArrowDownCircle,
    Receipt,
    AlertCircle,
} from 'lucide-react';
import { type User } from '@/types';

interface Invoice {
    id: string;
    amount: number;
    status: string;
    date: string;
    description: string;
}

interface PortalPageProps {
    subscription: {
        tier: string;
        credits_remaining: number;
        credits_total: number;
        started_at: string | null;
        ends_at: string | null;
        auto_renew: boolean;
    };
    invoices: Invoice[];
    auth: {
        user: User;
    };
}

export default function PortalPage({ subscription, invoices, auth }: PortalPageProps) {
    const creditsPercentage = (subscription.credits_remaining / subscription.credits_total) * 100;
    
    const handlePurchaseCredits = () => {
        router.post('/subscription/purchase-credits', { amount: 50 });
    };

    const handleCancelSubscription = () => {
        if (confirm('Are you sure you want to cancel your subscription?')) {
            router.post('/subscription/downgrade');
        }
    };

    const getTierColor = (tier: string) => {
        switch (tier) {
            case 'launch':
                return 'bg-blue-500';
            case 'growth':
                return 'bg-orange-500';
            case 'scale':
                return 'bg-purple-500';
            default:
                return 'bg-gray-500';
        }
    };

    const getTierName = (tier: string) => {
        return tier.charAt(0).toUpperCase() + tier.slice(1);
    };

    return (
        <AppLayout>
            <Head title="Billing Portal" />

            <div className="min-h-screen bg-background p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-semibold">Billing Portal</h1>
                        <p className="text-muted-foreground mt-2">Manage your subscription and billing</p>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Current Plan */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold">Current Plan</h2>
                                    <Badge className={getTierColor(subscription.tier)}>
                                        {getTierName(subscription.tier)}
                                    </Badge>
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">Monthly Credits</span>
                                        <span className="font-semibold">
                                            {subscription.credits_total === 999999
                                                ? 'Unlimited'
                                                : subscription.credits_total}
                                        </span>
                                    </div>

                                    {subscription.started_at && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Started</span>
                                            <span className="font-semibold">
                                                {new Date(subscription.started_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                    )}

                                    {subscription.ends_at && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">
                                                {subscription.auto_renew ? 'Renews' : 'Ends'}
                                            </span>
                                            <span className="font-semibold">
                                                {new Date(subscription.ends_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-3 mt-6">
                                    <Button asChild className="flex-1">
                                        <Link href="/subscription/plans">
                                            <ArrowUpCircle className="h-4 w-4 mr-2" />
                                            Change Plan
                                        </Link>
                                    </Button>
                                    {subscription.tier !== 'free' && (
                                        <Button
                                            variant="outline"
                                            className="flex-1"
                                            onClick={handleCancelSubscription}
                                        >
                                            <ArrowDownCircle className="h-4 w-4 mr-2" />
                                            Cancel Subscription
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {/* Credits Usage */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h2 className="text-xl font-semibold">Credits Usage</h2>
                                    <Zap className="h-5 w-5" />
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-muted-foreground">Credits Remaining</span>
                                            <span className="text-2xl font-bold">
                                                {subscription.credits_remaining}
                                            </span>
                                        </div>
                                        <Progress value={creditsPercentage} className="h-2" />
                                        <p className="text-sm text-muted-foreground mt-2">
                                            {subscription.credits_remaining} of {subscription.credits_total} credits used this month
                                        </p>
                                    </div>

                                    {creditsPercentage < 20 && subscription.credits_total !== 999999 && (
                                        <div className="flex items-start gap-2 p-4 bg-muted/60 rounded-lg">
                                            <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                            <div>
                                                <p className="font-medium">
                                                    Running low on credits
                                                </p>
                                                <p className="text-sm text-muted-foreground mt-1">
                                                    Consider purchasing additional credits or upgrading your plan.
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={handlePurchaseCredits}
                                    >
                                        <CreditCard className="h-4 w-4 mr-2" />
                                        Purchase Additional Credits
                                    </Button>
                                </div>
                            </div>

                            {/* Invoices */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl font-semibold">Invoices</h2>
                                    <Receipt className="h-5 w-5 text-muted-foreground" />
                                </div>

                                {invoices.length > 0 ? (
                                    <div className="space-y-3">
                                        {invoices.map((invoice) => (
                                            <div
                                                key={invoice.id}
                                                className="flex items-center justify-between p-4 rounded-lg bg-muted/60 hover:bg-muted/80 transition-colors"
                                            >
                                                <div className="flex items-center gap-4">
                                                    <Calendar className="h-5 w-5 text-muted-foreground" />
                                                    <div>
                                                        <p className="font-medium">{invoice.description}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {new Date(invoice.date).toLocaleDateString()}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-semibold">${invoice.amount.toFixed(2)}</p>
                                                    <Badge
                                                        variant={
                                                            invoice.status === 'paid'
                                                                ? 'outline'
                                                                : 'destructive'
                                                        }
                                                        className="mt-1"
                                                    >
                                                        {invoice.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-center py-8 text-muted-foreground">
                                        <Receipt className="h-12 w-12 mx-auto mb-3 opacity-30" />
                                        <p>No invoices yet</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Payment Method */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <h3 className="font-semibold mb-4">Payment Method</h3>
                                <div className="flex items-center gap-3 p-4 rounded-lg bg-muted/60">
                                    <CreditCard className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">•••• •••• •••• 4242</p>
                                        <p className="text-sm text-muted-foreground">Expires 12/25</p>
                                    </div>
                                </div>
                                <Button variant="outline" className="w-full mt-4">
                                    Update Card
                                </Button>
                            </div>

                            {/* Quick Stats */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <h3 className="font-semibold mb-4">Quick Stats</h3>
                                <div className="space-y-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Total Spent</p>
                                        <p className="text-2xl font-semibold">$0.00</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Member Since</p>
                                        <p className="font-medium">
                                            {new Date(auth.user.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Help */}
                            <div className="rounded-lg bg-muted/40 p-6">
                                <h3 className="font-semibold mb-2">Need Help?</h3>
                                <p className="text-sm text-muted-foreground mb-4">
                                    Have questions about billing or your subscription?
                                </p>
                                <Button variant="outline" className="w-full">
                                    Contact Support
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
