import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import * as quickGenerate from '@/routes/quick-generate';
import { Download, Edit, RefreshCw, CheckCircle, ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Quick Generate', href: quickGenerate.index().url },
    { title: 'Result', href: '#' },
];

interface ClusterImageDebug {
    brand_reference_id?: number;
    url?: string;
    label?: string;
    is_anchor?: boolean;
}

interface DebugPayload {
    dna_summary?: string | null;
    dna_extracted_at?: string | null;
    cluster_key?: string | null;
    cluster_label?: string | null;
    prompt_json?: Record<string, unknown> | null;
    compiled_prompt?: string | null;
    selected_cluster_images?: ClusterImageDebug[];
    status?: string;
    error_message?: string | null;
}

interface Session {
    id: number;
    caption: string;
    format: string;
    extracted_title?: string;
    extracted_description?: string;
    cluster_key?: string;
    cluster_label?: string;
}

interface Image {
    id: number;
    url: string;
    metadata?: Record<string, unknown>;
}

interface BrandReference {
    id: number;
    url: string;
    order: number;
}

interface Project {
    id: number;
    name: string;
    format: string;
}

interface ResultProps {
    session: Session;
    project: Project;
    image: Image | null;
    references: BrandReference[];
    debug: DebugPayload;
}

function storageUrl(path: string): string {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    if (path.startsWith('/storage/')) return path;
    if (path.startsWith('storage/')) return `/${path}`;
    return `/storage/${path}`;
}

function DebugPanel({ debug }: { debug: DebugPayload }) {
    const [openJson, setOpenJson] = useState(false);
    const clusterImages = debug.selected_cluster_images ?? [];

    return (
        <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-6 dark:border-amber-900 dark:bg-amber-950/20">
            <h2 className="mb-1 text-lg font-semibold text-amber-900 dark:text-amber-100">Pipeline debug</h2>
            <p className="text-muted-foreground mb-4 text-sm">
                Step 1 DNA → Step 2 post JSON → cluster refs → Step 3 image
            </p>

            <div className="space-y-4">
                {debug.dna_extracted_at && (
                    <div>
                        <p className="text-xs font-medium uppercase text-muted-foreground">DNA extracted</p>
                        <p className="text-sm">{debug.dna_extracted_at}</p>
                        {debug.dna_summary && (
                            <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{debug.dna_summary}</p>
                        )}
                    </div>
                )}

                <div>
                    <p className="text-xs font-medium uppercase text-muted-foreground">Selected cluster</p>
                    <p className="font-medium">
                        {debug.cluster_label ?? '—'}{' '}
                        <span className="text-muted-foreground font-normal">({debug.cluster_key ?? 'n/a'})</span>
                    </p>
                </div>

                <div>
                    <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">
                        Reference images sent to image model ({clusterImages.length})
                    </p>
                    {clusterImages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">None recorded</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {clusterImages.map((img, i) => (
                                <div key={i} className="overflow-hidden rounded-md border bg-card">
                                    {img.url && (
                                        <img
                                            src={storageUrl(img.url)}
                                            alt={img.label ?? `Ref ${i + 1}`}
                                            className="aspect-square w-full object-cover"
                                        />
                                    )}
                                    <div className="p-2 text-xs">
                                        {img.is_anchor && (
                                            <span className="font-semibold text-amber-700 dark:text-amber-400">Anchor · </span>
                                        )}
                                        {img.label ?? `Image ${i + 1}`}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">Post JSON (sent to Nano Banana 2)</p>
                    <pre className="max-h-96 overflow-auto rounded-md border bg-card p-3 text-xs">
                        {JSON.stringify(debug.prompt_json ?? {}, null, 2)}
                    </pre>
                </div>

                <div>
                    <button
                        type="button"
                        onClick={() => setOpenJson(!openJson)}
                        className="flex items-center gap-1 text-sm font-medium"
                    >
                        {openJson ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        Compiled prompt (debug only, not sent)
                    </button>
                    {openJson && (
                        <pre className="mt-2 max-h-48 overflow-auto rounded-md border bg-card p-3 text-xs whitespace-pre-wrap">
                            {debug.compiled_prompt ?? '—'}
                        </pre>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function QuickGenerateResult({ session, project, image, references, debug }: ResultProps) {
    const handleDownload = () => {
        if (!image) return;
        const link = document.createElement('a');
        link.href = storageUrl(image.url);
        link.download = `${(session.extracted_title ?? 'visual').replace(/\s+/g, '_')}.png`;
        link.click();
    };

    const handleEditInCanvas = () => {
        if (!image) return;
        const encodedImageUrl = encodeURIComponent(image.url);
        const encodedTitle = encodeURIComponent(project.name);
        router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}&imageId=${image.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Result - Quick Generate" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                <div className="flex items-center gap-3 rounded-lg border bg-green-50 p-4 text-green-900 dark:bg-green-950 dark:text-green-100">
                    <CheckCircle className="h-6 w-6" />
                    <div>
                        <h2 className="font-semibold">Visual generated</h2>
                        <p className="text-sm opacity-90">Review the debug panel below to inspect prompts and cluster refs.</p>
                    </div>
                </div>

                <DebugPanel debug={debug} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold">Generated image (Step 3)</h2>
                            {image ? (
                                <div className="rounded-lg bg-muted p-4">
                                    <img
                                        src={storageUrl(image.url)}
                                        alt={session.extracted_title ?? 'Generated'}
                                        className="mx-auto max-h-[600px] w-full rounded-md object-contain"
                                    />
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">No image saved.</p>
                            )}

                            <div className="mt-6 flex flex-wrap gap-3">
                                <Button onClick={handleDownload} variant="default" disabled={!image}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                                <Button onClick={handleEditInCanvas} variant="outline" disabled={!image}>
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit in Canvas
                                </Button>
                                <Button onClick={() => router.visit(quickGenerate.index().url)} variant="outline">
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Generate another
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h3 className="mb-4 font-semibold">Caption</h3>
                            <p className="text-sm italic">{session.caption}</p>
                            <p className="text-muted-foreground mt-2 text-xs">Format: {session.format}</p>
                        </div>

                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h3 className="mb-4 font-semibold">All uploaded references</h3>
                            <div className="grid grid-cols-2 gap-2">
                                {references.map((ref) => (
                                    <div key={ref.id} className="aspect-square overflow-hidden rounded-md border">
                                        <img
                                            src={storageUrl(ref.url)}
                                            alt={`Reference ${ref.order + 1}`}
                                            className="h-full w-full object-cover"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
