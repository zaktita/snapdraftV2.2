import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { GenerationDebugDialog, type GenerationDebugData } from '@/components/generation-debug-dialog';
import {
    AlertCircle,
    Bug,
    CheckCircle2,
    Download,
    Layers,
    Loader2,
    MoreHorizontal,
    Sparkles,
    Target,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type SessionData = {
    id: number;
    status: string;
    total_jobs: number;
    project_id: number;
    error_message: string | null;
};

type PipelineData = {
    phase: string;
    error: string | null;
    cluster_count: number;
    dna_extracted: boolean;
    progress: {
        total: number;
        matched: number;
        prompted: number;
        completed: number;
        failed: number;
        pending: number;
    };
};

type RowData = {
    index: number;
    title: string;
    caption: string;
    format: string;
    cluster_key: string | null;
    cluster_label: string | null;
    used_model_fallback: boolean;
    top_score: number | null;
    json_valid: boolean | null;
    history_status: string | null;
    error_message: string | null;
    image_url: string | null;
    thumbnail_url: string | null;
    has_prompt: boolean;
};

interface PageProps {
    session: SessionData;
    pipeline: PipelineData;
    rows: RowData[];
    completed?: boolean;
    urls: {
        status: string;
        result: string;
        project: string;
        row_debug: string;
    };
    lab?: {
        title: string;
        setup_href: string;
        breadcrumb_label: string;
    };
}

const PHASES = [
    { key: 'clustering', label: 'Cluster & DNA', icon: Layers, donePhases: ['clustering_done', 'matching', 'matching_done', 'prompts', 'prompts_done', 'images', 'complete'] },
    { key: 'matching', label: 'Match captions', icon: Target, donePhases: ['matching_done', 'prompts', 'prompts_done', 'images', 'complete'] },
    { key: 'prompts', label: 'Generate prompts', icon: Sparkles, donePhases: ['prompts_done', 'images', 'complete'] },
    { key: 'images', label: 'Generate images', icon: Zap, donePhases: ['complete'] },
] as const;

function phaseStatus(current: string, phaseKey: string, donePhases: readonly string[]) {
    if (current === 'failed') return 'failed';
    if (current === 'complete' || donePhases.includes(current)) return 'done';
    if (current === phaseKey || current.startsWith(phaseKey)) return 'active';
    return 'pending';
}

function rowDebugUrl(template: string, rowIndex: number): string {
    return template.replace('__ROW__', String(rowIndex));
}

export default function CsvClusterProcessing({
    session,
    pipeline: initialPipeline,
    rows: initialRows,
    completed = false,
    urls,
    lab,
}: PageProps) {
    const [pipeline, setPipeline] = useState(initialPipeline);
    const [rows, setRows] = useState(initialRows);
    const [sessionData, setSessionData] = useState(session);
    const [debugRow, setDebugRow] = useState<RowData | null>(null);
    const [debugData, setDebugData] = useState<GenerationDebugData | null>(null);
    const [debugLoading, setDebugLoading] = useState(false);
    const [debugError, setDebugError] = useState<string | null>(null);

    const openRowDebug = (row: RowData) => {
        setDebugRow(row);
        setDebugData(null);
        setDebugError(null);
        setDebugLoading(true);

        fetch(rowDebugUrl(urls.row_debug, row.index))
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load debug data');
                return res.json();
            })
            .then((data: GenerationDebugData) => setDebugData(data))
            .catch(() => setDebugError('Could not load debug data for this row.'))
            .finally(() => setDebugLoading(false));
    };

    const closeRowDebug = () => {
        setDebugRow(null);
        setDebugData(null);
        setDebugError(null);
    };

    const isComplete = completed || pipeline.phase === 'complete' || sessionData.status === 'completed';
    const isFailed = pipeline.phase === 'failed' || sessionData.status === 'failed';

    useEffect(() => {
        if (isComplete || isFailed) return;

        const interval = setInterval(() => {
            fetch(urls.status)
                .then((res) => res.json())
                .then((data) => {
                    if (data.pipeline) setPipeline(data.pipeline);
                    if (data.rows) setRows(data.rows);
                    if (data.session) setSessionData(data.session);

                    if (data.pipeline?.phase === 'complete' || data.session?.status === 'completed') {
                        router.visit(urls.result, { preserveState: false });
                    }
                })
                .catch(() => {});
        }, 2000);

        return () => clearInterval(interval);
    }, [urls.status, urls.result, isComplete, isFailed]);

    const completedImages = useMemo(
        () => rows.filter((r) => r.image_url),
        [rows],
    );

    const pageTitle = lab?.title ?? 'CSV Cluster Wizard';
    const setupHref = lab?.setup_href ?? '/projects/create/csv-cluster';
    const breadcrumbLabel = lab?.breadcrumb_label ?? 'CSV Cluster Wizard';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: breadcrumbLabel, href: setupHref },
                { title: `Session #${session.id}`, href: '#' },
            ]}
        >
            <Head title={completed ? `${pageTitle} - Complete` : `${pageTitle} - Processing`} />

            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold">
                        {completed ? 'Batch complete' : 'Generating your batch'}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Session #{sessionData.id} · {sessionData.total_jobs} row{sessionData.total_jobs !== 1 ? 's' : ''}
                    </p>
                </div>

                {(isFailed || sessionData.error_message || pipeline.error) && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {pipeline.error ?? sessionData.error_message ?? 'Generation failed'}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pipeline progress</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-4">
                            {PHASES.map(({ key, label, icon: Icon, donePhases }) => {
                                const status = phaseStatus(pipeline.phase, key, donePhases);
                                return (
                                    <div
                                        key={key}
                                        className={cn(
                                            'rounded-lg border p-4',
                                            status === 'active' && 'border-primary bg-primary/5',
                                            status === 'done' && 'border-green-300 bg-green-50/50',
                                            status === 'failed' && 'border-red-300 bg-red-50/50',
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            {status === 'active' ? (
                                                <Loader2 className="h-4 w-4 animate-spin text-primary" />
                                            ) : status === 'done' ? (
                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                            ) : (
                                                <Icon className="text-muted-foreground h-4 w-4" />
                                            )}
                                            <span className="text-sm font-medium">{label}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="text-muted-foreground mt-4 flex flex-wrap gap-3 text-xs">
                            <span>Phase: <strong className="text-foreground">{pipeline.phase}</strong></span>
                            {pipeline.dna_extracted && <Badge variant="outline">{pipeline.cluster_count} clusters</Badge>}
                            <span>Matched: {pipeline.progress.matched}/{pipeline.progress.total}</span>
                            <span>Prompts: {pipeline.progress.prompted}/{pipeline.progress.total}</span>
                            <span>Images: {pipeline.progress.completed}/{pipeline.progress.total}</span>
                            {pipeline.progress.failed > 0 && (
                                <span className="text-red-600">Failed: {pipeline.progress.failed}</span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {completedImages.length > 0 && (
                    <Card className={cn(isComplete && 'border-green-200 bg-green-50/30')}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                {isComplete ? (
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                ) : (
                                    <Loader2 className="h-5 w-5 animate-spin text-primary" />
                                )}
                                Generated images
                                {!isComplete && (
                                    <span className="text-muted-foreground text-sm font-normal">
                                        ({completedImages.length}/{rows.length})
                                    </span>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {completedImages.map((row) => (
                                    <div key={row.index} className="overflow-hidden rounded-lg border bg-white">
                                        <div className="relative">
                                            {row.image_url && (
                                                <img
                                                    src={row.thumbnail_url ?? row.image_url}
                                                    alt=""
                                                    className="aspect-square w-full object-cover"
                                                />
                                            )}
                                            <div className="absolute right-2 top-2 z-10">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="secondary"
                                                            size="icon"
                                                            className="h-7 w-7 rounded-full bg-white shadow-sm hover:bg-white"
                                                            aria-label="More options"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-44">
                                                        <DropdownMenuItem onClick={() => openRowDebug(row)}>
                                                            <Bug className="mr-2 h-3.5 w-3.5" />
                                                            Prompt & cluster
                                                        </DropdownMenuItem>
                                                        {row.image_url && (
                                                            <DropdownMenuItem asChild>
                                                                <a href={row.image_url} download>
                                                                    <Download className="mr-2 h-3.5 w-3.5" />
                                                                    Download
                                                                </a>
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>
                                        <div className="space-y-2 p-3 text-sm">
                                            <p className="font-medium truncate">{row.title}</p>
                                            {row.cluster_label && (
                                                <Badge variant="outline" className="text-xs">{row.cluster_label}</Badge>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {isComplete && (
                                <div className="mt-4">
                                    <Button asChild>
                                        <a href={urls.project}>Open full project</a>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {!isComplete && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Per-row status</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="p-2 text-left">#</th>
                                    <th className="p-2 text-left">Caption</th>
                                    <th className="p-2 text-left">Cluster</th>
                                    <th className="p-2 text-left">Prompt</th>
                                    <th className="p-2 text-left">Image</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.index} className="border-t">
                                        <td className="p-2">{row.index}</td>
                                        <td className="p-2 max-w-[200px] truncate" title={row.caption}>{row.caption}</td>
                                        <td className="p-2">
                                            {row.cluster_label ? (
                                                <div className="space-y-1">
                                                    <Badge variant="outline">{row.cluster_label}</Badge>
                                                    {row.used_model_fallback && (
                                                        <span className="text-muted-foreground block text-xs">LLM pick</span>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </td>
                                        <td className="p-2">
                                            {row.has_prompt ? (
                                                row.json_valid === false ? (
                                                    <Badge variant="destructive">Invalid</Badge>
                                                ) : (
                                                    <Badge className="bg-green-100 text-green-800">OK</Badge>
                                                )
                                            ) : (
                                                <Loader2 className="text-muted-foreground h-4 w-4 animate-spin" />
                                            )}
                                        </td>
                                        <td className="p-2">
                                            {row.image_url ? (
                                                <img src={row.thumbnail_url ?? row.image_url} alt="" className="h-10 w-10 rounded object-cover" />
                                            ) : row.history_status === 'failed' ? (
                                                <Badge variant="destructive">Failed</Badge>
                                            ) : (
                                                <Loader2 className="text-muted-foreground h-4 w-4 animate-spin" />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                )}

                <GenerationDebugDialog
                    open={debugRow !== null}
                    onOpenChange={(open) => !open && closeRowDebug()}
                    title={debugRow?.title ?? 'Generation debug'}
                    loading={debugLoading}
                    error={debugError}
                    data={debugData}
                />
            </div>
        </AppLayout>
    );
}
