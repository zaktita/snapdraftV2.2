import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { Coins, Sparkles } from 'lucide-react';

export function CreditsCard() {
    return (
        <Card className="mx-2 mb-2 overflow-hidden border-none bg-gradient-to-br from-amber-500 via-amber-600 to-orange-600 p-4 text-white shadow-lg">
            <div className="flex items-start justify-between">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <Coins className="size-5" />
                        <p className="text-sm font-medium opacity-90">
                            Credits
                        </p>
                    </div>
                    <p className="text-2xl font-bold">1,250</p>
                </div>
            </div>
            <Button
                asChild
                size="sm"
                className="w-full bg-white text-amber-700 hover:bg-white/90 hover:text-amber-800"
            >
                <Link href="/credits/upgrade" prefetch>
                    Upgrade Plan
                </Link>
            </Button>
        </Card>
    );
}
