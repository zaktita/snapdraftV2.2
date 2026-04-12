import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Crown, KeyRound, Sparkles, Zap } from 'lucide-react';
import { useState } from 'react';

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

interface Plan {
    id: string;
    name: string;
    subtitle: string;
    price: number;
    yearly_price: number;
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

export default function SubscriptionPlans({
    plans,
    current_tier,
    credits_remaining,
    credits_total,
    remaining_project_slots,
}: SubscriptionPlansProps) {
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');
    const inviteForm = useForm({ code: '' });

    const redeemInvite = (e: React.FormEvent) => {
        e.preventDefault();
        inviteForm.post('/invite/redeem', { preserveScroll: true });
    };

    const handleUpgrade = (planId: string) => {
        router.post('/subscription/upgrade', { tier: planId, billing_period: billingPeriod });
    };

    const plan = plans[0];
    const creditsPercentage = credits_total > 0 ? Math.round((credits_remaining / credits_total) * 100) : 0;
    const isCurrentPlan = current_tier?.toLowerCase() === plan?.id?.toLowerCase();
    const price = plan ? (billingPeriod === 'yearly' ? plan.yearly_price : plan.price) : 0;
    const currencySymbol = plan?.currency === 'USD' ? '$' : '€';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscription" />

            <div className="p-6 md:p-8 max-w-2xl mx-auto">
                {/* Header */}
                <div className="mb-8 text-center">
                    <div className="flex items-center justify-center gap-3 mb-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary/10 to-primary/5">
                            <Crown className="h-5 w-5 text-primary" />
                        </div>
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">SnapDraft Beta</h1>
                    <p className="text-muted-foreground mt-1">Start your 7-day free trial — no credit card required</p>
                </div>

                {/* Current Usage */}
                {current_tier && (
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <div className="text-sm font-medium mb-1">Current Plan</div>
                                    <Badge variant="secondary" className="capitalize">{current_tier}</Badge>
                                </div>
                                <div>
                                    <div className="text-sm font-medium mb-1">Credits Remaining</div>
                                    <div className="flex items-baseline gap-2 mb-1">
                                        <span className="text-xl font-bold">{credits_remaining}</span>
                                        <span className="text-sm text-muted-foreground">/ {credits_total}</span>
                                    </div>
                                    <Progress value={creditsPercentage} className="h-1.5" />
                                </div>
                                <div>
                                    <div className="text-sm font-medium mb-1">Project Slots</div>
                                    <span className="text-xl font-bold">{remaining_project_slots}</span>
                                    <span className="text-sm text-muted-foreground ml-1">remaining</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Billing period toggle */}
                {plan && (
                    <div className="flex items-center justify-center gap-3 mb-6">
                        <button
                            onClick={() => setBillingPeriod('monthly')}
                            className={cn(
                                'px-4 py-2 rounded-lg font-medium text-sm transition-colors',
                                billingPeriod === 'monthly'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            )}
                        >
                            Monthly
                        </button>
                        <button
                            onClick={() => setBillingPeriod('yearly')}
                            className={cn(
                                'px-4 py-2 rounded-lg font-medium text-sm transition-colors flex items-center gap-2',
                                billingPeriod === 'yearly'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            )}
                        >
                            Yearly
                            <Badge variant="secondary" className="bg-green-500/10 text-green-700 border-green-500/20 text-xs">
                                2 months free
                            </Badge>
                        </button>
                    </div>
                )}

                {/* Plan card */}
                {plan ? (
                    <Card className={cn('overflow-hidden', isCurrentPlan && 'ring-2 ring-primary')}>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-3 mb-2">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary/10 to-primary/5">
                                    <Sparkles className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <CardTitle className="text-xl">{plan.name}</CardTitle>
                                    <p className="text-xs text-muted-foreground">{plan.subtitle}</p>
                                </div>
                            </div>

                            <div className="mt-2">
                                <div className="flex items-baseline gap-1">
                                    <span className="text-4xl font-bold">{currencySymbol}{price.toFixed(0)}</span>
                                    <span className="text-muted-foreground text-sm">/month</span>
                                </div>
                                {billingPeriod === 'yearly' && (
                                    <p className="text-xs text-green-600 mt-1">
                                        {currencySymbol}{(plan.price * 10).toFixed(0)} billed yearly — 2 months free
                                    </p>
                                )}
                                <p className="text-sm text-muted-foreground mt-1">7-day free trial included</p>
                            </div>
                        </CardHeader>

                        <CardContent>
                            <Separator className="mb-4" />

                            {/* Key metrics */}
                            <div className="grid grid-cols-3 gap-4 mb-4 text-center">
                                <div className="rounded-lg bg-muted/50 p-3">
                                    <div className="flex items-center justify-center gap-1 mb-1">
                                        <Zap className="h-3.5 w-3.5 text-yellow-500" />
                                        <span className="text-lg font-bold">{plan.credits}</span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">Credits / mo</p>
                                </div>
                                <div className="rounded-lg bg-muted/50 p-3">
                                    <div className="text-lg font-bold mb-1">{plan.max_projects}</div>
                                    <p className="text-xs text-muted-foreground">Projects</p>
                                </div>
                                <div className="rounded-lg bg-muted/50 p-3">
                                    <div className="text-lg font-bold mb-1">{plan.csv_max_rows}</div>
                                    <p className="text-xs text-muted-foreground">CSV rows</p>
                                </div>
                            </div>

                            <Separator className="mb-4" />

                            {/* Features */}
                            <ul className="space-y-2">
                                {plan.features.map((feature, i) => (
                                    <li key={i} className="flex items-start gap-2 text-sm">
                                        <Check className="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span className="text-muted-foreground">{feature}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>

                        <CardFooter className="flex-col gap-2 pt-2">
                            <Button
                                onClick={() => handleUpgrade(plan.id)}
                                disabled={isCurrentPlan}
                                className="w-full"
                                size="lg"
                            >
                                {isCurrentPlan ? 'Current Plan' : 'Start Free Trial'}
                            </Button>
                            {!isCurrentPlan && (
                                <p className="text-xs text-center text-muted-foreground">
                                    7 days free, then {currencySymbol}{plan.price}/month. Cancel anytime.
                                </p>
                            )}
                        </CardFooter>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="pt-6 text-center text-muted-foreground">
                            No plans available. Please check back soon.
                        </CardContent>
                    </Card>
                )}

                {/* Beta invite code */}
                {!current_tier && (
                    <div className="mt-6">
                        <div className="relative flex items-center gap-3 mb-6">
                            <Separator className="flex-1" />
                            <span className="text-xs text-muted-foreground shrink-0">or redeem a beta invite</span>
                            <Separator className="flex-1" />
                        </div>
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-2 mb-3">
                                    <KeyRound className="h-4 w-4 text-muted-foreground" />
                                    <p className="text-sm font-medium">Have a beta invite code?</p>
                                </div>
                                <form onSubmit={redeemInvite} className="flex gap-2">
                                    <Input
                                        value={inviteForm.data.code}
                                        onChange={e => inviteForm.setData('code', e.target.value.toUpperCase())}
                                        placeholder="XXXXXXXX"
                                        className="font-mono uppercase"
                                        maxLength={20}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={inviteForm.processing || !inviteForm.data.code}
                                        className="shrink-0"
                                    >
                                        Redeem
                                    </Button>
                                </form>
                                {inviteForm.errors.code && (
                                    <p className="text-xs text-destructive mt-2">{inviteForm.errors.code}</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Footer trust signals */}
                <div className="mt-6 grid gap-3 text-sm text-muted-foreground sm:grid-cols-3">
                    <div className="flex items-center gap-2">
                        <Check className="h-4 w-4 text-green-500 flex-shrink-0" />
                        Cancel anytime
                    </div>
                    <div className="flex items-center gap-2">
                        <Check className="h-4 w-4 text-green-500 flex-shrink-0" />
                        Secure via Lemon Squeezy
                    </div>
                    <div className="flex items-center gap-2">
                        <Check className="h-4 w-4 text-green-500 flex-shrink-0" />
                        No credit card for trial
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
