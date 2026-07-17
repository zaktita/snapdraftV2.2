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
        subscription_plan_name?: string;
        credits_remaining?: number;
        credits_total?: number;
    };

    const creditsRemaining = user.credits_remaining ?? 0;
    const creditsTotal = user.credits_total ?? 10;
    const planName = user.subscription_plan_name ?? 'Beta';

    const isUnlimited = creditsTotal === 999999;
    const creditsUsed = isUnlimited ? 0 : Math.max(creditsTotal - creditsRemaining, 0);
    const usagePercent = isUnlimited || creditsTotal === 0 ? 0 : (creditsUsed / creditsTotal) * 100;

    return (
        <Card className="mx-2 gap-3 overflow-hidden rounded-2xl border-border bg-card p-4 shadow-none">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-1 text-muted-foreground">
                    <Info className="h-4 w-4" />
                    <p className="text-sm font-medium">{planName}</p>
                </div>
            </div>

            {!isUnlimited ? (
                <div>
                    <Progress
                        value={usagePercent}
                        className="h-1 bg-muted"
                        indicatorClassName="bg-primary"
                        aria-label="Credit usage"
                    />
                    <p className="mt-1 text-sm text-foreground">
                        <span className="font-semibold">{creditsUsed.toLocaleString()} / {creditsTotal.toLocaleString()}</span> credits used
                    </p>
                </div>
            ) : (
                <p className="text-sm text-foreground">Unlimited credits</p>
            )}

            <div>
                <Button asChild size="sm" className="w-full">
                    <Link href="/subscription/plans" prefetch>
                        View plans
                    </Link>
                </Button>
            </div>
        </Card>
    );
}
