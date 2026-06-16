import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import * as quickGenerate from '@/routes/quick-generate';
import { Loader2, Zap } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Quick Generate', href: quickGenerate.index().url },
    { title: 'Processing', href: '#' },
];

interface Session {
    id: number;
    status: string;
    caption: string;
}

interface DebugPayload {
    compiled_prompt?: string | null;
    prompt_json?: Record<string, unknown> | null;
    cluster_key?: string | null;
    error_message?: string | null;
}

interface ProcessingProps {
    session: Session;
    error?: string;
    debug?: DebugPayload;
}

export default function QuickGenerateProcessing({ session, error, debug }: ProcessingProps) {
    const [statusMessage, setStatusMessage] = useState('Preparing...');

    useEffect(() => {
        // Update status message based on session status
        const messages: Record<string, string> = {
            pending: 'Preparing your request...',
            analyzing: 'Analyzing brand DNA and extracting details...',
            generating: 'Generating your visual...',
            completed: 'Complete! Redirecting...',
            failed: 'Generation failed',
        };

        setStatusMessage(messages[session.status] || 'Processing...');

        // Poll status every 3 seconds if still processing
        if (!error && session.status !== 'completed' && session.status !== 'failed') {
            const interval = setInterval(() => {
                fetch(quickGenerate.status({ session: session.id }).url)
                    .then((res) => res.json())
                    .then((data) => {
                        if (data.is_completed) {
                            router.visit(quickGenerate.result({ session: session.id }).url);
                        } else if (data.is_failed) {
                            router.reload({ only: ['session', 'error'] });
                        }
                    })
                    .catch((err) => {
                        console.error('Status poll failed:', err);
                    });
            }, 3000);

            return () => clearInterval(interval);
        }
    }, [session.status, session.id, error]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Processing - Quick Generate" />

            <div className="flex min-h-[60vh] items-center justify-center px-4">
                <div className="text-center">
                    {error ? (
                        <>
                            <div className="bg-destructive/10 text-destructive mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full">
                                <Zap className="h-8 w-8" />
                            </div>
                            <h1 className="mb-4 text-2xl font-bold">Generation Failed</h1>
                            <p className="text-muted-foreground mb-6 max-w-md">{error ?? debug?.error_message}</p>
                            {debug?.compiled_prompt && (
                                <pre className="text-muted-foreground mx-auto mb-4 max-w-2xl overflow-auto rounded border p-3 text-left text-xs">
                                    {debug.compiled_prompt}
                                </pre>
                            )}
                            <button
                                onClick={() => router.visit(quickGenerate.index().url)}
                                className="hover:bg-primary/90 rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground"
                            >
                                Try Again
                            </button>
                        </>
                    ) : (
                        <>
                            <div className="bg-primary/10 text-primary mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full">
                                <Loader2 className="h-8 w-8 animate-spin" />
                            </div>
                            <h1 className="mb-4 text-2xl font-bold">Generating Your Visual</h1>
                            <p className="text-muted-foreground mb-2">{statusMessage}</p>
                            <p className="text-muted-foreground text-sm">
                                Caption: <span className="font-medium">{session.caption}</span>
                            </p>
                            <div className="bg-muted mx-auto mt-6 h-2 w-64 overflow-hidden rounded-full">
                                <div className="bg-primary h-full animate-pulse rounded-full"></div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
