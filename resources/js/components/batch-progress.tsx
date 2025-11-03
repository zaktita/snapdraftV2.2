import { Progress } from '@/components/ui/progress';
import { Card } from '@/components/ui/card';
import { CheckCircle, Loader2, XCircle } from 'lucide-react';

interface BatchProgressProps {
    total: number;
    completed: number;
    failed: number;
    status: 'processing' | 'completed' | 'failed';
    currentItem?: string;
}

export function BatchProgress({
    total,
    completed,
    failed,
    status,
    currentItem,
}: BatchProgressProps) {
    const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
    const remaining = total - completed - failed;

    return (
        <Card className="p-6">
            <div className="space-y-4">
                {/* Status Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        {status === 'processing' && (
                            <>
                                <Loader2 className="h-5 w-5 animate-spin text-primary" />
                                <span className="font-semibold">Generating Images...</span>
                            </>
                        )}
                        {status === 'completed' && (
                            <>
                                <CheckCircle className="h-5 w-5 text-green-600" />
                                <span className="font-semibold text-green-600">
                                    Generation Complete!
                                </span>
                            </>
                        )}
                        {status === 'failed' && (
                            <>
                                <XCircle className="h-5 w-5 text-destructive" />
                                <span className="font-semibold text-destructive">
                                    Generation Failed
                                </span>
                            </>
                        )}
                    </div>
                    <span className="text-sm text-muted-foreground">
                        {completed} / {total}
                    </span>
                </div>

                {/* Progress Bar */}
                <Progress value={percentage} className="h-2" />

                {/* Current Item */}
                {currentItem && status === 'processing' && (
                    <p className="text-sm text-muted-foreground">
                        Current: <span className="font-medium">{currentItem}</span>
                    </p>
                )}

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p className="text-2xl font-bold text-green-600">{completed}</p>
                        <p className="text-xs text-muted-foreground">Completed</p>
                    </div>
                    <div>
                        <p className="text-2xl font-bold">{remaining}</p>
                        <p className="text-xs text-muted-foreground">Remaining</p>
                    </div>
                    <div>
                        <p className="text-2xl font-bold text-destructive">{failed}</p>
                        <p className="text-xs text-muted-foreground">Failed</p>
                    </div>
                </div>

                {/* Estimated Time (if processing) */}
                {status === 'processing' && remaining > 0 && (
                    <p className="text-center text-xs text-muted-foreground">
                        Estimated time remaining: {Math.ceil(remaining * 2 / 60)} minutes
                    </p>
                )}
            </div>
        </Card>
    );
}
