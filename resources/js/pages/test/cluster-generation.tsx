import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    Download,
    Eye,
    ImageIcon,
    Layers,
    Loader2,
    Sparkles,
    Target,
    Upload,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

// ─── Types ────────────────────────────────────────────────────────────────────

type StoredRef = { path: string; url: string; name: string; index: number };

type StyleCluster = {
    cluster_id: number;
    name: string;
    image_indices: number[];
    coherence_score: number;
    dominant_colors: string[];
    typography_style: string;
    mood: string;
    layout_pattern: string;
};

type ImageMeta = {
    index: number;
    cluster_id?: number;
    role?: string;
    layout_complexity?: 'simple' | 'moderate' | 'complex';
    quality?: 'excellent' | 'good' | 'usable' | 'poor';
    url?: string;
    name?: string;
    elements_detected?: Record<string, Record<string, boolean | number | string>>;
    assessment?: {
        primary_colors?: string[];
        dominant_layout?: string;
        typography_visible?: string[];
    };
    notes?: string;
};

type BrandAnalysis = {
    style_clusters?: StyleCluster[];
    image_analysis?: ImageMeta[];
    images?: ImageMeta[];
    brand_dna?: { brand_positioning?: { one_line_summary?: string } };
    coherence_analysis?: { overall_coherence_score?: number };
    coherence_score?: number;
    generation_guidance?: { dos?: string[]; donts?: string[] };
};

type GenImage = {
    image_base64?: string;
    image_data?: string;
    mime_type?: string;
    metadata?: { model?: string; generation_time_ms?: number };
};

type GenerationResult = {
    cluster_id: number | null;
    selected_cluster: StyleCluster | null;
    selected_indices: number[];
    selected_image_meta: ImageMeta[];
    simple_prompt: string;
    generation_prompt: string;
    model_used: string;
    image: GenImage;
    generation_ms: number;
    format: string;
    caption: string;
};

