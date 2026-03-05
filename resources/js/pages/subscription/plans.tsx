import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { 
    Crown,
    Check,
    Zap,
    TrendingUp,
    Sparkles,
    Rocket
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState, useEffect } from 'react';

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
    remaining_project_slots 
}: SubscriptionPlansProps) {
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');

    useEffect(() => {
        console.log('📦 Subscription Plans Component Loaded');
        console.log('Plans received:', plans);
        console.log('Current tier:', current_tier);
        console.log('Credits:', { remaining: credits_remaining, total: credits_total });
        console.log('Remaining project slots:', remaining_project_slots);
    }, []);

    const handleUpgrade = (planId: string) => {
        console.log('🚀 Upgrade button clicked!');
        console.log('Plan ID:', planId);
        console.log('Billing Period:', billingPeriod);
        console.log('Current Tier:', current_tier);
        
        const payload = {
            tier: planId,
            billing_period: billingPeriod,
        };
        
        console.log('Sending POST to /subscription/upgrade with payload:', payload);
        
        router.post('/subscription/upgrade', payload, {
            onStart: () => console.log('⏳ Request started...'),
            onProgress: (progress) => console.log('📊 Progress:', progress),
            onSuccess: (page) => {
                console.log('✅ Request successful!', page);
            },
            onError: (errors) => {
                console.error('❌ Request failed with errors:', errors);
            },
            onFinish: () => console.log('🏁 Request finished'),
        });
    };

    const getPrice = (plan: Plan) => {
        return billingPeriod === 'yearly' ? plan.yearly_price : plan.price;
    };

    const getYearlySavings = (plan: Plan) => {
        const yearlyTotal = plan.yearly_price * 12;
        const monthlyCost = plan.price * 12;
        const savings = monthlyCost - yearlyTotal; // = 2 months free
        const savingsPercent = Math.round((savings / monthlyCost) * 100); // ~17%
        return { savings, savingsPercent, monthsFree: 2 };
    };

    const getPlanIcon = (planId: string) => {
        switch (planId) {
            case 'launch':
                return <Rocket className="h-5 w-5" />;
            case 'growth':
                return <TrendingUp className="h-5 w-5" />;
            case 'scale':
                return <Crown className="h-5 w-5" />;
            default:
                return <Sparkles className="h-5 w-5" />;
        }
    };

    const getPlanColor = (planId: string) => {
        switch (planId) {
            case 'launch':
                return 'from-blue-500/10 to-blue-500/5 text-blue-600';
            case 'growth':
                return 'from-orange-500/10 to-orange-500/5 text-orange-600';
            case 'scale':
                return 'from-purple-500/10 to-purple-500/5 text-purple-600';
            default:
                return 'from-primary/10 to-primary/5 text-primary';
        }
    };

    const creditsPercentage = credits_total > 0 
        ? Math.round((credits_remaining / credits_total) * 100) 
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subscription Plans" />
            
            <div className="p-6 md:p-8 max-w-[1400px] mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary/10 to-primary/5">
                            <Crown className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Choose Your Plan</h1>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                Scale your visual content production with AI
                            </p>
                        </div>
                    </div>

                    {/* Current Usage Overview */}
                    {current_tier && (
                        <Card className="mb-6">
                            <CardContent className="pt-6">
                                <div className="grid gap-6 md:grid-cols-3">
                                    <div>
                                        <div className="flex items-center gap-2 mb-2">
                                            <span className="text-sm font-medium">Current Plan</span>
                                            <Badge variant="secondary" className="capitalize">
                                                {current_tier}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-sm font-medium mb-2">Credits Remaining</div>
                                        <div className="flex items-baseline gap-2 mb-2">
                                            <span className="text-2xl font-bold">{credits_remaining}</span>
                                            <span className="text-sm text-muted-foreground">/ {credits_total}</span>
                                        </div>
                                        <Progress value={creditsPercentage} className="h-2" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-medium mb-2">Project Slots</div>
                                        <div className="flex items-baseline gap-2">
                                            <span className="text-2xl font-bold">{remaining_project_slots}</span>
                                            <span className="text-sm text-muted-foreground">remaining</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Billing Period Toggle */}
                    <div className="flex items-center justify-center gap-4 mb-8">
                        <button
                            onClick={() => setBillingPeriod('monthly')}
                            className={cn(
                                "px-4 py-2 rounded-lg font-medium transition-colors",
                                billingPeriod === 'monthly'
                                    ? "bg-primary text-primary-foreground"
                                    : "bg-muted text-muted-foreground hover:bg-muted/80"
                            )}
                        >
                            Monthly
                        </button>
                        <button
                            onClick={() => setBillingPeriod('yearly')}
                            className={cn(
                                "px-4 py-2 rounded-lg font-medium transition-colors flex items-center gap-2",
                                billingPeriod === 'yearly'
                                    ? "bg-primary text-primary-foreground"
                                    : "bg-muted text-muted-foreground hover:bg-muted/80"
                            )}
                        >
                            Yearly
                            <Badge variant="secondary" className="bg-green-500/10 text-green-700 border-green-500/20">
                                2 months free
                            </Badge>
                        </button>
                    </div>
                </div>

                {/* Plans Grid */}
                <div className="grid gap-6 md:grid-cols-3 mb-8">
                    {plans.map((plan) => {
                        const isCurrentPlan = current_tier?.toLowerCase() === plan.id.toLowerCase();
                        const price = getPrice(plan);
                        const { savingsPercent } = getYearlySavings(plan);

                        return (
                            <Card 
                                key={plan.id} 
                                className={cn(
                                    "relative overflow-hidden transition-all hover:shadow-lg",
                                    plan.popular && "border-primary shadow-md",
                                    isCurrentPlan && "ring-2 ring-primary"
                                )}
                            >
                                {plan.popular && (
                                    <div className="absolute top-0 right-0 bg-primary text-primary-foreground px-3 py-1 text-xs font-medium rounded-bl-lg">
                                        Most Popular
                                    </div>
                                )}
                                
                                <CardHeader>
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className={cn(
                                            "flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br",
                                            getPlanColor(plan.id)
                                        )}>
                                            {getPlanIcon(plan.id)}
                                        </div>
                                        <div>
                                            <CardTitle className="text-xl">{plan.name}</CardTitle>
                                            <CardDescription className="text-xs">{plan.subtitle}</CardDescription>
                                        </div>
                                    </div>
                                    
                                    <div className="mt-4">
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-3xl font-bold">€{price.toFixed(2)}</span>
                                            <span className="text-muted-foreground text-sm">/month</span>
                                        </div>
                                        {billingPeriod === 'yearly' && (
                                            <>
                                                <p className="text-xs text-muted-foreground mt-0.5">
                                                    €{(plan.price * 10).toFixed(0)} billed yearly
                                                </p>
                                                <p className="text-xs text-green-600 mt-0.5">
                                                    2 months free — save €{(plan.price * 2).toFixed(0)}/year
                                                </p>
                                            </>
                                        )}
                                    </div>
                                </CardHeader>

                                <CardContent>
                                    <Separator className="mb-4" />
                                    
                                    {/* Key Metrics */}
                                    <div className="space-y-2 mb-4">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Zap className="h-4 w-4 text-yellow-500" />
                                            <span className="font-medium">{plan.credits.toLocaleString()} credits/month</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <Check className="h-4 w-4 text-green-500" />
                                            <span>{plan.max_projects} Brand Projects</span>
                                        </div>
                                    </div>

                                    <Separator className="mb-4" />

                                    {/* Features */}
                                    <ul className="space-y-2 mb-4">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm">
                                                <Check className="h-4 w-4 text-muted-foreground mt-0.5 flex-shrink-0" />
                                                <span className="text-muted-foreground">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    {/* Best For */}
                                    {plan.bestFor.length > 0 && (
                                        <>
                                            <Separator className="mb-4" />
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground mb-2">Best for:</p>
                                                <ul className="space-y-1">
                                                    {plan.bestFor.map((item, index) => (
                                                        <li key={index} className="text-xs text-muted-foreground">
                                                            • {item}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </>
                                    )}
                                </CardContent>

                                <CardFooter>
                                    <Button
                                        onClick={() => {
                                            console.log(`🔘 Button clicked for plan: ${plan.id}`, { 
                                                isCurrentPlan, 
                                                planName: plan.name,
                                                disabled: isCurrentPlan 
                                            });
                                            handleUpgrade(plan.id);
                                        }}
                                        disabled={isCurrentPlan}
                                        className="w-full"
                                        variant={plan.popular ? "default" : "outline"}
                                    >
                                        {isCurrentPlan ? 'Current Plan' : `Upgrade to ${plan.name}`}
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                {/* Additional Info */}
                <Card className="bg-muted/50">
                    <CardContent className="pt-6">
                        <div className="grid gap-6 md:grid-cols-3 text-sm">
                            <div>
                                <div className="font-medium mb-2 flex items-center gap-2">
                                    <Check className="h-4 w-4 text-green-500" />
                                    All Plans Include
                                </div>
                                <ul className="space-y-1 text-muted-foreground">
                                    <li>• Brand DNA extraction</li>
                                    <li>• CSV batch generation</li>
                                    <li>• Canvas editor</li>
                                    <li>• Version history</li>
                                </ul>
                            </div>
                            <div>
                                <div className="font-medium mb-2 flex items-center gap-2">
                                    <Zap className="h-4 w-4 text-yellow-500" />
                                    Flexible Billing
                                </div>
                                <ul className="space-y-1 text-muted-foreground">
                                    <li>• Cancel anytime</li>
                                    <li>• Upgrade/downgrade anytime</li>
                                    <li>• Pro-rated credits</li>
                                    <li>• Secure payments via Lemon Squeezy</li>
                                </ul>
                            </div>
                            <div>
                                <div className="font-medium mb-2 flex items-center gap-2">
                                    <Sparkles className="h-4 w-4 text-purple-500" />
                                    Need More?
                                </div>
                                <p className="text-muted-foreground mb-2">
                                    For enterprise needs, custom models, or dedicated support:
                                </p>
                                <Button variant="link" className="p-0 h-auto">
                                    Contact Sales →
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
