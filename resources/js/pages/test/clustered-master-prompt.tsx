import { Head, usePage } from '@inertiajs/react';
import { useRef, useState, type ChangeEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    AlertCircle,
    Download,
    FileText,
    ImageIcon,
    Layers,
    Loader2,
    Plus,
    Target,
    Trash2,
    Upload,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { csrfHeaders } from '@/lib/csrf';

type PageProps = {
    aspect_ratios: string[];
};

type StoredImage = {
    index: number;
    path: string;
    url: string;
    name: string;
};

type StyleCluster = {
    name: string;
    tags?: string[];
    reason?: string;
    imageIndices: number[];
    dominantColor?: string;
    typographyStyle?: string;
    compositionType?: string;
    backgroundTreatment?: string;
    palette?: string[];
    textPlacement?: string;
};

type ClusterResult = {
    images: StoredImage[];
    global_analysis: string;
    global_rules: string[];
    clusters: StyleCluster[];
};

type RowStatus = 'idle' | 'running' | 'done' | 'error' | 'skipped';

type CaptionDraft = {
    id: string;
    text: string;
};

type CaptionRow = {
    id: string;
    caption: string;
    selectedClusterIndex: number;
    selectedImageIndices: number[];
    scores: Record<number, number>;
    usedModelFallback: boolean;
    status: RowStatus;
    error?: string;
    resultUrl?: string;
    resultPath?: string;
    masterPrompt?: string;
    modelUsed?: string;
    generationMs?: number;
};

let draftSeq = 0;
function newDraftId(): string {
    draftSeq += 1;
    return `caption-${Date.now()}-${draftSeq}`;
}

function emptyDraft(): CaptionDraft {
    return { id: newDraftId(), text: '' };
}

function draftsFromCaptions(captions: string[]): CaptionDraft[] {
    const sliced = captions.slice(0, 50);
    if (sliced.length === 0) return [emptyDraft()];
    return sliced.map((text) => ({ id: newDraftId(), text }));
}

/** Prefer XSRF cookie — meta csrf-token goes stale after Inertia login/session regen. */
function labHeaders(): HeadersInit {
    return csrfHeaders({ Accept: 'application/json' });
}

async function postJson(url: string, body: FormData | object) {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: isForm
            ? labHeaders()
            : { ...labHeaders(), 'Content-Type': 'application/json' },
        body: isForm ? body : JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 419) {
        throw new Error('CSRF token mismatch. Refresh the page and try again.');
    }
    if (!res.ok || data.ok === false) {
        throw new Error(data.error || data.message || 'Request failed');
    }
    return data;
}

function parseCaptionsText(raw: string): string[] {
    return raw
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
}

function parseCaptionsFromCsv(text: string): string[] {
    const lines = text.split(/\r?\n/).filter((l) => l.trim().length > 0);
    if (lines.length === 0) return [];

    const header = lines[0].toLowerCase();
    const hasCaptionHeader =
        header.includes('caption') || header.includes('title') || header.includes('text');

    const start = hasCaptionHeader ? 1 : 0;
    const captions: string[] = [];

    for (let i = start; i < lines.length; i++) {
        const line = lines[i];
        // Prefer quoted field or first column
        const quoted = line.match(/^"([^"]*)"/);
        if (quoted) {
            const value = quoted[1].trim();
            if (value) captions.push(value);
            continue;
        }
        const firstCol = line.split(',')[0]?.trim() ?? '';
        if (firstCol) captions.push(firstCol);
    }

    return captions;
}

function defaultRefsForCluster(cluster: StyleCluster | undefined): number[] {
    return (cluster?.imageIndices ?? []).slice(0, 3);
}

function isUnfitName(name: string): boolean {
    const n = name.trim().toLowerCase();
    return n === 'unfit' || n.startsWith('unfit');
}

