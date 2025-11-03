import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { H1, Subtext } from '@/components/ds/typography';
import { Head, router } from '@inertiajs/react';
import { Check, Sparkles, Zap, Crown } from 'lucide-react';
import { type User } from '@/types';

interface Plan {
    id: string;
    name: string;
    price: number;
    credits: number;
    features: string[];
    popular?: boolean;
}

interface PlansPageProps {
    plans: Plan[];
    auth: {
        user: User & {
            subscription_tier: string;
            credits_remaining: number;
        };
    };
}

export default function PlansPage({ plans, auth }: PlansPageProps) {
    const handleUpgrade = (tier: string) => {
        router.post('/subscription/upgrade', { tier });
    };

    const getPlanIcon = (planId: string) => {
        switch (planId) {
            case 'pro':
                return <Zap className="h-8 w-8" />;
            case 'enterprise':
                return <Crown className="h-8 w-8" />;
            default:
                return <Sparkles className="h-8 w-8" />;
        }
    };

    return (
        <AppLayout>
            <Head title="Subscription Plans" />

            <div className="min-h-screen bg-white p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="text-center mb-12">
                        <H1 className="mb-2">Choose your plan</H1>
                        <Subtext>Start creating brand‑consistent visuals with AI</Subtext>
                        {auth.user.subscription_tier && (
                            <div className="mt-4">
                                <Badge className="text-sm px-4 py-2">
                                    Current Plan: {auth.user.subscription_tier.charAt(0).toUpperCase() + auth.user.subscription_tier.slice(1)}
                                </Badge>
                                <p className="text-sm text-gray-600 mt-2">
                                    {auth.user.credits_remaining} credits remaining
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Plans Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                        {plans.map((plan) => (
                            <Card
                                key={plan.id}
                                className={`relative p-8 bg-white border border-[#EAEAEB] shadow-none ${
                                    plan.popular ? 'ring-1 ring-gray-300' : ''
                                }`}
                            >
                                {plan.popular && (
                                    <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 bg-[#1F2937] text-white">
                                        Most Popular
                                    </Badge>
                                )}

                                {auth.user.subscription_tier === plan.id && (
                                    <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 bg-gray-800 text-white">
                                        Current Plan
                                    </Badge>
                                )}

                                <div className="text-center mb-6">
                                    <div className="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4 text-gray-700">
                                        {getPlanIcon(plan.id)}
                                    </div>
                                    <h3 className="text-2xl font-bold text-gray-900 mb-2">
                                        {plan.name}
                                    </h3>
                                    <div className="flex items-baseline justify-center gap-1">
                                        <span className="text-4xl font-bold text-gray-900">
                                            ${plan.price}
                                        </span>
                                        {plan.price > 0 && (
                                            <span className="text-gray-600">/month</span>
                                        )}
                                    </div>
                                </div>

                                <div className="mb-8">
                                    <div className="text-center mb-6">
                                        <p className="text-3xl font-bold text-gray-900">
                                            {plan.credits === 999999 ? 'Unlimited' : plan.credits}
                                        </p>
                                        <p className="text-sm text-gray-600">
                                            {plan.credits === 999999 ? 'generations' : 'credits/month'}
                                        </p>
                                    </div>

                                    <ul className="space-y-3">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-2">
                                                <Check className="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5" />
                                                <span className="text-sm text-gray-700">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>

                                <Button
                                    className={`w-full ${auth.user.subscription_tier === plan.id ? 'bg-gray-200 text-gray-500' : 'bg-[#1F2937] hover:bg-[#111827] text-white'}`}
                                    variant="default"
                                    disabled={auth.user.subscription_tier === plan.id}
                                    onClick={() => handleUpgrade(plan.id)}
                                >
                                    {auth.user.subscription_tier === plan.id
                                        ? 'Current Plan'
                                        : plan.price === 0
                                        ? 'Downgrade to Free'
                                        : 'Upgrade Now'}
                                </Button>
                            </Card>
                        ))}
                    </div>

                    {/* FAQ Section */}
                    <Card className="p-8">
                        <h3 className="text-2xl font-bold text-center mb-8">
                            Frequently Asked Questions
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <h4 className="font-semibold text-gray-900 mb-2">
                                    What happens if I run out of credits?
                                </h4>
                                <p className="text-gray-600 text-sm">
                                    You can purchase additional credits or upgrade to a higher tier.
                                    Your credits reset monthly based on your subscription tier.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-gray-900 mb-2">
                                    Can I change plans anytime?
                                </h4>
                                <p className="text-gray-600 text-sm">
                                    Yes! You can upgrade or downgrade at any time. When upgrading,
                                    changes take effect immediately. When downgrading, changes apply
                                    at the end of your billing period.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-gray-900 mb-2">
                                    Do unused credits roll over?
                                </h4>
                                <p className="text-gray-600 text-sm">
                                    No, credits reset monthly and do not roll over. Make sure to use
                                    your credits before the end of your billing cycle.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-semibold text-gray-900 mb-2">
                                    What payment methods do you accept?
                                </h4>
                                <p className="text-gray-600 text-sm">
                                    We accept all major credit cards through our secure payment processor, Paddle.
                                </p>
                            </div>
                        </div>
                    </Card>

                    {/* CTA */}
                    <div className="text-center mt-12">
                        <p className="text-gray-600 mb-4">
                            Need help choosing the right plan?
                        </p>
                        <Button variant="outline" size="lg">
                            Contact Sales
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
