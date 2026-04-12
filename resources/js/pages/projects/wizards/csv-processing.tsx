import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { Loader2, AlertCircle, FileText } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'CSV Wizard', href: '/projects/create/csv' },
    { title: 'Processing', href: '#' },
];

interface Session {
    id: number;
    status: string;
    project_id: number;
}

interface Progress {
    total: number | null;
    processed: number;
    pending: number | null;
    failed: number;
}

interface ProcessingProps {
    session: Session;
    urls: {
        status: string;
        result: string;
        project: string;
    };
    error?: string;
}

export default function CsvWizardProcessing({ session, urls, error }: ProcessingProps) {
    const [statusMessage, setStatusMessage] = useState('Preparing...');
    const [progress, setProgress] = useState<Progress>({ total: null, processed: 0, pending: null, failed: 0 });
    const [runtimeError, setRuntimeError] = useState<string | undefined>(error);

    const debug = (...args: any[]) => {
        if (!import.meta.env.DEV) return;

        console.log('[CsvWizardProcessing]', ...args);
    };

    useEffect(() => {
        setRuntimeError(error);

        const initialMessages: Record<string, string> = {
            pending: 'Preparing your batch...',
            generating: 'Generating your visuals...',
            completed: 'Complete! Opening your project...',
            failed: 'Generation failed',
        };

        setStatusMessage(initialMessages[session.status] || 'Processing...');

        debug('mount/update', {
            session,
            urls,
            hasError: !!error,
        });

        if (error) return;

        const interval = setInterval(() => {
            fetch(urls.status)
                .then((res) => res.json())
                .then((data) => {
                    debug('poll: status response', data);
                    if (data?.progress) {
                        const nextProgress = {
                            total: data.progress.total ?? null,
                            processed: data.progress.processed ?? 0,
                            pending: data.progress.pending ?? null,
                            failed: data.progress.failed ?? 0,
                        };

                        setProgress(nextProgress);

                        if (nextProgress.total && nextProgress.total > 0) {
                            setStatusMessage(
                                `Generated ${nextProgress.processed} of ${nextProgress.total} visuals...`,
                            );
                        } else if ((data?.status ?? session.status) === 'pending') {
                            setStatusMessage('Preparing your batch...');
                        } else {
                            setStatusMessage('Generating your visuals...');
                        }
                    }

                    if (data?.is_completed) {
                        const projectUrl = urls.project.includes('?')
                            ? `${urls.project}&batchCompleted=1`
                            : `${urls.project}?batchCompleted=1`;

                        debug('poll: completed -> redirect to project', { url: projectUrl });
                        clearInterval(interval);
                        router.visit(projectUrl, {
                            replace: true,
                            preserveScroll: true,
                        });
                    } else if (data?.is_failed) {
                        debug('poll: failed', { message: data?.error_message });
                        clearInterval(interval);
                        setRuntimeError(data?.error_message || 'Batch generation failed.');
                    }
                })
                .catch((e) => {
                    debug('poll: error', e);
                    // keep UI calm; next poll will retry
                });
        }, 3000);

        return () => clearInterval(interval);
    }, [session.status, urls.status, urls.project, error]);

    const progressPercent =
        progress.total && progress.total > 0
            ? Math.round((progress.processed / progress.total) * 100)
            : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Processing - CSV Wizard" />

            <div className="flex min-h-[60vh] items-center justify-center px-4">
                <div className="w-full max-w-lg rounded-lg border bg-card p-6 text-center shadow-sm">
                    {runtimeError ? (
                        <>
                            <div className="bg-destructive/10 text-destructive mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full">
                                <AlertCircle className="h-8 w-8" />
                            </div>
                            <h1 className="mb-3 text-2xl font-bold">Generation Failed</h1>
                            <p className="text-muted-foreground mb-6">{runtimeError}</p>
                            <div className="flex flex-wrap justify-center gap-3">
                                <button
                                    onClick={() => router.visit('/projects/create/csv')}
                                    className="rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    Try Again
                                </button>
                                <button
                                    onClick={() => router.visit(urls.project)}
                                    className="rounded-md border px-6 py-2 text-sm font-medium hover:bg-muted"
                                >
                                    View Project
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="bg-primary/10 text-primary mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full">
                                <Loader2 className="h-8 w-8 animate-spin" />
                            </div>

                            <h1 className="mb-2 text-2xl font-bold">Generating Your Batch</h1>
                            <p className="text-muted-foreground mb-4">{statusMessage}</p>

                            <div className="bg-muted mx-auto mt-4 h-2 w-full overflow-hidden rounded-full">
                                {progressPercent === null ? (
                                    <div className="bg-primary h-full animate-pulse rounded-full" />
                                ) : (
                                    <div
                                        className="bg-primary h-full rounded-full transition-all duration-500"
                                        style={{ width: `${Math.max(5, Math.min(100, progressPercent))}%` }}
                                    />
                                )}
                            </div>

                            {progressPercent !== null && (
                                <p className="text-muted-foreground mt-2 text-xs">{progressPercent}% complete</p>
                            )}

                            <div className="mt-5 grid grid-cols-3 gap-3 text-left text-sm">
                                <div className="rounded-md border p-3">
                                    <div className="text-muted-foreground flex items-center gap-2">
                                        <FileText className="h-4 w-4" />
                                        Total
                                    </div>
                                    <div className="mt-1 text-lg font-semibold">{progress.total ?? '—'}</div>
                                </div>
                                <div className="rounded-md border p-3">
                                    <div className="text-muted-foreground">Processed</div>
                                    <div className="mt-1 text-lg font-semibold">{progress.processed}</div>
                                </div>
                                <div className="rounded-md border p-3">
                                    <div className="text-muted-foreground">Failed</div>
                                    <div className="mt-1 text-lg font-semibold">{progress.failed}</div>
                                </div>
                            </div>

                            <p className="text-muted-foreground mt-5 text-xs">
                                Project #{session.project_id}
                            </p>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
