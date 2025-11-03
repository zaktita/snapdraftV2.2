import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';

export function CreditsCard() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user as typeof auth.user & {
        subscription_tier?: string;
        credits_remaining?: number;
        credits_total?: number;
    };

    const creditsRemaining = user.credits_remaining ?? 0;
    const creditsTotal = user.credits_total ?? 10;
    const tier = user.subscription_tier ?? 'free';
    
    const isUnlimited = creditsTotal === 999999;
    const creditsUsed = isUnlimited ? 0 : Math.max(creditsTotal - creditsRemaining, 0);
    const usagePercent = isUnlimited || creditsTotal === 0 ? 0 : (creditsUsed / creditsTotal) * 100;

    return (
        <Card className="mx-2  overflow-hidden bg-white border border-[#EAEAEB] shadow-none p-4">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-1 text-[#6B7280]">
                    <Info className="h-4 w-4" />
                    <p className="text-sm font-medium">
                        {(tier === 'enterprise' ? 'Enterprise' : tier === 'pro' ? 'Pro' : 'Free')} Plan
                    </p>
                </div>
            </div>

            {!isUnlimited ? (
                <div>
                    <Progress
                        value={usagePercent}
                        className="h-1 bg-[#F3F4F6]"
                        indicatorClassName="bg-[#9CA3AF]"
                        aria-label="Credit usage"
                    />
                    <p className="mt-1 text-sm text-[#374151]">
                        <span className="font-semibold">{creditsUsed.toLocaleString()} / {creditsTotal.toLocaleString()}</span> credits used
                    </p>
                </div>
            ) : (
                <p className="text-sm text-[#374151]">Unlimited credits</p>
            )}

            <div>
                <Button
                    asChild
                    size="sm"
                    className="w-full bg-[#1F2937] hover:bg-[#111827] text-white"
                >
                    <Link href={tier === 'free' ? '/subscription/plans' : '/subscription/portal'} prefetch>
                        {tier === 'free' ? 'Upgrade Plan' : 'Manage Billing'}
                    </Link>
                </Button>
            </div>
        </Card>
    );
}
