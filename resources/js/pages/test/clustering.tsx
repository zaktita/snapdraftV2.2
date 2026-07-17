import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    Download,
    ImageIcon,
    Layers,
    Loader2,
    Sparkles,
    Target,
    Upload,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Reference = {
    id: number;
    url: string;
    thumbnail_url: string | null;
    order: number;
};

type ClusterImage = {
    order: number;
    url: string;
    thumbnail_url: string | null;
    is_anchor: boolean;
};

type Cluster = {
    id: number;
    key: string;
    label: string;
    summary: string | null;
    keywords: string[];
    images: ClusterImage[];
};

type ClusterResult = {
    globalAnalysis?: string;
    globalRules?: string[];
    clusters?: Array<{
        name?: string;
        tags?: string[];
        reason?: string;
        imageIndices?: number[];
        dominantColor?: string;
        typographyStyle?: string;
        compositionType?: string;
        backgroundTreatment?: string;
        palette?: string[];
        textPlacement?: string;
    }>;
};

type ProjectData = {
    id: number;
    name: string;
    dna_extracted_at: string | null;
};

type CaptionMatch = {
    caption: string;
    selected_key: string;
    selected_label: string;
    scores: Record<string, number>;
    used_model_fallback: boolean;
    top_score: number;
    second_score: number;
    selected_images: Array<{
        cluster_image_id: number;
        brand_reference_id: number | null;
        order: number | null;
        url: string | null;
        thumbnail_url: string | null;
        is_anchor: boolean;
    }>;
    matched_at: string;
};

type ClusterScores = {
    scores: Record<string, number>;
    selected_key: string | null;
    used_model_fallback: boolean;
    top_score: number;
    second_score: number;
};

type PromptGeneration = {
    raw_text?: string;
    analysis_prose?: string | null;
    tweaks?: string[];
    prompt_json?: Record<string, unknown> | null;
    compiled_prompt?: string | null;
    json_valid?: boolean;
    json_validation_errors?: string[];
    cluster_key?: string;
    cluster_label?: string | null;
    cluster_scores?: ClusterScores;
    selected_cluster_images?: Array<{
        brand_reference_id?: number;
        url?: string | null;
        label?: string | null;
        is_anchor?: boolean;
    }>;
    system_prompt?: string;
    model_slug?: string;
    tokens_in?: number;
    tokens_out?: number;
    estimated_cost_usd?: number | null;
    latency_ms?: number;
    caption_used?: string;
    format?: string;
    aspect_ratio?: string;
    reference_count?: number;
    generated_at?: string;
};

type ImageGeneration = {
    status?: string;
    image_id?: number;
    image_url?: string | null;
    thumbnail_url?: string | null;
    cluster_key?: string;
    cluster_label?: string | null;
    model?: string;
    aspect_ratio?: string;
    format?: string;
    resolution_multiplier?: number;
    reference_count?: number;
    sent_cluster_images?: Array<{
        cluster_image_id?: number;
        brand_reference_id?: number;
        url?: string | null;
        is_anchor?: boolean;
    }>;
    image_request_prompt?: string | null;
    width?: number | null;
    height?: number | null;
    generated_at?: string;
};

type GeneratedImage = {
    id: number;
    url: string;
    thumbnail_url: string | null;
};

const formatOptions = [
    { value: '1:1', label: 'Square (1:1)' },
    { value: '4:5', label: 'Portrait (4:5)' },
    { value: '9:16', label: 'Portrait / Story (9:16)' },
    { value: '16:9', label: 'Landscape (16:9)' },
];

interface PageProps {
    project: ProjectData | null;
    references: Reference[];
    clusters: Cluster[];
    clusterResult: ClusterResult | null;
    dnaJson: Record<string, unknown> | null;
    dnaSummary: string | null;
    captionMatch: CaptionMatch | null;
    promptGeneration: PromptGeneration | null;
    imageGeneration: ImageGeneration | null;
    generatedImage: GeneratedImage | null;
}

