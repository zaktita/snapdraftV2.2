import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    Download,
    FlaskConical,
    ImageIcon,
    Loader2,
    Sparkles,
    Upload,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

// ─── Types ────────────────────────────────────────────────────────────────────

type Reference = {
    id: number;
    url: string;
    thumbnail_url: string | null;
    order: number;
};

type Cluster = {
    id: number;
    key: string;
    label: string;
    summary: string | null;
    keywords: string[];
};

type StepMetrics = {
    model_slug?: string;
    tokens_in?: number;
    tokens_out?: number;
    estimated_cost_usd?: number | null;
    latency_ms?: number;
};

type Step1Debug = {
    status?: string;
    cluster_count?: number;
    dna_summary?: string | null;
    dna_extracted_at?: string | null;
};

type Step2Debug = StepMetrics & {
    raw_text?: string;
    analysis_prose?: string | null;
    tweaks?: string[];
    prompt_json?: Record<string, unknown> | null;
    json_valid?: boolean;
    json_validation_errors?: string[];
    cluster_key?: string;
    cluster_label?: string;
    cluster_scores?: {
        scores: Record<string, number>;
        selected_key: string | null;
        used_model_fallback: boolean;
        top_score: number;
        second_score: number;
    };
    selected_cluster_images?: Array<{
        brand_reference_id?: number;
        url?: string;
        label?: string;
        is_anchor?: boolean;
    }>;
    system_prompt?: string;
};

type Step3Debug = {
    status?: string;
    cluster_key?: string | null;
    prompt_json?: Record<string, unknown> | null;
    compiled_prompt?: string | null;
    image_request_prompt?: string | null;
    reference_count?: number;
    sent_cluster_images?: Array<{
        cluster_image_id?: number;
        brand_reference_id?: number;
        url?: string | null;
        is_anchor?: boolean;
    }>;
    sent_to_model?: string;
    model_slug?: string;
    image_url?: string | null;
    image_id?: number | null;
};

type DebugPayload = {
    pipeline_step?: string;
    step1?: Step1Debug;
    step2?: Step2Debug;
    step3?: Step3Debug;
};

type SessionData = {
    id: number;
    caption: string;
    format: string;
    status: string;
    error_message: string | null;
    cluster_key: string | null;
    cluster_label: string | null;
    prompt_json: Record<string, unknown> | null;
    compiled_prompt: string | null;
};

type ProjectData = {
    id: number;
    name: string;
    dna_summary: string | null;
    dna_extracted_at: string | null;
    dna_json: Record<string, unknown> | null;
};

type GeneratedImage = {
    id: number;
    url: string;
    thumbnail_url: string | null;
};

interface PageProps {
    session: SessionData | null;
    project: ProjectData | null;
    references: Reference[];
    clusters: Cluster[];
    debug: DebugPayload | null;
    generatedImage: GeneratedImage | null;
    errors?: Record<string, string>;
}

