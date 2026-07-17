import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { Crown } from 'lucide-react';

interface UpgradeAlertProps {
    title: string;
    message: string;
    currentTier: string;
    feature?: string;
}

export function UpgradeAlert({ title, message }: UpgradeAlertProps) {
    return (
        <Alert variant="brand" className="mb-6">
            <Crown className="h-4 w-4" />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="mt-2">
                <p className="mb-3">{message}</p>
                <Button asChild size="sm" className="gap-2">
                    <Link href="/subscription/plans">
                        View plans
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>
    );
}

interface TierLimitBadgeProps {
    currentValue: number;
    maxValue: number;
    label: string;
    unit?: string;
}

export function TierLimitBadge({ currentValue, maxValue, label, unit = 'items' }: TierLimitBadgeProps) {
    const percentage = (currentValue / maxValue) * 100;
    const isNearLimit = percentage >= 80;
    const isAtLimit = currentValue >= maxValue;

    return (
        <div className={`rounded-lg border p-3 ${isAtLimit ? 'border-destructive bg-destructive/5' :
                isNearLimit ? 'border-warning bg-warning/5' :
                    'border-border'
            }`}>
            <div className="flex items-center justify-between mb-1">
                <span className="text-sm font-medium">{label}</span>
                <span className={`text-xs font-mono ${isAtLimit ? 'text-destructive' :
                        isNearLimit ? 'text-warning' :
                            'text-muted-foreground'
                    }`}>
                    {currentValue} / {maxValue}
                </span>
            </div>
            <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                <div
                    className={`h-full transition-all ${isAtLimit ? 'bg-destructive' :
                            isNearLimit ? 'bg-warning' :
                                'bg-primary'
                        }`}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>
        </div>
    );
}
