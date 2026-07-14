import { Head, usePage } from '@inertiajs/react';
import { useRef, useState, type ChangeEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    AlertCircle,
    Download,
    ImageIcon,
    ImagePlus,
    Loader2,
    Sparkles,
    Upload,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { csrfHeaders } from '@/lib/csrf';

type PageProps = {
    aspect_ratios: string[];
};

type CopySlots = {
    headline?: string;
    subhead?: string;
    body?: string;
    cta?: string;
    eyebrow?: string;
    label?: string;
};

type BuildResult = {
    slots_detected: string[];
    copy: CopySlots;
    visual_lock_summary: string;
    master_prompt: string;
    reference_paths: string[];
    reference_urls: string[];
    model_used: string;
};

type GenerateResult = {
    url: string;
    path: string;
    model_used: string;
    generation_ms: number;
};

type PreviewSlot = {
    file: File | null;
    previewUrl: string | null;
};

/** Prefer XSRF cookie — meta csrf-token goes stale after Inertia login/session regen. */
function labHeaders(): HeadersInit {
    return csrfHeaders({ Accept: 'application/json' });
}

export default function MasterPromptLab() {
    const { aspect_ratios } = usePage().props as unknown as PageProps;
    const ratios = aspect_ratios?.length ? aspect_ratios : ['1:1', '4:5', '9:16', '16:9', '3:4'];

    const fileInput0 = useRef<HTMLInputElement>(null);
    const fileInput1 = useRef<HTMLInputElement>(null);
    const fileInput2 = useRef<HTMLInputElement>(null);
    const fileInputRefs = [fileInput0, fileInput1, fileInput2];

    const [slots, setSlots] = useState<PreviewSlot[]>([
        { file: null, previewUrl: null },
        { file: null, previewUrl: null },
        { file: null, previewUrl: null },
    ]);
    const [caption, setCaption] = useState('');
    const [aspectRatio, setAspectRatio] = useState('1:1');
    const [masterPrompt, setMasterPrompt] = useState('');
    const [buildMeta, setBuildMeta] = useState<BuildResult | null>(null);
    const [generated, setGenerated] = useState<GenerateResult | null>(null);
    const [building, setBuilding] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const allFilesReady = slots.every((s) => s.file !== null);
    const canBuild = allFilesReady && caption.trim().length > 0 && !building && !generating;
    const canGenerate =
        Boolean(buildMeta?.reference_paths?.length === 3) &&
        masterPrompt.trim().length > 0 &&
        !building &&
        !generating;

    function onPickFile(index: number, e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setSlots((prev) => {
            const next = [...prev];
            if (next[index].previewUrl) {
                URL.revokeObjectURL(next[index].previewUrl!);
            }
            next[index] = {
                file,
                previewUrl: file ? URL.createObjectURL(file) : null,
            };
            return next;
        });
        setBuildMeta(null);
        setGenerated(null);
        setError(null);
    }

    async function buildPrompt() {
        if (!canBuild) return;
        setBuilding(true);
        setError(null);
        setGenerated(null);

        const form = new FormData();
        form.append('caption', caption.trim());
        form.append('aspect_ratio', aspectRatio);
        slots.forEach((s) => {
            if (s.file) form.append('reference_images[]', s.file);
        });

        try {
            const res = await fetch('/test/master-prompt/build', {
                method: 'POST',
                credentials: 'same-origin',
                headers: labHeaders(),
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 419) {
                throw new Error('CSRF token mismatch. Refresh the page and try again.');
            }
            if (!res.ok || !data.ok) {
                throw new Error(data.error || data.message || 'Failed to build master prompt');
            }
            setBuildMeta(data as BuildResult);
            setMasterPrompt(data.master_prompt || '');
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Build failed');
        } finally {
            setBuilding(false);
        }
    }

    async function generateImage() {
        if (!canGenerate || !buildMeta) return;
        setGenerating(true);
        setError(null);

        const form = new FormData();
        form.append('master_prompt', masterPrompt.trim());
        form.append('aspect_ratio', aspectRatio);
        buildMeta.reference_paths.forEach((path) => {
            form.append('reference_paths[]', path);
        });

        try {
            const res = await fetch('/test/master-prompt/generate', {
                method: 'POST',
                credentials: 'same-origin',
                headers: labHeaders(),
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 419) {
                throw new Error('CSRF token mismatch. Refresh the page and try again.');
            }
            if (!res.ok || !data.ok) {
                throw new Error(data.error || data.message || 'Failed to generate image');
            }
            setGenerated(data as GenerateResult);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Generate failed');
        } finally {
            setGenerating(false);
        }
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Test Labs', href: '/test/master-prompt' },
                { title: 'Master Prompt Lab', href: '/test/master-prompt' },
            ]}
        >
            <Head title="Master Prompt Lab" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <ImagePlus className="size-6" />
                        Master Prompt Lab
                    </h1>
                    <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Upload 3 brand references and a caption. Step 1 builds an editable master
                        prompt (layout locked from refs, copy from caption). Step 2 generates with
                        Gemini image preview.
                    </p>
                </div>

                {error && (
                    <div className="border-destructive/40 bg-destructive/10 text-destructive flex items-start gap-2 rounded-lg border px-3 py-2 text-sm">
                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                        <span>{error}</span>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">1. References & caption</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-3">
                                {slots.map((slot, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => fileInputRefs[i].current?.click()}
                                        className={cn(
                                            'border-muted-foreground/25 hover:border-muted-foreground/50 relative flex aspect-square flex-col items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30 transition-colors',
                                            slot.previewUrl && 'border-solid border-border',
                                        )}
                                    >
                                        {slot.previewUrl ? (
                                            <img
                                                src={slot.previewUrl}
                                                alt={`Reference ${i + 1}`}
                                                className="absolute inset-0 size-full object-cover"
                                            />
                                        ) : (
                                            <>
                                                <Upload className="text-muted-foreground size-5" />
                                                <span className="text-muted-foreground mt-1 text-xs">
                                                    Ref {i + 1}
                                                </span>
                                            </>
                                        )}
                                        <input
                                            ref={fileInputRefs[i]}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) => onPickFile(i, e)}
                                        />
                                    </button>
                                ))}
                            </div>

                            {buildMeta?.reference_urls?.length === 3 && (
                                <div className="flex flex-wrap gap-2">
                                    {buildMeta.reference_urls.map((url, i) => (
                                        <a
                                            key={url}
                                            href={url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-muted-foreground text-xs underline"
                                        >
                                            Stored ref {i + 1}
                                        </a>
                                    ))}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="caption">Caption</Label>
                                <Textarea
                                    id="caption"
                                    value={caption}
                                    onChange={(e) => setCaption(e.target.value)}
                                    placeholder="Paste the post caption / brief. On-image copy will be derived from this only."
                                    rows={5}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Aspect ratio</Label>
                                <Select value={aspectRatio} onValueChange={setAspectRatio}>
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

                            <Button onClick={buildPrompt} disabled={!canBuild} className="w-full sm:w-auto">
                                {building ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Building master prompt…
                                    </>
                                ) : (
                                    <>
                                        <Sparkles className="size-4" />
                                        Build master prompt
                                    </>
                                )}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">2. Master prompt</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {buildMeta && (
                                <div className="space-y-2">
                                    <div className="flex flex-wrap gap-1.5">
                                        {(buildMeta.slots_detected || []).map((slot) => (
                                            <Badge key={slot} variant="secondary">
                                                {slot}
                                            </Badge>
                                        ))}
                                        {buildMeta.model_used && (
                                            <Badge variant="outline">{buildMeta.model_used}</Badge>
                                        )}
                                    </div>
                                    {buildMeta.visual_lock_summary && (
                                        <p className="text-muted-foreground text-xs leading-relaxed">
                                            {buildMeta.visual_lock_summary}
                                        </p>
                                    )}
                                    {buildMeta.copy && (
                                        <div className="bg-muted/40 space-y-1 rounded-md p-3 text-xs">
                                            {Object.entries(buildMeta.copy)
                                                .filter(([, v]) => typeof v === 'string' && v.trim())
                                                .map(([k, v]) => (
                                                    <div key={k}>
                                                        <span className="font-medium capitalize">{k}: </span>
                                                        {v}
                                                    </div>
                                                ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="master_prompt">Editable master prompt</Label>
                                <Textarea
                                    id="master_prompt"
                                    value={masterPrompt}
                                    onChange={(e) => setMasterPrompt(e.target.value)}
                                    placeholder="Build step fills this. Edit before generating."
                                    rows={14}
                                    className="font-mono text-xs"
                                />
                            </div>

                            <Button
                                onClick={generateImage}
                                disabled={!canGenerate}
                                className="w-full sm:w-auto"
                            >
                                {generating ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Generating image…
                                    </>
                                ) : (
                                    <>
                                        <ImageIcon className="size-4" />
                                        Generate image
                                    </>
                                )}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {generated && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">Result</CardTitle>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline">{generated.model_used}</Badge>
                                <Badge variant="secondary">{generated.generation_ms} ms</Badge>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={generated.url} download target="_blank" rel="noreferrer">
                                        <Download className="size-4" />
                                        Download
                                    </a>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <img
                                src={generated.url}
                                alt="Generated"
                                className="mx-auto max-h-[70vh] w-auto max-w-full rounded-lg border"
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