function DebugBlock({
    title,
    children,
    defaultOpen = false,
}: {
    title: string;
    children: React.ReactNode;
    defaultOpen?: boolean;
}) {
    return (
        <Collapsible defaultOpen={defaultOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left text-sm font-medium hover:bg-muted/50">
                {title}
                <ChevronDown className="h-4 w-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2">{children}</CollapsibleContent>
        </Collapsible>
    );
}

function JsonBlock({ data }: { data?: Record<string, unknown> | ClusterResult | CaptionMatch | PromptGeneration | ImageGeneration | null }) {
    if (!data) return <p className="text-muted-foreground text-sm">-</p>;
    return (
        <pre className="bg-muted max-h-96 overflow-auto rounded-md p-3 text-xs">
            {JSON.stringify(data, null, 2)}
        </pre>
    );
}

function PreBlock({ content }: { content?: string | null }) {
    if (!content) return <p className="text-muted-foreground text-sm">-</p>;
    return (
        <pre className="bg-muted max-h-96 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">
            {content}
        </pre>
    );
}

function ValidationBadge({ valid, errors }: { valid?: boolean; errors?: string[] }) {
    if (valid === undefined) return null;
    return valid ? (
        <Badge className="bg-green-100 text-green-800 border-green-300">
            <CheckCircle2 className="mr-1 h-3 w-3" /> JSON valid
        </Badge>
    ) : (
        <Badge variant="destructive">
            <AlertCircle className="mr-1 h-3 w-3" /> {errors?.[0] ?? 'Invalid JSON'}
        </Badge>
    );
}

function MetricsRow({ data }: { data?: PromptGeneration }) {
    if (!data) return null;
    return (
        <div className="text-muted-foreground flex flex-wrap gap-3 text-xs">
            {data.model_slug && <span>Model: <strong className="text-foreground">{data.model_slug}</strong></span>}
            {data.latency_ms !== undefined && <span>Latency: <strong className="text-foreground">{data.latency_ms}ms</strong></span>}
            {data.tokens_in !== undefined && <span>In: <strong className="text-foreground">{data.tokens_in}</strong></span>}
            {data.tokens_out !== undefined && <span>Out: <strong className="text-foreground">{data.tokens_out}</strong></span>}
            {data.estimated_cost_usd != null && (
                <span>Cost: <strong className="text-foreground">${data.estimated_cost_usd.toFixed(4)}</strong></span>
            )}
        </div>
    );
}

export default function ClusteringTest({
    project,
    references = [],
    clusters = [],
    clusterResult,
    dnaJson,
    dnaSummary,
    captionMatch,
    promptGeneration,
    imageGeneration,
    generatedImage,
}: PageProps) {
    const page = usePage<{ errors?: Record<string, string>; flash?: { success?: string } }>();
    const [localFiles, setLocalFiles] = useState<File[]>([]);
    const [localPreviews, setLocalPreviews] = useState<string[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const uploadForm = useForm({
        brand_name: '',
        reference_images: [] as File[],
    });

    const matchForm = useForm({
        caption: captionMatch?.caption ?? '',
    });

    const promptForm = useForm({
        caption: captionMatch?.caption ?? promptGeneration?.caption_used ?? '',
        format: promptGeneration?.format ?? '1:1',
    });

    const imageForm = useForm({
        resolution_multiplier: String(imageGeneration?.resolution_multiplier ?? 1),
    });

    const handleFiles = useCallback((files: FileList | null) => {
        if (!files) return;
        const arr = Array.from(files).slice(0, 10);
        setLocalFiles(arr);
        setLocalPreviews(arr.map((f) => URL.createObjectURL(f)));
        uploadForm.setData('reference_images', arr);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleAnalyze = (e: FormEvent) => {
        e.preventDefault();
        uploadForm.post('/test/clustering/analyze', { forceFormData: true });
    };

    const handleMatchCaption = (e: FormEvent) => {
        e.preventDefault();
        if (!project) return;
        matchForm.post(`/test/clustering/${project.id}/match`);
    };

    const handleGeneratePrompt = (e: FormEvent) => {
        e.preventDefault();
        if (!project) return;
        promptForm.post(`/test/clustering/${project.id}/generate-prompt`);
    };

    const handleGenerateImage = (e: FormEvent) => {
        e.preventDefault();
        if (!project) return;
        imageForm.post(`/test/clustering/${project.id}/generate-image`);
    };

    const hasResults = Boolean(project && clusters.length > 0);
    const globalError = page.props.errors?.error;
    const successMessage = page.props.flash?.success;
    const displayImageUrl = generatedImage?.url ?? imageGeneration?.image_url ?? null;
    const sentReferences = imageGeneration?.sent_cluster_images ?? [];

    useEffect(() => {
        if (captionMatch?.caption) {
            matchForm.setData('caption', captionMatch.caption);
            promptForm.setData('caption', captionMatch.caption);
        }
    }, [captionMatch?.caption]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Clustering Test', href: '/test/clustering' },
            ]}
        >
            <Head title="Clustering Test Lab" />

            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Layers className="h-6 w-6 text-primary" />
                            <h1 className="text-2xl font-bold">Clustering Test Lab</h1>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Upload → cluster &amp; DNA → match caption → generate prompt → generate image
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a href="/test/prompt-forge">PromptForge Lab →</a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a href="/test/cluster-generation">Legacy Cluster Gen →</a>
                        </Button>
                    </div>
                </div>

                {globalError && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {globalError}
                    </div>
                )}

                {successMessage && (
                    <div className="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                        {successMessage}
                    </div>
                )}

                {displayImageUrl && hasResults && (
                    <Card className="overflow-hidden border-green-200 bg-green-50/30">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                                Generated image
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div className="space-y-3">
                                    <p className="text-sm font-semibold">Output</p>
                                    <div className="overflow-hidden rounded-lg border bg-white">
                                        <img
                                            src={displayImageUrl}
                                            alt="Generated post"
                                            className="mx-auto max-h-[560px] w-full object-contain"
                                        />
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={displayImageUrl} download={`clustering-test-${project?.id ?? 'result'}.png`}>
                                                <Download className="mr-2 h-4 w-4" />
                                                Download
                                            </a>
                                        </Button>
                                        {generatedImage?.id && (
                                            <Badge variant="outline">Image #{generatedImage.id}</Badge>
                                        )}
                                        {imageGeneration?.cluster_label && (
                                            <Badge variant="outline">Cluster: {imageGeneration.cluster_label}</Badge>
                                        )}
                                        {imageGeneration?.model && (
                                            <Badge variant="outline">{imageGeneration.model}</Badge>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-sm font-semibold">
                                            Reference images sent
                                            {imageGeneration?.reference_count !== undefined && (
                                                <span className="text-muted-foreground ml-1 font-normal">
                                                    ({imageGeneration.reference_count})
                                                </span>
                                            )}
                                        </p>
                                        {sentReferences.length > 0 ? (
                                            <div className="grid grid-cols-3 gap-2">
                                                {sentReferences.map((ref, i) => (
                                                    <div
                                                        key={ref.cluster_image_id ?? ref.brand_reference_id ?? i}
                                                        className="relative aspect-square overflow-hidden rounded border-2 border-green-300 bg-white"
                                                    >
                                                        {ref.url ? (
                                                            <img src={ref.url} alt="" className="h-full w-full object-cover" />
                                                        ) : (
                                                            <div className="bg-muted flex h-full items-center justify-center text-xs">No URL</div>
                                                        )}
                                                        {ref.is_anchor && (
                                                            <span className="absolute left-1 top-1 rounded bg-black/60 px-1 text-[10px] text-white">anchor</span>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">No reference images recorded.</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-sm font-semibold">Prompt sent to image model</p>
                                        {imageGeneration?.image_request_prompt ? (
                                            <pre className="bg-muted max-h-80 overflow-auto rounded-md border p-3 text-xs whitespace-pre-wrap">
                                                {imageGeneration.image_request_prompt}
                                            </pre>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">-</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {!hasResults && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="h-5 w-5" />
                                Upload Reference Images
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleAnalyze} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="brand_name">Brand name (optional)</Label>
                                    <Input
                                        id="brand_name"
                                        placeholder="e.g. Acme Coffee Co."
                                        value={uploadForm.data.brand_name}
                                        onChange={(e) => uploadForm.setData('brand_name', e.target.value)}
                                    />
                                </div>

                                <div
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 transition-colors',
                                        dragOver
                                            ? 'border-primary bg-primary/5'
                                            : 'border-muted-foreground/30 hover:border-primary/40',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setDragOver(true);
                                    }}
                                    onDragLeave={() => setDragOver(false)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragOver(false);
                                        handleFiles(e.dataTransfer.files);
                                    }}
                                >
                                    <Upload className="text-muted-foreground mb-3 h-10 w-10" />
                                    <p className="font-medium">Drop images here or click to browse</p>
                                    <p className="text-muted-foreground text-sm">
                                        2–10 images (JPEG, PNG, WebP) - max 10 MB each
                                    </p>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        multiple
                                        className="hidden"
                                        onChange={(e) => handleFiles(e.target.files)}
                                    />
                                </div>

                                {localPreviews.length > 0 && (
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                                        {localPreviews.map((src, i) => (
                                            <div key={src} className="relative overflow-hidden rounded-lg border">
                                                <img src={src} alt={localFiles[i]?.name ?? `Image ${i + 1}`} className="aspect-square w-full object-cover" />
                                                <Badge className="absolute top-2 left-2 text-xs" variant="secondary">
                                                    #{i}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {uploadForm.errors.reference_images && (
                                    <p className="text-sm text-red-600">{uploadForm.errors.reference_images}</p>
                                )}

                                <Button
                                    type="submit"
                                    disabled={uploadForm.processing || localFiles.length < 2}
                                    className="w-full sm:w-auto"
                                >
                                    {uploadForm.processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Clustering &amp; analyzing DNA…
                                        </>
                                    ) : (
                                        <>
                                            <Sparkles className="mr-2 h-4 w-4" />
                                            Run clustering analysis
                                        </>
                                    )}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {hasResults && (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">{project?.name}</h2>
                                <p className="text-muted-foreground text-sm">
                                    {clusters.length} cluster{clusters.length !== 1 ? 's' : ''} from {references.length} images
                                    {project?.dna_extracted_at && (
                                        <> · analyzed {new Date(project.dna_extracted_at).toLocaleString()}</>
                                    )}
                                </p>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <a href="/test/clustering">Upload new set</a>
                            </Button>
                        </div>

                        {clusterResult?.globalAnalysis && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Global analysis</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed">{clusterResult.globalAnalysis}</p>
                                </CardContent>
                            </Card>
                        )}

                        {clusterResult?.globalRules && clusterResult.globalRules.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Global rules (locked)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-1 text-sm">
                                        {clusterResult.globalRules.map((rule) => (
                                            <li key={rule} className="flex gap-2">
                                                <span className="text-muted-foreground">•</span>
                                                {rule}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            {clusters.map((cluster) => {
                                const isSelected = captionMatch?.selected_key === cluster.key;
                                const score = captionMatch?.scores[cluster.key];

                                return (
                                <Card
                                    key={cluster.id}
                                    className={cn(
                                        isSelected && 'border-primary ring-2 ring-primary/20',
                                    )}
                                >
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle className="text-base">{cluster.label}</CardTitle>
                                            <div className="flex flex-wrap justify-end gap-1">
                                                {isSelected && (
                                                    <Badge className="bg-primary text-primary-foreground">
                                                        <Target className="mr-1 h-3 w-3" />
                                                        Matched
                                                    </Badge>
                                                )}
                                                <Badge variant="outline">{cluster.key}</Badge>
                                            </div>
                                        </div>
                                        {score !== undefined && (
                                            <p className="text-muted-foreground text-xs">
                                                Keyword score: <strong className="text-foreground">{(score * 100).toFixed(1)}%</strong>
                                            </p>
                                        )}
                                        {cluster.summary && (
                                            <p className="text-muted-foreground text-sm">{cluster.summary}</p>
                                        )}
                                        {cluster.keywords.length > 0 && (
                                            <div className="flex flex-wrap gap-1 pt-1">
                                                {cluster.keywords.map((kw) => (
                                                    <Badge key={kw} variant="secondary" className="text-xs">
                                                        {kw}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid grid-cols-3 gap-2">
                                            {cluster.images.map((img) => (
                                                <div key={`${cluster.id}-${img.order}`} className="relative overflow-hidden rounded-md border">
                                                    <img
                                                        src={img.thumbnail_url ?? img.url}
                                                        alt={`Reference ${img.order}`}
                                                        className="aspect-square w-full object-cover"
                                                    />
                                                    <Badge
                                                        className="absolute top-1 left-1 text-[10px]"
                                                        variant={img.is_anchor ? 'default' : 'secondary'}
                                                    >
                                                        #{img.order}
                                                        {img.is_anchor ? ' · anchor' : ''}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                                );
                            })}
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Target className="h-5 w-5" />
                                    Step 2 - Match caption to cluster
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <form onSubmit={handleMatchCaption} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="caption">Caption / post topic</Label>
                                        <Textarea
                                            id="caption"
                                            placeholder="e.g. Summer workshop registration is now open - sign up today!"
                                            rows={3}
                                            value={matchForm.data.caption}
                                            onChange={(e) => matchForm.setData('caption', e.target.value)}
                                            required
                                        />
                                        {matchForm.errors.caption && (
                                            <p className="text-sm text-red-600">{matchForm.errors.caption}</p>
                                        )}
                                        <p className="text-muted-foreground text-xs">
                                            Uses keyword scoring on cluster labels/keywords; falls back to{' '}
                                            <code className="text-foreground">gpt-4o</code> via OpenRouter when ambiguous.
                                        </p>
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={matchForm.processing || !matchForm.data.caption.trim()}
                                    >
                                        {matchForm.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Matching caption…
                                            </>
                                        ) : (
                                            <>
                                                <Target className="mr-2 h-4 w-4" />
                                                Match caption
                                            </>
                                        )}
                                    </Button>
                                </form>

                                {captionMatch && (
                                    <div className="space-y-4 border-t pt-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge className="bg-green-100 text-green-800 border-green-300">
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                {captionMatch.selected_label}
                                            </Badge>
                                            {captionMatch.used_model_fallback && (
                                                <Badge variant="outline">LLM tie-break used</Badge>
                                            )}
                                            <span className="text-muted-foreground text-xs">
                                                Top score {(captionMatch.top_score * 100).toFixed(1)}%
                                                {captionMatch.second_score > 0 && (
                                                    <> · runner-up {(captionMatch.second_score * 100).toFixed(1)}%</>
                                                )}
                                            </span>
                                        </div>

                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">Matched caption</p>
                                            <p className="text-muted-foreground text-sm">{captionMatch.caption}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Reference images for this cluster
                                                <span className="text-muted-foreground ml-1 font-normal">
                                                    ({captionMatch.selected_images.length})
                                                </span>
                                            </p>
                                            {captionMatch.selected_images.length > 0 ? (
                                                <div className="grid grid-cols-3 gap-2 sm:max-w-md">
                                                    {captionMatch.selected_images.map((img) => (
                                                        <div
                                                            key={img.cluster_image_id}
                                                            className="relative overflow-hidden rounded-md border-2 border-green-300"
                                                        >
                                                            {img.url ? (
                                                                <img
                                                                    src={img.thumbnail_url ?? img.url}
                                                                    alt={`Reference ${img.order}`}
                                                                    className="aspect-square w-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="bg-muted flex aspect-square items-center justify-center text-xs">
                                                                    No URL
                                                                </div>
                                                            )}
                                                            <Badge
                                                                className="absolute top-1 left-1 text-[10px]"
                                                                variant={img.is_anchor ? 'default' : 'secondary'}
                                                            >
                                                                #{img.order}
                                                                {img.is_anchor ? ' · anchor' : ''}
                                                            </Badge>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-muted-foreground text-sm">No images assigned.</p>
                                            )}
                                        </div>

                                        <DebugBlock title="Match debug (scores)">
                                            <JsonBlock data={captionMatch} />
                                        </DebugBlock>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Sparkles className="h-5 w-5" />
                                    Step 3 - Generate post prompt from DNA
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    Uses <code className="text-xs">OpenRouterCsvPostGenerator</code> with brand DNA JSON,
                                    matched cluster references, and the PromptForge post schema.
                                </p>

                                <form onSubmit={handleGeneratePrompt} className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2 sm:col-span-2">
                                            <Label htmlFor="prompt_caption">Caption</Label>
                                            <Textarea
                                                id="prompt_caption"
                                                rows={3}
                                                value={promptForm.data.caption}
                                                onChange={(e) => promptForm.setData('caption', e.target.value)}
                                                placeholder="Uses Step 2 caption if left unchanged"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="format">Format</Label>
                                            <Select
                                                value={promptForm.data.format}
                                                onValueChange={(v) => promptForm.setData('format', v)}
                                            >
                                                <SelectTrigger id="format">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {formatOptions.map((o) => (
                                                        <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={promptForm.processing || !dnaJson}
                                    >
                                        {promptForm.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Generating post prompt…
                                            </>
                                        ) : (
                                            <>
                                                <Sparkles className="mr-2 h-4 w-4" />
                                                Generate post prompt
                                            </>
                                        )}
                                    </Button>
                                </form>

                                {promptGeneration && (
                                    <div className="space-y-4 border-t pt-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <ValidationBadge
                                                valid={promptGeneration.json_valid}
                                                errors={promptGeneration.json_validation_errors}
                                            />
                                            {promptGeneration.cluster_label && (
                                                <Badge variant="outline">Cluster: {promptGeneration.cluster_label}</Badge>
                                            )}
                                            {promptGeneration.cluster_scores?.used_model_fallback && (
                                                <Badge variant="secondary">LLM cluster fallback</Badge>
                                            )}
                                            <MetricsRow data={promptGeneration} />
                                        </div>

                                        {promptGeneration.cluster_scores && (
                                            <DebugBlock title="Cluster keyword scores" defaultOpen>
                                                <div className="space-y-2">
                                                    {Object.entries(promptGeneration.cluster_scores.scores).map(([key, score]) => (
                                                        <div key={key} className="flex items-center gap-2 text-sm">
                                                            <span className="w-40 shrink-0 truncate font-mono text-xs">{key}</span>
                                                            <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                                <div
                                                                    className="bg-primary h-full rounded-full"
                                                                    style={{ width: `${Math.min(score * 100, 100)}%` }}
                                                                />
                                                            </div>
                                                            <span className="w-12 text-right text-xs">{score.toFixed(3)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </DebugBlock>
                                        )}

                                        <DebugBlock title="On-brand check" defaultOpen>
                                            <PreBlock content={promptGeneration.analysis_prose} />
                                        </DebugBlock>

                                        <DebugBlock title="Compiled image prompt" defaultOpen>
                                            <PreBlock content={promptGeneration.compiled_prompt} />
                                        </DebugBlock>

                                        <DebugBlock title="Post prompt JSON">
                                            <JsonBlock data={promptGeneration.prompt_json ?? null} />
                                        </DebugBlock>

                                        {promptGeneration.tweaks && promptGeneration.tweaks.length > 0 && (
                                            <DebugBlock title="Tweaks">
                                                <ul className="list-inside list-disc text-sm">
                                                    {promptGeneration.tweaks.map((t, i) => <li key={i}>{t}</li>)}
                                                </ul>
                                            </DebugBlock>
                                        )}

                                        {promptGeneration.selected_cluster_images && promptGeneration.selected_cluster_images.length > 0 && (
                                            <DebugBlock title="Cluster references sent to LLM">
                                                <div className="grid grid-cols-3 gap-2 sm:max-w-md">
                                                    {promptGeneration.selected_cluster_images.map((img, i) => (
                                                        <div key={img.brand_reference_id ?? i} className="relative overflow-hidden rounded-md border">
                                                            {img.url ? (
                                                                <img src={img.url} alt="" className="aspect-square w-full object-cover" />
                                                            ) : (
                                                                <div className="bg-muted flex aspect-square items-center justify-center text-xs">No URL</div>
                                                            )}
                                                            {img.is_anchor && (
                                                                <span className="absolute left-1 top-1 rounded bg-black/60 px-1 text-[10px] text-white">anchor</span>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </DebugBlock>
                                        )}

                                        <DebugBlock title="Raw LLM response">
                                            <PreBlock content={promptGeneration.raw_text} />
                                        </DebugBlock>

                                        <DebugBlock title="System prompt (generate_post)">
                                            <PreBlock content={promptGeneration.system_prompt} />
                                        </DebugBlock>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Zap className="h-5 w-5" />
                                    Step 4 - Generate image
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    Sends the Step 3 post JSON + cluster reference images to{' '}
                                    <code className="text-xs">GeminiCsvImageGenerator</code>{' '}
                                    (same path as <code className="text-xs">GenerateSingleImageJob</code>).
                                </p>

                                {promptGeneration && (
                                    <div className="flex flex-wrap gap-2 text-sm">
                                        <Badge variant="outline">Cluster: {promptGeneration.cluster_label ?? promptGeneration.cluster_key}</Badge>
                                        <Badge variant="outline">Format: {promptGeneration.format ?? '1:1'}</Badge>
                                        <Badge variant="outline">Refs: {promptGeneration.reference_count ?? 0}</Badge>
                                    </div>
                                )}

                                <form onSubmit={handleGenerateImage} className="space-y-4">
                                    <div className="space-y-2 max-w-xs">
                                        <Label htmlFor="resolution_multiplier">Resolution</Label>
                                        <Select
                                            value={imageForm.data.resolution_multiplier}
                                            onValueChange={(v) => imageForm.setData('resolution_multiplier', v)}
                                        >
                                            <SelectTrigger id="resolution_multiplier">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">1x (default)</SelectItem>
                                                <SelectItem value="2">2x</SelectItem>
                                                <SelectItem value="4">4x</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={imageForm.processing || !promptGeneration?.prompt_json}
                                    >
                                        {imageForm.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Generating image…
                                            </>
                                        ) : (
                                            <>
                                                <Zap className="mr-2 h-4 w-4" />
                                                Generate image
                                            </>
                                        )}
                                    </Button>
                                </form>

                                {imageGeneration && (
                                    <div className="space-y-3 border-t pt-4 text-sm">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge className="bg-green-100 text-green-800 border-green-300">
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                {imageGeneration.status ?? 'completed'}
                                            </Badge>
                                            {imageGeneration.width && imageGeneration.height && (
                                                <Badge variant="outline">
                                                    {imageGeneration.width}×{imageGeneration.height}
                                                </Badge>
                                            )}
                                            {imageGeneration.aspect_ratio && (
                                                <Badge variant="outline">{imageGeneration.aspect_ratio}</Badge>
                                            )}
                                        </div>

                                        <DebugBlock title="Image generation debug">
                                            <JsonBlock data={imageGeneration} />
                                        </DebugBlock>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {dnaSummary && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">DNA summary</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <pre className="text-sm whitespace-pre-wrap">{dnaSummary}</pre>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">All uploaded references</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                                    {references.map((ref) => (
                                        <div key={ref.id} className="relative overflow-hidden rounded-lg border">
                                            <img
                                                src={ref.thumbnail_url ?? ref.url}
                                                alt={`Reference ${ref.order}`}
                                                className="aspect-square w-full object-cover"
                                            />
                                            <Badge className="absolute top-2 left-2 text-xs" variant="secondary">
                                                #{ref.order}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <div className="space-y-3">
                            <DebugBlock title="DNA JSON">
                                <JsonBlock data={dnaJson} />
                            </DebugBlock>
                            <DebugBlock title="Raw cluster result (Gemini)">
                                <JsonBlock data={clusterResult} />
                            </DebugBlock>
                        </div>
                    </>
                )}

                {!hasResults && !uploadForm.processing && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <ImageIcon className="text-muted-foreground mb-3 h-12 w-12" />
                            <p className="text-muted-foreground text-sm">
                                Results will show clusters, assigned images, global rules, and DNA JSON
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
