import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Crown, KeyRound } from 'lucide-react';
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

export default function SubscriptionPlans({
    plans,
    current_tier,
    credits_remaining,
    credits_total,
    remaining_project_slots,
}: SubscriptionPlansProps) {
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');
    const [showBetaCode, setShowBetaCode] = useState(false);
    const inviteForm = useForm({ code: '' });

    const redeemInvite = (e: React.FormEvent) => {
        e.preventDefault();
        inviteForm.post('/invite/redeem', { preserveScroll: true });
    };

    const handleUpgrade = (planId: string) => {
        router.post('/subscription/upgrade', { tier: planId, billing_period: billingPeriod });
    };

    const creditsPercentage =
        credits_total > 0 ? Math.round((credits_remaining / credits_total) * 100) : 0;
    const currencySymbol = plans[0]?.currency === 'USD' ? '$' : '€';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plans" />

            <div className="mx-auto max-w-6xl p-6 md:p-8">
                <div className="mb-8 text-center">
                    <div className="mb-3 flex items-center justify-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Crown className="h-5 w-5 text-primary" />
                        </div>
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">Choose your plan</h1>
                    <p className="mt-1 text-muted-foreground">
                        Generate brand-consistent visuals from CSV. Pay monthly or save 2 months with yearly.
                    </p>
                </div>

                {current_tier && (
                    <Card className="mb-8">
                        <CardContent className="pt-6">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <div className="mb-1 text-sm font-medium">Current Plan</div>
                                    <Badge variant="secondary" className="capitalize">
                                        {current_tier}
                                    </Badge>
                                </div>
                                <div>
                                    <div className="mb-1 text-sm font-medium">Credits Remaining</div>
                                    <div className="mb-1 flex items-baseline gap-2">
                                        <span className="text-xl font-bold">{credits_remaining}</span>
                                        <span className="text-sm text-muted-foreground">
                                            / {credits_total}
                                        </span>
                                    </div>
                                    <Progress value={creditsPercentage} className="h-1.5" />
                                </div>
                                <div>
                                    <div className="mb-1 text-sm font-medium">Project Slots</div>
                                    <span className="text-xl font-bold">{remaining_project_slots}</span>
                                    <span className="ml-1 text-sm text-muted-foreground">remaining</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="mb-8 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        onClick={() => setBillingPeriod('monthly')}
                        className={cn(
                            'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                            billingPeriod === 'monthly'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                        )}
                    >
                        Monthly
                    </button>
                    <button
                        type="button"
                        onClick={() => setBillingPeriod('yearly')}
                        className={cn(
                            'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                            billingPeriod === 'yearly'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                        )}
                    >
                        Yearly
                        <span className="ml-2 text-xs opacity-80">2 months free</span>
                    </button>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {plans.map((plan) => {
                        const isCurrent =
                            current_tier?.toLowerCase() === plan.id.toLowerCase();
                        const monthlyEquivalent =
                            billingPeriod === 'yearly' ? plan.yearly_price : plan.price;
                        const yearlyTotal = plan.yearly_total ?? plan.price * 10;

                        return (
                            <Card
                                key={plan.id}
                                className={cn(
                                    'relative flex flex-col',
                                    plan.popular && 'border-primary shadow-sm',
                                )}
                            >
                                {plan.popular && (
                                    <Badge className="absolute -top-2.5 left-1/2 -translate-x-1/2">
                                        Most popular
                                    </Badge>
                                )}
                                <CardHeader>
                                    <CardTitle className="text-xl">{plan.name}</CardTitle>
                                    <p className="text-sm text-muted-foreground">{plan.subtitle}</p>
                                    <div className="pt-4">
                                        <span className="text-4xl font-bold tracking-tight">
                                            {currencySymbol}
                                            {monthlyEquivalent}
                                        </span>
                                        <span className="text-muted-foreground">/mo</span>
                                        {billingPeriod === 'yearly' && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Billed {currencySymbol}
                                                {yearlyTotal}/year
                                            </p>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-4">
                                    <ul className="space-y-2 text-sm">
                                        <li className="flex gap-2">
                                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                            <span>
                                                <strong>{plan.credits}</strong> credits / month
                                            </span>
                                        </li>
                                        <li className="flex gap-2">
                                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                            <span>
                                                Up to <strong>{plan.max_projects}</strong> projects
                                            </span>
                                        </li>
                                        <li className="flex gap-2">
                                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                            <span>
                                                CSV batches up to <strong>{plan.csv_max_rows}</strong>{' '}
                                                rows
                                            </span>
                                        </li>
                                        {plan.features
                                            .filter(
                                                (f) =>
                                                    !f.toLowerCase().includes('credit') &&
                                                    !f.toLowerCase().includes('project') &&
                                                    !f.toLowerCase().includes('csv'),
                                            )
                                            .slice(0, 5)
                                            .map((feature) => (
                                                <li key={feature} className="flex gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                                    <span>{feature}</span>
                                                </li>
                                            ))}
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        className="w-full"
                                        variant={plan.popular ? 'default' : 'outline'}
                                        disabled={isCurrent}
                                        onClick={() => handleUpgrade(plan.id)}
                                    >
                                        {isCurrent ? 'Current plan' : `Get ${plan.name}`}
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                <div className="mt-10 border-t pt-6 text-center">
                    {!showBetaCode ? (
                        <button
                            type="button"
                            onClick={() => setShowBetaCode(true)}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Have a beta code?
                        </button>
                    ) : (
                        <form
                            onSubmit={redeemInvite}
                            className="mx-auto flex max-w-md flex-col items-center gap-3 sm:flex-row"
                        >
                            <div className="relative w-full">
                                <KeyRound className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    placeholder="Enter invite code"
                                    value={inviteForm.data.code}
                                    onChange={(e) => inviteForm.setData('code', e.target.value)}
                                />
                            </div>
                            <Button type="submit" disabled={inviteForm.processing}>
                                Redeem
                            </Button>
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