const formatOptions = [
    { value: '1:1', label: 'Square (1:1)' },
    { value: '4:5', label: 'Portrait (4:5)' },
    { value: '3:4', label: 'Portrait (3:4)' },
    { value: '2:3', label: 'Portrait (2:3)' },
    { value: '9:16', label: 'Portrait / Story (9:16)' },
    { value: '3:2', label: 'Landscape (3:2)' },
    { value: '4:3', label: 'Landscape (4:3)' },
    { value: '5:4', label: 'Landscape (5:4)' },
    { value: '16:9', label: 'Landscape (16:9)' },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function DebugBlock({ title, children, defaultOpen = false }: { title: string; children: React.ReactNode; defaultOpen?: boolean }) {
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

function MetricsRow({ data }: { data?: StepMetrics }) {
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

function PreBlock({ content }: { content?: string | null }) {
    if (!content) return <p className="text-muted-foreground text-sm">—</p>;
    return (
        <pre className="bg-muted max-h-96 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">
            {content}
        </pre>
    );
}

function JsonBlock({ data }: { data?: Record<string, unknown> | null }) {
    if (!data) return <p className="text-muted-foreground text-sm">—</p>;
    return (
        <pre className="bg-muted max-h-96 overflow-auto rounded-md p-3 text-xs">
            {JSON.stringify(data, null, 2)}
        </pre>
    );
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function PromptForgeTest({
    session,
    project,
    references = [],
    clusters = [],
    debug,
    generatedImage,
}: PageProps) {
    const page = usePage<{ errors?: Record<string, string>; flash?: { success?: string } }>();
    const [activeTab, setActiveTab] = useState('setup');
    const [localFiles, setLocalFiles] = useState<File[]>([]);
    const [localPreviews, setLocalPreviews] = useState<string[]>([]);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const setupForm = useForm({
        brand_name: project?.name ?? '',
        caption: session?.caption ?? '',
        format: session?.format ?? '1:1',
        reference_images: [] as File[],
    });

    const step1Form = useForm({});
    const step2Form = useForm({});
    const step3Form = useForm({});

    const handleFiles = useCallback((files: FileList | null) => {
        if (!files) return;
        const arr = Array.from(files).slice(0, 10);
        setLocalFiles(arr);
        setLocalPreviews(arr.map((f) => URL.createObjectURL(f)));
        setupForm.setData('reference_images', arr);
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const handleSetup = (e: FormEvent) => {
        e.preventDefault();
        setupForm.post('/test/prompt-forge', { forceFormData: true });
    };

    const runStep1 = () => {
        if (!session) return;
        step1Form.post(`/test/prompt-forge/${session.id}/extract`);
    };

    const runStep2 = () => {
        if (!session) return;
        step2Form.post(`/test/prompt-forge/${session.id}/generate-post`);
    };

    const runStep3 = () => {
        if (!session) return;
        step3Form.post(`/test/prompt-forge/${session.id}/generate-image`);
    };

    const step1Done = debug?.pipeline_step === 'step1_complete' || Boolean(debug?.step1);
    const step2Done = debug?.pipeline_step === 'step2_complete' || Boolean(debug?.step2);
    const step3Done = debug?.pipeline_step === 'step3_complete' || Boolean(debug?.step3);

    const displayImageUrl =
        generatedImage?.url
        ?? (debug?.step3?.image_url ? debug.step3.image_url : null);

    const sentReferences = debug?.step3?.sent_cluster_images ?? [];
    const promptSent =
        debug?.step3?.image_request_prompt
        ?? (debug?.step3?.prompt_json
            ? JSON.stringify(debug.step3.prompt_json, null, 2)
            : session?.prompt_json
                ? JSON.stringify(session.prompt_json, null, 2)
                : null);

    useEffect(() => {
        if (!session) {
            setActiveTab('setup');
            return;
        }

        const step = debug?.pipeline_step;
        if (step === 'step3_complete') {
            setActiveTab('step3');
        } else if (step === 'step2_complete') {
            setActiveTab('step2');
        } else if (step === 'step1_complete') {
            setActiveTab('step1');
        } else if (step === 'setup_complete') {
            setActiveTab('step1');
        }
    }, [session?.id, debug?.pipeline_step]);

    const globalError = page.props.errors?.error;
    const successMessage = page.props.flash?.success;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'PromptForge Test Lab', href: '/test/prompt-forge' },
            ]}
        >
            <Head title="PromptForge Test Lab" />

            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <FlaskConical className="h-6 w-6 text-primary" />
                            <h1 className="text-2xl font-bold">PromptForge Test Lab</h1>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Step-by-step PromptForge pipeline — Extract DNA → Post JSON → Image generation
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href="/test/cluster-generation">Cluster Generation Test →</a>
                    </Button>
                    {session && (
                        <Button variant="outline" size="sm" asChild>
                            <a href="/test/prompt-forge">New session</a>
                        </Button>
                    )}
                </div>

                {globalError && (
                    <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {globalError}
                    </div>
                )}

                {successMessage && (
                    <div className="flex items-start gap-2 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                        {successMessage}
                    </div>
                )}

                {session?.error_message && !globalError && (
                    <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {session.error_message}
                    </div>
                )}

                {session && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">Session #{session.id}</Badge>
                        <Badge variant="outline">{project?.name}</Badge>
                        <Badge variant={session.status === 'completed' ? 'default' : 'secondary'}>
                            {session.status}
                        </Badge>
                        {debug?.pipeline_step && (
                            <Badge variant="outline">{debug.pipeline_step}</Badge>
                        )}
                    </div>
                )}

                {displayImageUrl && (
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
                                            <a href={displayImageUrl} download={`prompt-forge-${session?.id ?? 'result'}.png`}>
                                                <Download className="mr-2 h-4 w-4" />Download
                                            </a>
                                        </Button>
                                        {generatedImage?.id && (
                                            <Badge variant="outline">Image #{generatedImage.id}</Badge>
                                        )}
                                        {debug?.step3?.cluster_key && (
                                            <Badge variant="outline">Cluster: {debug.step3.cluster_key}</Badge>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-sm font-semibold">
                                            Reference images sent
                                            {debug?.step3?.reference_count !== undefined && (
                                                <span className="text-muted-foreground ml-1 font-normal">
                                                    ({debug.step3.reference_count})
                                                </span>
                                            )}
                                        </p>
                                        {sentReferences.length > 0 ? (
                                            <div className="grid grid-cols-3 gap-2">
                                                {sentReferences.map((ref, i) => (
                                                    <div key={ref.cluster_image_id ?? ref.brand_reference_id ?? i} className="relative aspect-square overflow-hidden rounded border-2 border-green-300 bg-white">
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
                                            <p className="text-muted-foreground text-sm">No reference images recorded for this run.</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-sm font-semibold">Prompt sent to image model</p>
                                        {promptSent ? (
                                            <pre className="bg-muted max-h-80 overflow-auto rounded-md border p-3 text-xs whitespace-pre-wrap">
                                                {promptSent}
                                            </pre>
                                        ) : (
                                            <p className="text-muted-foreground text-sm">Prompt not available — re-run Step 3 to capture it.</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="setup">Setup</TabsTrigger>
                        <TabsTrigger value="step1" disabled={!session}>Step 1 — DNA</TabsTrigger>
                        <TabsTrigger value="step2" disabled={!step1Done}>Step 2 — Post JSON</TabsTrigger>
                        <TabsTrigger value="step3" disabled={!step2Done}>Step 3 — Image</TabsTrigger>
                    </TabsList>

                    {/* ── Setup ── */}
                    <TabsContent value="setup" className="mt-4 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Upload className="h-5 w-5" />
                                    {session ? 'Session created' : 'Create test session'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {session ? (
                                    <div className="space-y-4">
                                        <p className="text-muted-foreground text-sm">
                                            Session is ready. Use the tabs above to run each pipeline step.
                                        </p>
                                        <div className="grid gap-2 text-sm sm:grid-cols-2">
                                            <div><span className="text-muted-foreground">Brand:</span> {project?.name}</div>
                                            <div><span className="text-muted-foreground">Format:</span> {session.format}</div>
                                            <div className="sm:col-span-2"><span className="text-muted-foreground">Caption:</span> {session.caption}</div>
                                        </div>
                                        {references.length > 0 && (
                                            <div className="grid grid-cols-5 gap-2">
                                                {references.map((ref) => (
                                                    <div key={ref.id} className="relative aspect-square overflow-hidden rounded border">
                                                        <img
                                                            src={ref.thumbnail_url ?? ref.url}
                                                            alt={`Reference ${ref.order + 1}`}
                                                            className="h-full w-full object-cover"
                                                        />
                                                        <p className="absolute bottom-0 left-0 right-0 bg-black/50 px-1 py-0.5 text-center text-[10px] text-white">
                                                            #{ref.order}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <Button onClick={() => setActiveTab('step1')}>
                                            Go to Step 1 — Extract DNA
                                        </Button>
                                    </div>
                                ) : (
                                    <form onSubmit={handleSetup} className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="brand_name">Brand name</Label>
                                                <Input
                                                    id="brand_name"
                                                    value={setupForm.data.brand_name}
                                                    onChange={(e) => setupForm.setData('brand_name', e.target.value)}
                                                    placeholder="e.g. Collège LaSalle Maroc"
                                                    required
                                                />
                                                {setupForm.errors.brand_name && (
                                                    <p className="text-sm text-red-600">{setupForm.errors.brand_name}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="format">Format</Label>
                                                <Select
                                                    value={setupForm.data.format}
                                                    onValueChange={(v) => setupForm.setData('format', v)}
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

                                        <div className="space-y-2">
                                            <Label htmlFor="caption">Caption / topic</Label>
                                            <Textarea
                                                id="caption"
                                                value={setupForm.data.caption}
                                                onChange={(e) => setupForm.setData('caption', e.target.value)}
                                                placeholder="Social post caption or creative brief..."
                                                rows={3}
                                                required
                                            />
                                            {setupForm.errors.caption && (
                                                <p className="text-sm text-red-600">{setupForm.errors.caption}</p>
                                            )}
                                        </div>

                                        <div
                                            className={cn(
                                                'flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-10 transition-colors',
                                                dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/30 hover:border-primary/40',
                                            )}
                                            onClick={() => fileInputRef.current?.click()}
                                            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                            onDragLeave={() => setDragOver(false)}
                                            onDrop={(e) => {
                                                e.preventDefault();
                                                setDragOver(false);
                                                handleFiles(e.dataTransfer.files);
                                            }}
                                        >
                                            <Upload className="text-muted-foreground mb-3 h-10 w-10" />
                                            <p className="font-medium">Drop reference posts or click to browse</p>
                                            <p className="text-muted-foreground text-sm">1–10 images · min 2 for clustering · PNG, JPG, WebP</p>
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                multiple
                                                accept="image/jpeg,image/png,image/webp"
                                                className="hidden"
                                                onChange={(e) => handleFiles(e.target.files)}
                                            />
                                        </div>

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

                                        <Button
                                            type="submit"
                                            disabled={localFiles.length < 2 || setupForm.processing}
                                            className="w-full"
                                        >
                                            {setupForm.processing ? (
                                                <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Creating session…</>
                                            ) : (
                                                <><Zap className="mr-2 h-4 w-4" />Create session</>
                                            )}
                                        </Button>
                                    </form>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* ── Step 1 ── */}
                    <TabsContent value="step1" className="mt-4 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Sparkles className="h-5 w-5" />
                                    Step 1 — ClusteringService (Gemini clustering)
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    Runs <code className="text-xs">AnalyzeBrandJob</code> with the original Gemini <code className="text-xs">ClusteringService</code> — groups references into clusters of 2–3 images each.
                                </p>

                                {references.length > 0 && (
                                    <div className="grid grid-cols-5 gap-2">
                                        {references.map((ref) => (
                                            <div key={ref.id} className="aspect-square overflow-hidden rounded border">
                                                <img src={ref.thumbnail_url ?? ref.url} alt="" className="h-full w-full object-cover" />
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <Button
                                    onClick={runStep1}
                                    disabled={step1Form.processing || !session}
                                    className="w-full"
                                >
                                    {step1Form.processing ? (
                                        <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Running AnalyzeBrandJob…</>
                                    ) : step1Done ? (
                                        <><CheckCircle2 className="mr-2 h-4 w-4" />Re-run AnalyzeBrandJob</>
                                    ) : (
                                        <><Sparkles className="mr-2 h-4 w-4" />Run AnalyzeBrandJob</>
                                    )}
                                </Button>

                                {step1Done && (debug?.step1 || project?.dna_summary) && (
                                    <div className="space-y-3 border-t pt-4 text-sm">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="outline">Status: {debug?.step1?.status ?? 'completed'}</Badge>
                                            {debug?.step1?.cluster_count !== undefined && (
                                                <Badge variant="outline">{debug.step1.cluster_count} clusters</Badge>
                                            )}
                                        </div>
                                        {project?.dna_summary && (
                                            <div className="rounded-md border bg-green-50/50 p-3">
                                                <p className="font-medium text-green-800">DNA Summary</p>
                                                <p className="mt-1 whitespace-pre-wrap">{project.dna_summary}</p>
                                            </div>
                                        )}
                                        {project?.dna_extracted_at && (
                                            <p className="text-muted-foreground text-xs">
                                                Extracted at {new Date(project.dna_extracted_at).toLocaleString()}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* ── Step 2 ── */}
                    <TabsContent value="step2" className="mt-4 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Sparkles className="h-5 w-5" />
                                    Step 2 — CsvPostPromptBuilder (PromptForge)
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    Same post JSON path as CSV <code className="text-xs">MatchCaptionsJob</code> — PromptForge system prompts, cluster selection, and <code className="text-xs">meta</code> enrichment.
                                </p>
                                {clusters.length > 0 && (
                                    <div className="overflow-x-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Key</th>
                                                    <th className="px-3 py-2 text-left">Label</th>
                                                    <th className="px-3 py-2 text-left">Keywords</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {clusters.map((c) => (
                                                    <tr key={c.id} className="border-t">
                                                        <td className="px-3 py-2 font-mono text-xs">{c.key}</td>
                                                        <td className="px-3 py-2">{c.label}</td>
                                                        <td className="text-muted-foreground px-3 py-2 text-xs">
                                                            {(c.keywords ?? []).slice(0, 5).join(', ')}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}

                                <Button
                                    onClick={runStep2}
                                    disabled={step2Form.processing || !step1Done}
                                    className="w-full"
                                >
                                    {step2Form.processing ? (
                                        <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Generating post JSON…</>
                                    ) : step2Done ? (
                                        <><CheckCircle2 className="mr-2 h-4 w-4" />Re-run post JSON generation</>
                                    ) : (
                                        <><Sparkles className="mr-2 h-4 w-4" />Generate post JSON</>
                                    )}
                                </Button>

                                {step2Done && debug?.step2 && (
                                    <div className="space-y-3 border-t pt-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <ValidationBadge valid={debug.step2.json_valid} errors={debug.step2.json_validation_errors} />
                                            {debug.step2.cluster_label && (
                                                <Badge variant="outline">Cluster: {debug.step2.cluster_label}</Badge>
                                            )}
                                            {debug.step2.cluster_scores?.used_model_fallback && (
                                                <Badge variant="secondary">LLM cluster fallback</Badge>
                                            )}
                                            <MetricsRow data={debug.step2} />
                                        </div>

                                        {debug.step2.cluster_scores && (
                                            <DebugBlock title="Cluster keyword scores" defaultOpen>
                                                <div className="space-y-2">
                                                    {Object.entries(debug.step2.cluster_scores.scores).map(([key, score]) => (
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
                                            <PreBlock content={debug.step2.analysis_prose} />
                                        </DebugBlock>
                                        <DebugBlock title="Post prompt JSON">
                                            <JsonBlock data={debug.step2.prompt_json} />
                                        </DebugBlock>
                                        {debug.step2.tweaks && debug.step2.tweaks.length > 0 && (
                                            <DebugBlock title="Tweaks">
                                                <ul className="list-inside list-disc text-sm">
                                                    {debug.step2.tweaks.map((t, i) => <li key={i}>{t}</li>)}
                                                </ul>
                                            </DebugBlock>
                                        )}
                                        <DebugBlock title="Selected cluster references">
                                            <JsonBlock data={Object.fromEntries(
                                                (debug.step2.selected_cluster_images ?? []).map((img, i) => [i, img]),
                                            )} />
                                        </DebugBlock>
                                        <DebugBlock title="Raw LLM response">
                                            <PreBlock content={debug.step2.raw_text} />
                                        </DebugBlock>
                                        <DebugBlock title="System prompt (generate_post)">
                                            <PreBlock content={debug.step2.system_prompt} />
                                        </DebugBlock>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* ── Step 3 ── */}
                    <TabsContent value="step3" className="mt-4 space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ImageIcon className="h-5 w-5" />
                                    Step 3 — GenerateSingleImageJob
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    Runs <code className="text-xs">GenerateSingleImageJob</code> — sends the post JSON prompt plus up to 3 reference images from the cluster chosen in Step 2.
                                </p>
                                <Button
                                    onClick={runStep3}
                                    disabled={step3Form.processing || !step2Done}
                                    className="w-full"
                                >
                                    {step3Form.processing ? (
                                        <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Generating image…</>
                                    ) : step3Done ? (
                                        <><CheckCircle2 className="mr-2 h-4 w-4" />Re-run image generation</>
                                    ) : (
                                        <><ImageIcon className="mr-2 h-4 w-4" />Generate image</>
                                    )}
                                </Button>

                                {step3Done && debug?.step3 && (
                                    <div className="space-y-4 border-t pt-4">
                                        <div className="text-muted-foreground flex flex-wrap gap-3 text-xs">
                                            <span>Job status: <strong className="text-foreground">{debug.step3.status}</strong></span>
                                            <span>Sent to model: <strong className="text-foreground">{debug.step3.sent_to_model}</strong></span>
                                            {debug.step3.reference_count !== undefined && (
                                                <span>References: <strong className="text-foreground">{debug.step3.reference_count}</strong></span>
                                            )}
                                            {debug.step3.model_slug && (
                                                <span>Model: <strong className="text-foreground">{debug.step3.model_slug}</strong></span>
                                            )}
                                            {debug.step3.cluster_key && (
                                                <span>Cluster: <strong className="text-foreground">{debug.step3.cluster_key}</strong></span>
                                            )}
                                        </div>

                                        {debug.step3.sent_cluster_images && debug.step3.sent_cluster_images.length > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-sm font-medium">Cluster references sent to image model</p>
                                                <div className="grid grid-cols-3 gap-2">
                                                    {debug.step3.sent_cluster_images.map((ref, i) => (
                                                        <div key={ref.cluster_image_id ?? i} className="relative aspect-square overflow-hidden rounded border">
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
                                            </div>
                                        )}

                                        {debug.step3.prompt_json && (
                                            <DebugBlock title="Post JSON sent to image model">
                                                <JsonBlock data={debug.step3.prompt_json} />
                                            </DebugBlock>
                                        )}

                                        {debug.step3.image_request_prompt && (
                                            <DebugBlock title="Image request prompt (text + JSON block)">
                                                <PreBlock content={debug.step3.image_request_prompt} />
                                            </DebugBlock>
                                        )}

                                        {displayImageUrl && (
                                            <div className="space-y-3">
                                                <p className="font-medium">Preview</p>
                                                <div className="overflow-hidden rounded-lg border bg-muted/30">
                                                    <img
                                                        src={displayImageUrl}
                                                        alt="Generated post"
                                                        className="mx-auto max-h-[480px] w-full object-contain"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
