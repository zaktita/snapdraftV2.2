import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { H1, Subtext } from '@/components/ds/typography';
import { Head, router } from '@inertiajs/react';
import { Check, Sparkles, Zap, Crown } from 'lucide-react';
import { useState } from 'react';
import { type User } from '@/types';

interface Plan {
    id: string;
    name: string;
    subtitle?: string;
    price: number;
    credits: number;
    max_projects?: number;
    csv_max_rows?: number;
    features: string[];
    popular?: boolean;
    bestFor?: string[];
}

interface PlansPageProps {
    plans: Plan[];
    current_limits?: {
        credits_per_month: number;
        max_projects: number;
        csv_max_rows: number;
        features: Record<string, boolean>;
    };
    remaining_project_slots?: number;
    auth: {
        user: User & {
            subscription_tier: string;
            credits_remaining: number;
        };
    };
}

export default function PlansPage({ plans, auth, current_limits, remaining_project_slots }: PlansPageProps) {
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'annually'>('monthly');
    
    const handleUpgrade = (tier: string) => {
        router.post('/subscription/upgrade', { 
            tier,
            billing_period: billingPeriod === 'annually' ? 'yearly' : 'monthly'
        });
    };

    const getPlanIcon = (planId: string) => {
        switch (planId) {
            case 'launch':
                return <Sparkles className="h-6 w-6" />;
            case 'growth':
                return <Zap className="h-6 w-6" />;
            case 'scale':
                return <Crown className="h-6 w-6" />;
            default:
                return <Sparkles className="h-6 w-6" />;
        }
    };

    return (
        <AppLayout>
            <Head title="Subscription Plans" />

            <div className="min-h-screen bg-[#0a0b0f] p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="text-center mb-8">
                        <Badge className="mb-4 bg-orange-500/10 text-orange-500 border-orange-500/20 hover:bg-orange-500/20">
                            Pricing
                        </Badge>
                        <h1 className="text-4xl md:text-5xl font-bold text-white mb-4">
                            Choose the Perfect<br />Plan for Your Business
                        </h1>
                        <p className="text-gray-400 text-lg">
                            Whether you're just starting or scaling up, SnapDraft has a plan that fits your need.
                        </p>
                    </div>

                    {/* Billing Toggle */}
                    <div className="flex justify-center items-center gap-4 mb-12">
                        <button
                            onClick={() => setBillingPeriod('monthly')}
                            className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all ${
                                billingPeriod === 'monthly'
                                    ? 'bg-orange-500 text-white'
                                    : 'bg-transparent text-gray-400 hover:text-white'
                            }`}
                        >
                            <span className={`w-2 h-2 rounded-full ${
                                billingPeriod === 'monthly' ? 'bg-white' : 'bg-gray-600'
                            }`} />
                            Monthly
                        </button>
                        <button
                            onClick={() => setBillingPeriod('annually')}
                            className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all ${
                                billingPeriod === 'annually'
                                    ? 'bg-orange-500 text-white'
                                    : 'bg-transparent text-gray-400 hover:text-white'
                            }`}
                        >
                            Annually
                        </button>
                        <Badge className="bg-orange-500/10 text-orange-500 border-orange-500/20">
                            Save 20%
                        </Badge>
                    </div>

                    {/* Current Plan Status */}
                    {auth.user.subscription_tier && (
                        <div className="text-center mb-8">
                            <Badge className="text-sm px-4 py-2 bg-orange-500/10 text-orange-500 border-orange-500/20">
                                Current Plan: {auth.user.subscription_tier.charAt(0).toUpperCase() + auth.user.subscription_tier.slice(1)}
                            </Badge>
                            <p className="text-sm text-gray-400 mt-2">
                                {auth.user.credits_remaining} credits remaining
                            </p>
                        </div>
                    )}

                    {/* Plans Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                        {plans.map((plan) => {
                            const isPopular = plan.popular;
                            const isCurrent = auth.user.subscription_tier === plan.id;
                            
                            // Calculate prices: stored price is monthly, yearly gets 20% discount
                            const monthlyPrice = plan.price;
                            const yearlyPrice = Math.round(plan.price * 0.8);
                            const displayPrice = billingPeriod === 'monthly' ? monthlyPrice : yearlyPrice;
                            
                            return (
                                <div
                                    key={plan.id}
                                    className={`relative rounded-2xl p-8 backdrop-blur-sm transition-all hover:scale-[1.02] ${
                                        isPopular
                                            ? 'bg-orange-500/10 border border-orange-500/30'
                                            : 'bg-[#1a1d29]/80 border border-gray-800'
                                    }`}
                                >
                                    {/* Plan Header */}
                                    <div className="mb-6">
                                        <h3 className="text-2xl font-bold text-white mb-1">
                                            {plan.name}
                                        </h3>
                                        {plan.subtitle && (
                                            <p className="text-sm text-gray-400">{plan.subtitle}</p>
                                        )}
                                    </div>

                                    {/* Price */}
                                    <div className="mb-6">
                                        <div className="flex items-baseline gap-2">
                                            <span className="text-5xl font-bold text-white">
                                                €{displayPrice}
                                            </span>
                                            <span className="text-gray-400">/ month</span>
                                        </div>
                                        {billingPeriod === 'annually' && (
                                            <p className="text-sm text-orange-400 mt-1">
                                                Save €{(monthlyPrice - yearlyPrice) * 12}/year
                                            </p>
                                        )}
                                        <p className="text-sm text-gray-500 mt-2">
                                            {plan.credits.toLocaleString()} credits/month
                                        </p>
                                    </div>

                                    {/* CTA Button */}
                                    <Button
                                        className={`w-full mb-8 ${
                                            isPopular
                                                ? 'bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white'
                                                : isCurrent
                                                ? 'bg-gray-800 text-gray-400'
                                                : 'bg-white hover:bg-gray-100 text-black'
                                        }`}
                                        disabled={isCurrent}
                                        onClick={() => handleUpgrade(plan.id)}
                                    >
                                        {isCurrent ? 'Current Plan' : 'Upgrade'}
                                    </Button>

                                    {/* Plan Includes Label */}
                                    <div className="mb-4">
                                        <p className="text-sm font-semibold text-white">
                                            Includes:
                                        </p>
                                    </div>

                                    {/* Features List */}
                                    <ul className="space-y-3 mb-6">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-3">
                                                <div className="mt-0.5">
                                                    <Check className={`h-5 w-5 ${
                                                        isPopular ? 'text-orange-400' : 'text-gray-400'
                                                    }`} />
                                                </div>
                                                <span className="text-sm text-gray-300">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    {/* Best For Section */}
                                    {plan.bestFor && plan.bestFor.length > 0 && (
                                        <div className="mt-6 pt-6 border-t border-gray-800">
                                            <p className="text-sm font-semibold text-white mb-3">
                                                Best for:
                                            </p>
                                            <ul className="space-y-2">
                                                {plan.bestFor.map((item, index) => (
                                                    <li key={index} className="text-sm text-gray-400">
                                                        • {item}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Credit Packs */}
                    <div className="mb-12">
                        <div className="text-center mb-8">
                            <h2 className="text-3xl font-bold text-white mb-2">Need More Credits?</h2>
                            <p className="text-gray-400">Purchase additional credits anytime</p>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                            {/* Credit Pack 1 */}
                            <div className="relative rounded-2xl p-8 bg-[#1a1d29]/80 border border-gray-800 backdrop-blur-sm transition-all hover:scale-[1.02]">
                                <div className="mb-6">
                                    <h3 className="text-2xl font-bold text-white mb-1">500 Credits</h3>
                                    <p className="text-sm text-gray-400">One-time purchase</p>
                                </div>
                                
                                <div className="mb-6">
                                    <div className="flex items-baseline gap-2">
                                        <span className="text-5xl font-bold text-white">$49</span>
                                    </div>
                                    <p className="text-sm text-gray-500 mt-2">~125 high-precision posts</p>
                                </div>
                                
                                <Button
                                    className="w-full mb-6 bg-white hover:bg-gray-100 text-black"
                                    onClick={() => router.post('/credits/purchase', { amount: 500 })}
                                >
                                    Buy Credits
                                </Button>
                                
                                <ul className="space-y-3">
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-gray-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Never expires</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-gray-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Works with any plan</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-gray-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Instant delivery</span>
                                    </li>
                                </ul>
                            </div>
                            
                            {/* Credit Pack 2 */}
                            <div className="relative rounded-2xl p-8 bg-[#1a1d29]/80 border border-gray-800 backdrop-blur-sm transition-all hover:scale-[1.02]">
                                <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 bg-orange-500 text-white border-0">
                                    Best Value
                                </Badge>
                                
                                <div className="mb-6">
                                    <h3 className="text-2xl font-bold text-white mb-1">1,200 Credits</h3>
                                    <p className="text-sm text-gray-400">One-time purchase</p>
                                </div>
                                
                                <div className="mb-6">
                                    <div className="flex items-baseline gap-2">
                                        <span className="text-5xl font-bold text-white">$99</span>
                                    </div>
                                    <p className="text-sm text-orange-400 mt-1">Save $19 vs 500 pack</p>
                                    <p className="text-sm text-gray-500 mt-1">~300 high-precision posts</p>
                                </div>
                                
                                <Button
                                    className="w-full mb-6 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white"
                                    onClick={() => router.post('/credits/purchase', { amount: 1200 })}
                                >
                                    Buy Credits
                                </Button>
                                
                                <ul className="space-y-3">
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-orange-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Never expires</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-orange-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Works with any plan</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-orange-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">Instant delivery</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="h-5 w-5 text-orange-400 mt-0.5" />
                                        <span className="text-sm text-gray-300">16% discount included</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* FAQ Section */}
                    <div className="rounded-2xl bg-[#1a1d29]/80 border border-gray-800 p-8">
                        <h3 className="text-2xl font-bold text-white text-center mb-8">
                            Frequently Asked Questions
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <h4 className="font-semibold text-white mb-2">
                                    What happens if I run out of credits?
                                </h4>
                                <p className="text-gray-400 text-sm">
                                    You can purchase additional credits or upgrade to a higher tier.
                                    Your credits reset monthly based on your subscription tier.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-white mb-2">
                                    Can I change plans anytime?
                                </h4>
                                <p className="text-gray-400 text-sm">
                                    Yes! You can upgrade or downgrade at any time. When upgrading,
                                    changes take effect immediately. When downgrading, changes apply
                                    at the end of your billing period.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-white mb-2">
                                    Do unused credits roll over?
                                </h4>
                                <p className="text-gray-400 text-sm">
                                    No, credits reset monthly and do not roll over. Make sure to use
                                    your credits before the end of your billing cycle.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-white mb-2">
                                    What payment methods do you accept?
                                </h4>
                                <p className="text-gray-400 text-sm">
                                    We accept all major credit cards through our secure payment processor, Paddle.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* CTA */}
                    <div className="text-center mt-12">
                        <p className="text-gray-400 mb-4">
                            Need help choosing the right plan?
                        </p>
                        <Button variant="outline" size="lg" className="border-gray-700 text-white hover:bg-gray-800">
                            Contact Sales
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