export default function ClusteredMasterPromptLab() {
    const { aspect_ratios } = usePage().props as unknown as PageProps;
    const ratios = aspect_ratios?.length ? aspect_ratios : ['1:1', '4:5', '9:16', '16:9', '3:4'];
    const fileInputRef = useRef<HTMLInputElement>(null);
    const captionsFileRef = useRef<HTMLInputElement>(null);

    const [files, setFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);
    const [clusterResult, setClusterResult] = useState<ClusterResult | null>(null);
    const [captionDrafts, setCaptionDrafts] = useState<CaptionDraft[]>([emptyDraft()]);
    const [rows, setRows] = useState<CaptionRow[]>([]);
    const [aspectRatio, setAspectRatio] = useState('1:1');
    const [expandedRowId, setExpandedRowId] = useState<string | null>(null);

    const [clustering, setClustering] = useState(false);
    const [matching, setMatching] = useState(false);
    const [bulkRunning, setBulkRunning] = useState(false);
    const [bulkProgress, setBulkProgress] = useState({ done: 0, total: 0 });
    const [error, setError] = useState<string | null>(null);

    const busy = clustering || matching || bulkRunning;
    const readyCaptions = captionDrafts.map((d) => d.text.trim()).filter(Boolean);
    const captionCount = readyCaptions.length;
    const canCluster = files.length >= 3 && !busy;
    const canMatch = Boolean(clusterResult) && captionCount > 0 && !busy;
    const runnableRows = rows.filter((r) => r.selectedImageIndices.length === 3);
    const canBulkGenerate = runnableRows.length > 0 && !busy;

    function resetDownstream() {
        setRows([]);
        setExpandedRowId(null);
    }

    function setDraftText(id: string, text: string) {
        setCaptionDrafts((prev) => prev.map((d) => (d.id === id ? { ...d, text } : d)));
        resetDownstream();
    }

    function addCaptionRow() {
        if (captionDrafts.length >= 50) return;
        setCaptionDrafts((prev) => [...prev, emptyDraft()]);
        resetDownstream();
    }

    function removeCaptionRow(id: string) {
        setCaptionDrafts((prev) => {
            const next = prev.filter((d) => d.id !== id);
            return next.length > 0 ? next : [emptyDraft()];
        });
        resetDownstream();
    }

    function onPickFiles(e: ChangeEvent<HTMLInputElement>) {
        const picked = Array.from(e.target.files ?? []).slice(0, 20);
        previews.forEach((url) => URL.revokeObjectURL(url));
        setFiles(picked);
        setPreviews(picked.map((f) => URL.createObjectURL(f)));
        setClusterResult(null);
        resetDownstream();
        setError(null);
        e.target.value = '';
    }

    async function onPickCaptionsFile(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;

        try {
            const text = await file.text();
            const name = file.name.toLowerCase();
            const parsed =
                name.endsWith('.csv') || name.endsWith('.tsv')
                    ? parseCaptionsFromCsv(text)
                    : parseCaptionsText(text);
            if (parsed.length === 0) {
                setError('No captions found in that file.');
                return;
            }
            setCaptionDrafts(draftsFromCaptions(parsed));
            resetDownstream();
            setError(null);
        } catch {
            setError('Could not read captions file.');
        }
    }

    async function runCluster() {
        if (!canCluster) return;
        setClustering(true);
        setError(null);
        setClusterResult(null);
        resetDownstream();

        const form = new FormData();
        files.forEach((f) => form.append('reference_images[]', f));

        try {
            const data = await postJson('/test/clustered-master-prompt/cluster', form);
            setClusterResult({
                images: data.images,
                global_analysis: data.global_analysis || '',
                global_rules: data.global_rules || [],
                clusters: data.clusters || [],
            });
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Clustering failed');
        } finally {
            setClustering(false);
        }
    }

    async function runMatchBatch() {
        if (!canMatch || !clusterResult) return;
        setMatching(true);
        setError(null);

        const captions = readyCaptions.slice(0, 50);

        try {
            const data = await postJson('/test/clustered-master-prompt/match-batch', {
                captions,
                clusters: clusterResult.clusters,
            });

            const nextRows: CaptionRow[] = (data.rows || []).map(
                (row: {
                    row_index: number;
                    caption: string;
                    selected_index: number;
                    selected_image_indices: number[];
                    scores: Record<number, number>;
                    used_model_fallback: boolean;
                }) => ({
                    id: `row-${row.row_index}-${Date.now()}`,
                    caption: row.caption,
                    selectedClusterIndex: row.selected_index,
                    selectedImageIndices: (row.selected_image_indices || []).slice(0, 3),
                    scores: row.scores || {},
                    usedModelFallback: Boolean(row.used_model_fallback),
                    status: 'idle' as const,
                }),
            );

            setRows(nextRows);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Caption match failed');
        } finally {
            setMatching(false);
        }
    }

    function updateRowCluster(rowId: string, clusterIndex: number) {
        if (!clusterResult) return;
        const cluster = clusterResult.clusters[clusterIndex];
        setRows((prev) =>
            prev.map((row) =>
                row.id === rowId
                    ? {
                          ...row,
                          selectedClusterIndex: clusterIndex,
                          selectedImageIndices: defaultRefsForCluster(cluster),
                          status: 'idle',
                          error: undefined,
                          resultUrl: undefined,
                          resultPath: undefined,
                          masterPrompt: undefined,
                      }
                    : row,
            ),
        );
    }

    function toggleRowRef(rowId: string, imageIndex: number) {
        if (!clusterResult) return;
        setRows((prev) =>
            prev.map((row) => {
                if (row.id !== rowId) return row;
                const cluster = clusterResult.clusters[row.selectedClusterIndex];
                if (!cluster?.imageIndices.includes(imageIndex)) return row;

                let next = [...row.selectedImageIndices];
                if (next.includes(imageIndex)) {
                    next = next.filter((i) => i !== imageIndex);
                } else if (next.length >= 3) {
                    next = [...next.slice(1), imageIndex];
                } else {
                    next = [...next, imageIndex];
                }

                return {
                    ...row,
                    selectedImageIndices: next,
                    status: 'idle',
                    error: undefined,
                    resultUrl: undefined,
                    masterPrompt: undefined,
                };
            }),
        );
    }

    async function runBulkGenerate() {
        if (!canBulkGenerate || !clusterResult) return;
        setBulkRunning(true);
        setError(null);

        const targets = rows.filter((r) => r.selectedImageIndices.length === 3);
        setBulkProgress({ done: 0, total: targets.length });

        // Mark under-ref rows as skipped
        setRows((prev) =>
            prev.map((row) =>
                row.selectedImageIndices.length === 3
                    ? { ...row, status: 'idle', error: undefined }
                    : {
                          ...row,
                          status: 'skipped',
                          error: 'Need exactly 3 reference images in the selected cluster.',
                      },
            ),
        );

        let done = 0;
        for (const target of targets) {
            setRows((prev) =>
                prev.map((row) =>
                    row.id === target.id ? { ...row, status: 'running', error: undefined } : row,
                ),
            );

            try {
                const paths = target.selectedImageIndices.map((idx) => {
                    const img = clusterResult.images.find((i) => i.index === idx);
                    if (!img) throw new Error(`Missing image index ${idx}`);
                    return img.path;
                });

                const data = await postJson('/test/clustered-master-prompt/run-row', {
                    reference_paths: paths,
                    caption: target.caption,
                    aspect_ratio: aspectRatio,
                });

                setRows((prev) =>
                    prev.map((row) =>
                        row.id === target.id
                            ? {
                                  ...row,
                                  status: 'done',
                                  resultUrl: data.url,
                                  resultPath: data.path,
                                  masterPrompt: data.master_prompt,
                                  modelUsed: data.model_used,
                                  generationMs: data.generation_ms,
                              }
                            : row,
                    ),
                );
            } catch (e) {
                const message = e instanceof Error ? e.message : 'Generate failed';
                setRows((prev) =>
                    prev.map((row) =>
                        row.id === target.id
                            ? { ...row, status: 'error', error: message }
                            : row,
                    ),
                );
            }

            done += 1;
            setBulkProgress({ done, total: targets.length });
        }

        setBulkRunning(false);
    }

    const completedResults = rows.filter((r) => r.status === 'done' && r.resultUrl);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Test Labs', href: '/test/clustered-master-prompt' },
                { title: 'Clustered Master Prompt', href: '/test/clustered-master-prompt' },
            ]}
        >
            <Head title="Clustered Master Prompt" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Layers className="size-6" />
                        Clustered Master Prompt
                    </h1>
                    <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                        Cluster brand references, match multiple captions to clusters, then bulk
                        generate images.
                    </p>
                </div>

                {error && (
                    <div className="border-destructive/40 bg-destructive/10 text-destructive flex items-start gap-2 rounded-lg border px-3 py-2 text-sm">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                {/* Step 1: Upload + cluster */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">1. Upload & cluster</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={busy}
                            >
                                <Upload className="size-4" />
                                Choose images (3–20)
                            </Button>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                className="hidden"
                                onChange={onPickFiles}
                            />
                            {files.length > 0 && (
                                <span className="text-muted-foreground text-sm">
                                    {files.length} selected
                                </span>
                            )}
                            <Button onClick={runCluster} disabled={!canCluster}>
                                {clustering ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Clustering…
                                    </>
                                ) : (
                                    <>
                                        <Layers className="size-4" />
                                        Run clustering
                                    </>
                                )}
                            </Button>
                        </div>

                        {previews.length > 0 && (
                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
                                {previews.map((url, i) => (
                                    <div
                                        key={url}
                                        className="relative aspect-square overflow-hidden rounded-md border"
                                    >
                                        <img
                                            src={url}
                                            alt={`Upload ${i + 1}`}
                                            className="size-full object-cover"
                                        />
                                        <span className="bg-background/80 absolute left-1 top-1 rounded px-1 text-[10px]">
                                            {i}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {clusterResult && (
                            <div className="space-y-4 border-t pt-4">
                                {clusterResult.global_analysis && (
                                    <p className="text-muted-foreground text-sm leading-relaxed">
                                        {clusterResult.global_analysis}
                                    </p>
                                )}
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {clusterResult.clusters.map((cluster, ci) => {
                                        const unfit = isUnfitName(cluster.name);
                                        return (
                                            <div
                                                key={`${cluster.name}-${ci}`}
                                                className={cn(
                                                    'space-y-2 rounded-lg border bg-muted/20 p-3',
                                                    unfit &&
                                                        'border-dashed border-amber-500/50 bg-amber-500/5',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div>
                                                        <p className="flex items-center gap-2 text-sm font-medium">
                                                            {cluster.name}
                                                            {unfit && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-[10px]"
                                                                >
                                                                    outliers
                                                                </Badge>
                                                            )}
                                                        </p>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            {cluster.reason}
                                                        </p>
                                                    </div>
                                                    <Badge variant="secondary">
                                                        {cluster.imageIndices.length}
                                                    </Badge>
                                                </div>
                                                <div className="grid grid-cols-3 gap-1.5">
                                                    {cluster.imageIndices.map((idx) => {
                                                        const img = clusterResult.images.find(
                                                            (i) => i.index === idx,
                                                        );
                                                        if (!img) return null;
                                                        return (
                                                            <div
                                                                key={idx}
                                                                className="relative aspect-square overflow-hidden rounded border"
                                                            >
                                                                <img
                                                                    src={img.url}
                                                                    alt={img.name}
                                                                    className="size-full object-cover"
                                                                />
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Step 2: Captions + match */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">2. Captions → match clusters</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Label>Captions table (max 50)</Label>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!clusterResult || busy}
                                        onClick={() => captionsFileRef.current?.click()}
                                    >
                                        <FileText className="size-4" />
                                        Upload .txt / .csv
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!clusterResult || busy || captionDrafts.length >= 50}
                                        onClick={addCaptionRow}
                                    >
                                        <Plus className="size-4" />
                                        Add row
                                    </Button>
                                </div>
                                <input
                                    ref={captionsFileRef}
                                    type="file"
                                    accept=".txt,.csv,.tsv,text/plain,text/csv"
                                    className="hidden"
                                    onChange={onPickCaptionsFile}
                                />
                            </div>

                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead className="bg-muted/50 border-b">
                                        <tr>
                                            <th className="text-muted-foreground w-14 px-3 py-2 text-left font-medium">
                                                #
                                            </th>
                                            <th className="text-muted-foreground px-3 py-2 text-left font-medium">
                                                Caption
                                            </th>
                                            <th className="text-muted-foreground w-16 px-3 py-2 text-right font-medium">
                                                {' '}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {captionDrafts.map((draft, i) => (
                                            <tr key={draft.id} className="border-b last:border-b-0">
                                                <td className="text-muted-foreground px-3 py-2 align-middle">
                                                    {i + 1}
                                                </td>
                                                <td className="px-3 py-2 align-middle">
                                                    <Input
                                                        value={draft.text}
                                                        onChange={(e) =>
                                                            setDraftText(draft.id, e.target.value)
                                                        }
                                                        placeholder="Enter caption…"
                                                        disabled={!clusterResult || busy}
                                                        className="h-9"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right align-middle">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        disabled={
                                                            busy ||
                                                            (captionDrafts.length === 1 &&
                                                                !draft.text.trim())
                                                        }
                                                        onClick={() => removeCaptionRow(draft.id)}
                                                        aria-label={`Remove caption ${i + 1}`}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="text-muted-foreground text-xs">
                                {captionCount} caption{captionCount === 1 ? '' : 's'} ready
                            </p>
                        </div>

                        <Button onClick={runMatchBatch} disabled={!canMatch}>
                            {matching ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Matching captions…
                                </>
                            ) : (
                                <>
                                    <Target className="size-4" />
                                    Match all captions
                                </>
                            )}
                        </Button>

                        {rows.length > 0 && clusterResult && (
                            <div className="space-y-3 border-t pt-4">
                                <div className="flex flex-wrap items-end gap-4">
                                    <div className="space-y-2">
                                        <Label>Aspect ratio</Label>
                                        <Select
                                            value={aspectRatio}
                                            onValueChange={setAspectRatio}
                                            disabled={busy}
                                        >
                                            <SelectTrigger className="w-40">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ratios.map((r) => (
                                                    <SelectItem key={r} value={r}>
                                                        {r}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button onClick={runBulkGenerate} disabled={!canBulkGenerate}>
                                        {bulkRunning ? (
                                            <>
                                                <Loader2 className="size-4 animate-spin" />
                                                Generating {bulkProgress.done}/{bulkProgress.total}…
                                            </>
                                        ) : (
                                            <>
                                                <ImageIcon className="size-4" />
                                                Bulk generate ({runnableRows.length})
                                            </>
                                        )}
                                    </Button>
                                    {runnableRows.length < rows.length && (
                                        <p className="text-muted-foreground text-xs">
                                            {rows.length - runnableRows.length} row(s) need 3 refs
                                            (cluster too small)
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    {rows.map((row, i) => {
                                        const cluster =
                                            clusterResult.clusters[row.selectedClusterIndex];
                                        const expanded = expandedRowId === row.id;
                                        return (
                                            <div
                                                key={row.id}
                                                className="space-y-3 rounded-lg border p-3"
                                            >
                                                <div className="flex flex-wrap items-start gap-3">
                                                    <Badge variant="outline" className="mt-0.5">
                                                        #{i + 1}
                                                    </Badge>
                                                    <p className="min-w-0 flex-1 text-sm leading-snug">
                                                        {row.caption}
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        {row.usedModelFallback && (
                                                            <Badge variant="secondary">
                                                                model pick
                                                            </Badge>
                                                        )}
                                                        <Badge
                                                            variant={
                                                                row.status === 'done'
                                                                    ? 'default'
                                                                    : row.status === 'error' ||
                                                                        row.status === 'skipped'
                                                                      ? 'destructive'
                                                                      : 'outline'
                                                            }
                                                        >
                                                            {row.status === 'running'
                                                                ? 'running…'
                                                                : row.status}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="space-y-1">
                                                        <Label className="text-xs">Cluster</Label>
                                                        <Select
                                                            value={String(row.selectedClusterIndex)}
                                                            onValueChange={(v) =>
                                                                updateRowCluster(row.id, Number(v))
                                                            }
                                                            disabled={busy}
                                                        >
                                                            <SelectTrigger className="w-[220px]">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {clusterResult.clusters.map(
                                                                    (c, ci) => (
                                                                        <SelectItem
                                                                            key={`${c.name}-${ci}`}
                                                                            value={String(ci)}
                                                                        >
                                                                            {c.name}
                                                                            {typeof row.scores[
                                                                                ci
                                                                            ] === 'number'
                                                                                ? ` (${Number(row.scores[ci]).toFixed(2)})`
                                                                                : ''}
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="flex gap-1.5">
                                                        {row.selectedImageIndices.map((idx) => {
                                                            const img = clusterResult.images.find(
                                                                (x) => x.index === idx,
                                                            );
                                                            return img ? (
                                                                <div
                                                                    key={idx}
                                                                    className="size-12 overflow-hidden rounded border"
                                                                >
                                                                    <img
                                                                        src={img.url}
                                                                        alt=""
                                                                        className="size-full object-cover"
                                                                    />
                                                                </div>
                                                            ) : null;
                                                        })}
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setExpandedRowId(
                                                                expanded ? null : row.id,
                                                            )
                                                        }
                                                    >
                                                        {expanded ? 'Hide refs' : 'Swap refs'}
                                                    </Button>
                                                </div>

                                                {expanded && cluster && (
                                                    <div className="space-y-2">
                                                        <p className="text-muted-foreground text-xs">
                                                            Click images to swap (need exactly 3).
                                                            Selected: {row.selectedImageIndices.length}
                                                            /3
                                                        </p>
                                                        <div className="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8">
                                                            {cluster.imageIndices.map((idx) => {
                                                                const img =
                                                                    clusterResult.images.find(
                                                                        (x) => x.index === idx,
                                                                    );
                                                                if (!img) return null;
                                                                const picked =
                                                                    row.selectedImageIndices.includes(
                                                                        idx,
                                                                    );
                                                                return (
                                                                    <button
                                                                        key={idx}
                                                                        type="button"
                                                                        disabled={busy}
                                                                        onClick={() =>
                                                                            toggleRowRef(
                                                                                row.id,
                                                                                idx,
                                                                            )
                                                                        }
                                                                        className={cn(
                                                                            'relative aspect-square overflow-hidden rounded border',
                                                                            picked
                                                                                ? 'ring-2 ring-primary'
                                                                                : 'opacity-60 hover:opacity-100',
                                                                        )}
                                                                    >
                                                                        <img
                                                                            src={img.url}
                                                                            alt=""
                                                                            className="size-full object-cover"
                                                                        />
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}

                                                {row.error && (
                                                    <p className="text-destructive text-xs">
                                                        {row.error}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Step 3: Results */}
                {completedResults.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                3. Results ({completedResults.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {completedResults.map((row, i) => (
                                    <div key={row.id} className="space-y-2 rounded-lg border p-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="line-clamp-2 text-xs leading-snug">
                                                <span className="font-medium">#{i + 1}</span>{' '}
                                                {row.caption}
                                            </p>
                                            <Button variant="outline" size="sm" asChild>
                                                <a
                                                    href={row.resultUrl}
                                                    download
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Download className="size-3.5" />
                                                </a>
                                            </Button>
                                        </div>
                                        <img
                                            src={row.resultUrl}
                                            alt={`Result ${i + 1}`}
                                            className="w-full rounded-md border"
                                        />
                                        <div className="flex flex-wrap gap-1.5">
                                            {row.modelUsed && (
                                                <Badge variant="outline" className="text-[10px]">
                                                    {row.modelUsed}
                                                </Badge>
                                            )}
                                            {typeof row.generationMs === 'number' && (
                                                <Badge variant="secondary" className="text-[10px]">
                                                    {row.generationMs} ms
                                                </Badge>
                                            )}
                                            <Badge variant="outline" className="text-[10px]">
                                                {clusterResult?.clusters[row.selectedClusterIndex]
                                                    ?.name ?? 'cluster'}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
