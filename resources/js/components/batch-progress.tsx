import { Progress } from '@/components/ui/progress';
import { Card } from '@/components/ui/card';
import { AlertTriangle, CheckCircle, ChevronDown, ChevronUp, Loader2, XCircle } from 'lucide-react';
import { useState } from 'react';

interface FailureReason {
    title?: string | null;
    message: string;
}

interface BatchProgressProps {
    total: number;
    completed: number;
    failed: number;
    status: 'processing' | 'completed' | 'partial' | 'failed';
    currentItem?: string;
    failures?: FailureReason[];
}

export function BatchProgress({
    total,
    completed,
    failed,
    status,
    currentItem,
    failures = [],
}: BatchProgressProps) {
    const [showFailures, setShowFailures] = useState(false);
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
                        {status === 'partial' && (
                            <>
                                <AlertTriangle className="h-5 w-5 text-yellow-600" />
                                <span className="font-semibold text-yellow-600">
                                    Partially Complete - {failed} image{failed !== 1 ? 's' : ''} failed
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
                        <p className={`text-2xl font-bold ${failed > 0 ? 'text-destructive' : 'text-muted-foreground'}`}>{failed}</p>
                        <p className="text-xs text-muted-foreground">Failed</p>
                    </div>
                </div>

                {/* Estimated Time (if processing) */}
                {status === 'processing' && remaining > 0 && (
                    <p className="text-center text-xs text-muted-foreground">
                        Estimated time remaining: {Math.ceil(remaining * 2 / 60)} minutes
                    </p>
                )}

                {/* Failure details (collapsible) */}
                {failures.length > 0 && (status === 'partial' || status === 'failed') && (
                    <div className="border-t pt-3">
                        <button
                            type="button"
                            onClick={() => setShowFailures((v) => !v)}
                            className="flex w-full items-center justify-between text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <span>View failure details ({failures.length})</span>
                            {showFailures ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </button>

                        {showFailures && (
                            <ul className="mt-3 space-y-2">
                                {failures.map((f, i) => (
                                    <li key={i} className="rounded-md bg-destructive/10 px-3 py-2 text-sm">
                                        {f.title && (
                                            <span className="font-medium text-foreground">{f.title}: </span>
                                        )}
                                        <span className="text-muted-foreground">{f.message}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </Card>
    );
}