interface PageProps {
    analysis?: BrandAnalysis | null;
    references?: StoredRef[];
    generation_result?: GenerationResult | null;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const qualityColor: Record<string, string> = {
    excellent: 'bg-green-100 text-green-800 border-green-300',
    good:      'bg-blue-100 text-blue-800 border-blue-300',
    usable:    'bg-yellow-100 text-yellow-800 border-yellow-300',
    poor:      'bg-red-100 text-red-800 border-red-300',
};

const complexityColor: Record<string, string> = {
    simple:   'bg-gray-100 text-gray-700 border-gray-300',
    moderate: 'bg-purple-100 text-purple-800 border-purple-300',
    complex:  'bg-orange-100 text-orange-800 border-orange-300',
};

function coherenceClass(score: number): string {
    if (score >= 90) return 'text-green-600';
    if (score >= 70) return 'text-yellow-600';
    return 'text-red-500';
}

function countTrueElements(elements?: Record<string, Record<string, boolean | number | string>>): number {
    if (!elements) return 0;
    let count = 0;
    Object.values(elements).forEach(category => {
        if (category && typeof category === 'object') {
            Object.values(category).forEach(v => { if (v === true) count++; });
        }
    });
    return count;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ClusterGenerationTest({
    analysis,
    references = [],
    generation_result,
}: PageProps) {
    // ── Phase 1: Upload form ──────────────────────────────────────────────────
    const [localFiles, setLocalFiles] = useState<File[]>([]);
    const [localPreviews, setLocalPreviews] = useState<string[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const uploadForm = useForm<{ reference_images: File[] }>({
        reference_images: [],
    });

    const handleFiles = useCallback((files: FileList | null) => {
        if (!files) return;
        const arr = Array.from(files).slice(0, 10);
        setLocalFiles(arr);
        setLocalPreviews(arr.map(f => URL.createObjectURL(f)));
        uploadForm.setData('reference_images', arr);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleAnalyze = (e: FormEvent) => {
        e.preventDefault();
        uploadForm.post('/test/cluster-generation/analyze', { forceFormData: true });
    };

    // ── Phase 2→3: Generate form ──────────────────────────────────────────────
    const generateForm = useForm<{
        caption: string;
        format: string;
        analysis_json: string;
        reference_paths: string[];
    }>({
        caption: generation_result?.caption ?? '',
        format:  generation_result?.format  ?? 'square',
        analysis_json:   '',
        reference_paths: [],
    });

    // Sync hidden fields whenever analysis/references props update (after analyze POST)
    useEffect(() => {
        if (analysis) {
            generateForm.setData('analysis_json', JSON.stringify(analysis));
        }
    }, [analysis]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (references?.length) {
            generateForm.setData('reference_paths', references.map(r => r.path));
        }
    }, [references]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleGenerate = (e: FormEvent) => {
        e.preventDefault();
        generateForm.post('/test/cluster-generation/generate');
    };

    // ── Derived helpers ───────────────────────────────────────────────────────
    const imagesByIndex = useMemo<Record<number, ImageMeta>>(() => {
        const list = analysis?.image_analysis ?? analysis?.images ?? [];
        return Object.fromEntries(list.map(img => [img.index, img]));
    }, [analysis]);

    const refsByIndex = useMemo<Record<number, StoredRef>>(() => (
        Object.fromEntries(references.map(r => [r.index, r]))
    ), [references]);

    const clusters    = analysis?.style_clusters ?? [];
    const allImages   = analysis?.image_analysis ?? analysis?.images ?? [];
    const overallCoherence =
        analysis?.coherence_analysis?.overall_coherence_score ?? analysis?.coherence_score;

    const genImgSrc = generation_result?.image
        ? `data:${generation_result.image.mime_type ?? 'image/png'};base64,${
              generation_result.image.image_data ?? generation_result.image.image_base64
          }`
        : null;

    const handleDownload = () => {
        if (!genImgSrc) return;
        const a = document.createElement('a');
        a.href = genImgSrc;
        a.download = `cluster-test-${Date.now()}.png`;
        a.click();
    };

    // ─── Render ───────────────────────────────────────────────────────────────
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Cluster Generation Test', href: '/test/cluster-generation' },
            ]}
        >
            <Head title="Cluster Generation Test" />

            <div className="mx-auto max-w-6xl space-y-6 p-6">

                {/* ── Header ── */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <Layers className="h-6 w-6 text-primary" />
                            <h1 className="text-2xl font-bold">Cluster Generation Test Lab</h1>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Visualize style clusters → AI reference selection → generation prompt → output image
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href="/test/prompt-forge">PromptForge Test Lab →</a>
                    </Button>
                </div>

                {/* ══════════════════════════════════════════════════════════════
                    PHASE 1 - Upload
                ══════════════════════════════════════════════════════════════ */}
                {!analysis && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="h-5 w-5" />
                                Step 1 - Upload Brand Reference Images
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleAnalyze} className="space-y-4">
                                {/* Drop zone */}
                                <div
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 transition-colors',
                                        dragOver
                                            ? 'border-primary bg-primary/5'
                                            : 'border-muted-foreground/30 hover:border-primary/40',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={e => { e.preventDefault(); setDragOver(true); }}
                                    onDragLeave={() => setDragOver(false)}
                                    onDrop={e => {
                                        e.preventDefault();
                                        setDragOver(false);
                                        handleFiles(e.dataTransfer.files);
                                    }}
                                >
                                    <Upload className="text-muted-foreground mb-3 h-10 w-10" />
                                    <p className="font-medium">Drop images here or click to browse</p>
                                    <p className="text-muted-foreground text-sm">
                                        PNG, JPG, WebP · 2–10 images · Max 10 MB each
                                    </p>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,image/webp"
                                        className="hidden"
                                        onChange={e => handleFiles(e.target.files)}
                                    />
                                </div>

                                {/* Thumbnail preview */}
                                {localPreviews.length > 0 && (
                                    <div className="grid grid-cols-5 gap-2">
                                        {localPreviews.map((src, i) => (
                                            <div key={i} className="relative aspect-square overflow-hidden rounded border">
                                                <img src={src} alt={localFiles[i]?.name} className="h-full w-full object-cover" />
                                                <p className="absolute bottom-0 left-0 right-0 truncate bg-black/50 px-1 py-0.5 text-[10px] text-white">
                                                    #{i} {localFiles[i]?.name}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {uploadForm.errors.reference_images && (
                                    <p className="flex items-center gap-1 text-sm text-red-600">
                                        <AlertCircle className="h-4 w-4" />
                                        {uploadForm.errors.reference_images}
                                    </p>
                                )}

                                <Button
                                    type="submit"
                                    disabled={localFiles.length < 2 || uploadForm.processing}
                                    className="w-full"
                                >
                                    {uploadForm.processing ? (
                                        <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Analysing images…</>
                                    ) : (
                                        <><Sparkles className="mr-2 h-4 w-4" />Analyze {localFiles.length > 0 ? localFiles.length : ''} Images</>
                                    )}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* ══════════════════════════════════════════════════════════════
                    PHASE 2 + 3 - Analysis view
                ══════════════════════════════════════════════════════════════ */}
                {analysis && (
                    <>
                        {/* ── Stats bar ── */}
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-muted-foreground text-xs uppercase tracking-wide">Clusters</p>
                                    <p className="text-3xl font-bold">{clusters.length}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-muted-foreground text-xs uppercase tracking-wide">Images</p>
                                    <p className="text-3xl font-bold">{allImages.length}</p>
                                </CardContent>
                            </Card>
                            {overallCoherence !== undefined && (
                                <Card>
                                    <CardContent className="pt-4">
                                        <p className="text-muted-foreground text-xs uppercase tracking-wide">Coherence</p>
                                        <p className={cn('text-3xl font-bold', coherenceClass(overallCoherence))}>
                                            {overallCoherence}%
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                            <Card>
                                <CardContent className="pt-4">
                                    <p className="text-muted-foreground text-xs uppercase tracking-wide">Brand Summary</p>
                                    <p className="truncate text-sm font-medium leading-snug">
                                        {analysis.brand_dna?.brand_positioning?.one_line_summary ?? '-'}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* ══════════════════════════════════════════════════════
                            PHASE 3 - Generation result
                        ══════════════════════════════════════════════════════ */}
                        {generation_result && (
                            <Card className="border-green-300 bg-green-50/40">
                                <CardHeader>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                                        <CardTitle>Generation Result</CardTitle>
                                        {generation_result.selected_cluster && (
                                            <Badge className="border-green-300 bg-green-100 text-green-800">
                                                Cluster {generation_result.cluster_id}: {generation_result.selected_cluster.name}
                                            </Badge>
                                        )}
                                        <div className="ml-auto flex gap-2">
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {generation_result.model_used}
                                            </Badge>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {generation_result.generation_ms}ms
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-6">

                                    {/* Generated image + selected references */}
                                    <div className="grid gap-6 lg:grid-cols-2">
                                        {/* Generated image */}
                                        <div className="space-y-2">
                                            <p className="text-sm font-semibold">Generated Image</p>
                                            {genImgSrc ? (
                                                <div className="relative">
                                                    <img
                                                        src={genImgSrc}
                                                        alt="Generated"
                                                        className="w-full rounded-lg border shadow-sm"
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="absolute right-2 top-2 bg-white/90 backdrop-blur"
                                                        onClick={handleDownload}
                                                    >
                                                        <Download className="mr-1 h-3 w-3" />Save
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="flex h-56 items-center justify-center rounded border text-sm text-gray-400">
                                                    No image returned
                                                </div>
                                            )}
                                        </div>

                                        {/* Selected reference images */}
                                        <div className="space-y-2">
                                            <p className="text-sm font-semibold">
                                                Reference Images Used
                                                <span className="text-muted-foreground ml-1 font-normal">
                                                    ({generation_result.selected_indices.length} from cluster {generation_result.cluster_id})
                                                </span>
                                            </p>
                                            {generation_result.selected_image_meta.length > 0 ? (
                                                <div className="grid grid-cols-3 gap-3">
                                                    {generation_result.selected_image_meta.map(img => (
                                                        <div key={img.index} className="space-y-1">
                                                            <div className="aspect-square overflow-hidden rounded border-2 border-green-400 ring-2 ring-green-200">
                                                                {img.url ? (
                                                                    <img
                                                                        src={img.url}
                                                                        alt={img.name}
                                                                        className="h-full w-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <div className="flex h-full items-center justify-center bg-gray-100">
                                                                        <ImageIcon className="h-6 w-6 text-gray-400" />
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <p className="truncate text-xs font-medium">#{img.index} {img.name}</p>
                                                            <div className="flex flex-wrap gap-0.5">
                                                                {img.quality && (
                                                                    <span className={cn('rounded border px-1 text-[10px]', qualityColor[img.quality] ?? '')}>
                                                                        {img.quality}
                                                                    </span>
                                                                )}
                                                                {img.layout_complexity && (
                                                                    <span className={cn('rounded border px-1 text-[10px]', complexityColor[img.layout_complexity] ?? '')}>
                                                                        {img.layout_complexity}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p className="text-[10px] text-gray-400">
                                                                {countTrueElements(img.elements_detected)} elements
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-400">No metadata available for selected images.</p>
                                            )}
                                        </div>
                                    </div>

                                    <Separator />

                                    {/* Prompt comparison */}
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        {/* Simple prompt (PromptGeneratorService output) */}
                                        <div className="space-y-1.5">
                                            <div className="flex items-center gap-2">
                                                <Zap className="h-4 w-4 text-yellow-500" />
                                                <p className="text-sm font-semibold">Simple Prompt</p>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                Text content extracted for the image (headline, subtext, CTA)
                                            </p>
                                            <pre className="max-h-44 overflow-y-auto rounded border bg-yellow-50 p-3 text-xs leading-relaxed whitespace-pre-wrap break-words">
                                                {generation_result.simple_prompt}
                                            </pre>
                                        </div>

                                        {/* Full generation prompt */}
                                        <div className="space-y-1.5">
                                            <div className="flex items-center gap-2">
                                                <Target className="h-4 w-4 text-blue-500" />
                                                <p className="text-sm font-semibold">Full Generation Prompt</p>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                Brand-locked prompt sent to the Gemini image model
                                            </p>
                                            <pre className="max-h-44 overflow-y-auto rounded border bg-blue-50 p-3 text-xs leading-relaxed whitespace-pre-wrap break-words">
                                                {generation_result.generation_prompt}
                                            </pre>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* ── Generate / Re-generate form ── */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Sparkles className="h-5 w-5" />
                                    {generation_result ? 'Try Another Caption' : 'Step 2 - Generate Image'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleGenerate} className="space-y-4">
                                    <div className="space-y-1.5">
                                        <label className="text-sm font-medium">Caption / Content</label>
                                        <Textarea
                                            value={generateForm.data.caption}
                                            onChange={e => generateForm.setData('caption', e.target.value)}
                                            placeholder="Describe what should appear on the image…"
                                            rows={3}
                                        />
                                        {generateForm.errors.caption && (
                                            <p className="text-sm text-red-600">{generateForm.errors.caption}</p>
                                        )}
                                    </div>

                                    <div className="flex items-end gap-4">
                                        <div className="space-y-1.5">
                                            <label className="text-sm font-medium">Format</label>
                                            <Select
                                                value={generateForm.data.format}
                                                onValueChange={v => generateForm.setData('format', v)}
                                            >
                                                <SelectTrigger className="w-44">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="square">Square (1:1)</SelectItem>
                                                    <SelectItem value="portrait">Portrait (9:16)</SelectItem>
                                                    <SelectItem value="landscape">Landscape (16:9)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={!generateForm.data.caption.trim() || generateForm.processing}
                                            className="flex-1"
                                        >
                                            {generateForm.processing ? (
                                                <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Generating…</>
                                            ) : (
                                                <><Sparkles className="mr-2 h-4 w-4" />Generate Image</>
                                            )}
                                        </Button>
                                    </div>

                                    {generateForm.errors.analysis_json && (
                                        <p className="flex items-center gap-1 text-sm text-red-600">
                                            <AlertCircle className="h-4 w-4" />
                                            {generateForm.errors.analysis_json}
                                        </p>
                                    )}
                                </form>
                            </CardContent>
                        </Card>

                        {/* ── Style Clusters ── */}
                        {clusters.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Layers className="h-5 w-5" />
                                        Style Clusters
                                        {generation_result?.cluster_id != null && (
                                            <span className="text-muted-foreground ml-2 text-sm font-normal">
                                                - cluster {generation_result.cluster_id} was selected
                                            </span>
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Tabs defaultValue={String(clusters[0]?.cluster_id ?? 0)}>
                                        <TabsList className="h-auto flex-wrap gap-1 p-1">
                                            {clusters.map(cluster => {
                                                const isChosen = generation_result?.cluster_id === cluster.cluster_id;
                                                return (
                                                    <TabsTrigger
                                                        key={cluster.cluster_id}
                                                        value={String(cluster.cluster_id)}
                                                        className={cn(
                                                            isChosen && 'border-2 border-green-400',
                                                        )}
                                                    >
                                                        {isChosen && (
                                                            <CheckCircle2 className="mr-1 h-3 w-3 text-green-600" />
                                                        )}
                                                        {cluster.name}
                                                        <span className={cn('ml-1.5 font-mono text-xs', coherenceClass(cluster.coherence_score))}>
                                                            {cluster.coherence_score}%
                                                        </span>
                                                    </TabsTrigger>
                                                );
                                            })}
                                        </TabsList>

                                        {clusters.map(cluster => {
                                            const isChosen = generation_result?.cluster_id === cluster.cluster_id;
                                            return (
                                                <TabsContent
                                                    key={cluster.cluster_id}
                                                    value={String(cluster.cluster_id)}
                                                    className="mt-4 space-y-4"
                                                >
                                                    {/* Cluster metadata row */}
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        {/* Color swatches */}
                                                        <div className="flex items-center gap-1">
                                                            {(cluster.dominant_colors ?? []).slice(0, 7).map((color, i) => (
                                                                <div
                                                                    key={i}
                                                                    className="h-6 w-6 rounded-full border shadow-sm"
                                                                    style={{ backgroundColor: color }}
                                                                    title={color}
                                                                />
                                                            ))}
                                                        </div>
                                                        <div className="h-5 w-px bg-border" />
                                                        {cluster.mood && <Badge variant="outline">{cluster.mood}</Badge>}
                                                        {cluster.layout_pattern && <Badge variant="outline">{cluster.layout_pattern}</Badge>}
                                                        {cluster.typography_style && <Badge variant="outline">{cluster.typography_style}</Badge>}
                                                        <div className="ml-auto flex items-center gap-3">
                                                            <Badge variant="secondary">{cluster.image_indices.length} images</Badge>
                                                            <span className={cn('font-mono text-sm font-semibold', coherenceClass(cluster.coherence_score))}>
                                                                {cluster.coherence_score}% coherence
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Selected-cluster banner */}
                                                    {isChosen && (
                                                        <div className="flex items-center gap-2 rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700">
                                                            <CheckCircle2 className="h-4 w-4 shrink-0" />
                                                            AI selected this cluster for the last generation
                                                        </div>
                                                    )}

                                                    {/* Images in this cluster */}
                                                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                                                        {cluster.image_indices.map(idx => {
                                                            const ref  = refsByIndex[idx];
                                                            const meta = imagesByIndex[idx];
                                                            const isSelected = generation_result?.selected_indices?.includes(idx);
                                                            return (
                                                                <div key={idx} className="space-y-1">
                                                                    <div className={cn(
                                                                        'aspect-square overflow-hidden rounded border-2 transition-all',
                                                                        isSelected
                                                                            ? 'border-green-400 ring-2 ring-green-300'
                                                                            : 'border-muted',
                                                                    )}>
                                                                        {ref?.url ? (
                                                                            <img
                                                                                src={ref.url}
                                                                                alt={ref.name}
                                                                                className="h-full w-full object-cover"
                                                                            />
                                                                        ) : (
                                                                            <div className="flex h-full items-center justify-center bg-muted">
                                                                                <ImageIcon className="h-6 w-6 text-muted-foreground" />
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    <p className="truncate text-xs font-medium">
                                                                        #{idx} {ref?.name}
                                                                    </p>
                                                                    <div className="flex flex-wrap gap-0.5">
                                                                        {meta?.quality && (
                                                                            <span className={cn('rounded border px-1 text-[10px]', qualityColor[meta.quality] ?? '')}>
                                                                                {meta.quality}
                                                                            </span>
                                                                        )}
                                                                        {meta?.layout_complexity && (
                                                                            <span className={cn('rounded border px-1 text-[10px]', complexityColor[meta.layout_complexity] ?? '')}>
                                                                                {meta.layout_complexity}
                                                                            </span>
                                                                        )}
                                                                        {isSelected && (
                                                                            <span className="rounded border border-green-300 bg-green-100 px-1 text-[10px] text-green-700">
                                                                                ✓ used
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <p className="text-[10px] text-muted-foreground">
                                                                        {countTrueElements(meta?.elements_detected)} elements
                                                                    </p>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </TabsContent>
                                            );
                                        })}
                                    </Tabs>
                                </CardContent>
                            </Card>
                        )}

                        {/* ── All Images Overview ── */}
                        {allImages.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Eye className="h-5 w-5" />
                                        All Images Overview
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                                        {allImages.map(img => {
                                            const ref        = refsByIndex[img.index];
                                            const cluster    = clusters.find(c => c.cluster_id === img.cluster_id);
                                            const isSelected = generation_result?.selected_indices?.includes(img.index);
                                            return (
                                                <div key={img.index} className="space-y-1">
                                                    <div className={cn(
                                                        'aspect-square overflow-hidden rounded border-2 transition-all',
                                                        isSelected
                                                            ? 'border-green-400 ring-2 ring-green-300'
                                                            : 'border-muted',
                                                    )}>
                                                        {ref?.url ? (
                                                            <img
                                                                src={ref.url}
                                                                alt={ref.name}
                                                                className="h-full w-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex h-full items-center justify-center bg-muted">
                                                                <ImageIcon className="h-6 w-6 text-muted-foreground" />
                                                            </div>
                                                        )}
                                                    </div>
                                                    <p className="truncate text-xs font-medium">#{img.index} {ref?.name}</p>
                                                    {cluster && (
                                                        <p className="truncate text-[10px] text-muted-foreground">→ {cluster.name}</p>
                                                    )}
                                                    <div className="flex flex-wrap gap-0.5">
                                                        {img.quality && (
                                                            <span className={cn('rounded border px-1 text-[10px]', qualityColor[img.quality] ?? '')}>
                                                                {img.quality}
                                                            </span>
                                                        )}
                                                        {img.layout_complexity && (
                                                            <span className={cn('rounded border px-1 text-[10px]', complexityColor[img.layout_complexity] ?? '')}>
                                                                {img.layout_complexity}
                                                            </span>
                                                        )}
                                                        {isSelected && (
                                                            <span className="rounded border border-green-300 bg-green-100 px-1 text-[10px] text-green-700">
                                                                ✓ used
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        {countTrueElements(img.elements_detected)} elements
                                                    </p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* ── Generation Guidance ── */}
                        {(analysis.generation_guidance?.dos?.length || analysis.generation_guidance?.donts?.length) && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {analysis.generation_guidance.dos && analysis.generation_guidance.dos.length > 0 && (
                                    <Card className="border-green-200">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm text-green-700">✓ DOs</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="space-y-1">
                                                {analysis.generation_guidance.dos.map((d, i) => (
                                                    <li key={i} className="text-xs text-green-800">{d}</li>
                                                ))}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}
                                {analysis.generation_guidance.donts && analysis.generation_guidance.donts.length > 0 && (
                                    <Card className="border-red-200">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="text-sm text-red-700">✗ DON'Ts</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="space-y-1">
                                                {analysis.generation_guidance.donts.map((d, i) => (
                                                    <li key={i} className="text-xs text-red-800">{d}</li>
                                                ))}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        )}

                        {/* ── Raw JSON ── */}
                        <Collapsible>
                            <CollapsibleTrigger asChild>
                                <Button variant="outline" className="w-full justify-between">
                                    Raw Analysis JSON
                                    <ChevronDown className="h-4 w-4" />
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <pre className="mt-2 max-h-96 overflow-auto rounded border bg-muted p-4 text-xs">
                                    {JSON.stringify(analysis, null, 2)}
                                </pre>
                            </CollapsibleContent>
                        </Collapsible>

                        {/* Start over */}
                        <div className="flex justify-center pb-4">
                            <Button
                                variant="ghost"
                                onClick={() => { window.location.href = '/test/cluster-generation'; }}
                            >
                                ↺ Start Over with New Images
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
