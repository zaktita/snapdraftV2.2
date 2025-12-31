import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Loader2, AlertCircle, CheckCircle2, Sparkles, ImageIcon } from 'lucide-react';

type ReferenceItem = {
    path: string;
    url: string;
    name?: string;
};

interface GenerationImage {
    image_base64?: string;
    image_data?: string;
    mime_type?: string;
    metadata?: Record<string, any>;
}

interface BrandAnalysisProps {
    result?: any;
    count?: number;
    timestamp?: string;
    errors?: Record<string, string>;
    references?: ReferenceItem[];
    selected_reference_paths?: string[];
    generated_prompt?: string;
    generated_image?: GenerationImage;
    caption?: string;
    format?: string;
}

export default function BrandAnalysisWizard({
    result,
    count,
    timestamp,
    errors,
    references = [],
    selected_reference_paths = [],
    generated_prompt,
    generated_image,
    caption,
    format,
}: BrandAnalysisProps) {
    const [localFiles, setLocalFiles] = useState<File[]>([]);

    const uploadForm = useForm<{ reference_images: File[]; caption: string; format: string }>({
        reference_images: [],
        caption: '',
        format: 'square',
    });

    const regenerateForm = useForm<{
        caption: string;
        analysis_json: string;
        reference_paths: string[];
        format: string;
    }>({
        caption: caption ?? '',
        analysis_json: result ? JSON.stringify(result) : '',
        reference_paths: selected_reference_paths,
        format: format ?? 'square',
    });

    const hasResult = !!result;
    const hasGeneratedImage = !!(generated_image?.image_base64 || generated_image?.image_data);
    const imageData = generated_image?.image_base64 || generated_image?.image_data;
    const mimeType = generated_image?.mime_type || 'image/png';

    useEffect(() => {
        if (result) {
            regenerateForm.setData((prev) => ({ ...prev, analysis_json: JSON.stringify(result) }));
        }
    }, [result]);

    useEffect(() => {
        if (selected_reference_paths.length) {
            regenerateForm.setData((prev) => ({ ...prev, reference_paths: selected_reference_paths }));
        }
    }, [selected_reference_paths]);

    const handleFiles = (files: FileList | null) => {
        const arr = files ? Array.from(files) : [];
        setLocalFiles(arr);
        uploadForm.setData('reference_images', arr);
    };

    const submitUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post('/projects/wizards/brand-analysis');
    };

    const submitRegenerate = (e: React.FormEvent) => {
        e.preventDefault();
        regenerateForm.post('/projects/wizards/brand-analysis/generate');
    };

    const uploadError = useMemo(() => {
        if (!errors) return null;
        return errors['reference_images'] || errors['caption'];
    }, [errors]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Brand Analysis Wizard', href: '/projects/wizards/brand-analysis' }]}> 
            <Head title="Brand Analysis Wizard" />

            <div className="container max-w-5xl mx-auto py-8 space-y-8">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Brand Analysis Wizard</h1>
                    <p className="text-muted-foreground">
                        Upload images + enter caption → AI analyzes brand DNA → builds on-brand prompt → generates image. All in one flow.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Upload & Generate</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitUpload} className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Reference Images (1-10)</label>
                                <Input
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => handleFiles(e.target.files)}
                                    disabled={uploadForm.processing}
                                />
                                {localFiles.length > 0 && (
                                    <p className="text-xs text-muted-foreground">Selected {localFiles.length} files</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Caption</label>
                                <Textarea
                                    value={uploadForm.data.caption}
                                    onChange={(e) => uploadForm.setData('caption', e.target.value)}
                                    placeholder="e.g., Vibrant founder portrait for spring launch with bold energy"
                                    rows={3}
                                    disabled={uploadForm.processing}
                                />
                                {uploadForm.errors.caption && (
                                    <p className="text-xs text-destructive">{uploadForm.errors.caption}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Format</label>
                                <select
                                    className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    value={uploadForm.data.format}
                                    onChange={(e) => uploadForm.setData('format', e.target.value)}
                                    disabled={uploadForm.processing}
                                >
                                    <option value="square">Square (1:1)</option>
                                    <option value="portrait">Portrait (9:16)</option>
                                    <option value="landscape">Landscape (16:9)</option>
                                </select>
                            </div>

                            {uploadError && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertTitle>Error</AlertTitle>
                                    <AlertDescription>{uploadError}</AlertDescription>
                                </Alert>
                            )}

                            <Button
                                type="submit"
                                disabled={uploadForm.processing || localFiles.length === 0 || !uploadForm.data.caption.trim()}
                                className="w-full"
                            >
                                {uploadForm.processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Analyzing DNA + Generating Image...
                                    </>
                                ) : (
                                    'Analyze & Generate'
                                )}
                            </Button>

                            {uploadForm.progress && (
                                <div className="text-xs text-muted-foreground">Uploading... {uploadForm.progress.percentage}%</div>
                            )}
                        </form>
                    </CardContent>
                </Card>

                {hasResult && (
                    <>
                        <Card className="border-2 border-primary/20">
                            <CardHeader className="bg-muted/50">
                                <CardTitle className="text-lg">Brand DNA Analysis</CardTitle>
                                <div className="text-xs text-muted-foreground">
                                    Images analyzed: {count ?? 0} • {timestamp}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <pre className="whitespace-pre-wrap break-words text-xs bg-muted/50 p-4 rounded-md font-mono max-h-[400px] overflow-auto">
                                    {JSON.stringify(result, null, 2)}
                                </pre>

                                {references.length > 0 && (
                                    <div className="space-y-2">
                                        <div className="text-sm font-medium">
                                            References ({references.length}) — Auto-selected best for generation
                                        </div>
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                            {references.map((ref) => {
                                                const isSelected = selected_reference_paths.includes(ref.path);
                                                return (
                                                    <div
                                                        key={ref.path}
                                                        className={`rounded-md border p-2 space-y-2 ${isSelected ? 'border-primary bg-primary/5' : 'bg-muted/40'}`}
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <div className="text-xs font-medium truncate">{ref.name ?? 'Reference'}</div>
                                                            {isSelected && (
                                                                <div className="text-xs font-semibold text-primary">Used</div>
                                                            )}
                                                        </div>
                                                        <div className="rounded-sm overflow-hidden bg-black/5 aspect-[4/3]">
                                                            <img
                                                                src={ref.url}
                                                                alt={ref.name ?? 'Reference'}
                                                                className="h-full w-full object-cover"
                                                            />
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {generated_prompt && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="h-4 w-4" />
                                        <CardTitle className="text-lg">Generated Prompt</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <pre className="whitespace-pre-wrap break-words text-xs bg-muted/50 p-3 rounded-md font-mono">
                                        {generated_prompt}
                                    </pre>
                                </CardContent>
                            </Card>
                        )}

                        {hasGeneratedImage && imageData && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <ImageIcon className="h-4 w-4" />
                                        <CardTitle className="text-lg">Generated Image</CardTitle>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="rounded-lg border overflow-hidden bg-muted/30 flex justify-center">
                                        <img
                                            src={`data:${mimeType};base64,${imageData}`}
                                            alt="Generated"
                                            className="max-h-[520px] w-full object-contain bg-black/5"
                                        />
                                    </div>
                                    {generated_image?.metadata && (
                                        <details className="text-xs">
                                            <summary className="cursor-pointer font-medium">Metadata</summary>
                                            <pre className="whitespace-pre-wrap break-words bg-muted/40 p-2 rounded-md mt-2">
                                                {JSON.stringify(generated_image.metadata, null, 2)}
                                            </pre>
                                        </details>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Regenerate with New Caption</CardTitle>
                                <div className="text-xs text-muted-foreground">
                                    Reuse the same brand DNA and references, just change the caption.
                                </div>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitRegenerate} className="space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">New Caption</label>
                                        <Textarea
                                            value={regenerateForm.data.caption}
                                            onChange={(e) => regenerateForm.setData('caption', e.target.value)}
                                            placeholder="e.g., Calm morning product shot with soft natural light"
                                            rows={3}
                                            disabled={regenerateForm.processing}
                                        />
                                        {regenerateForm.errors.caption && (
                                            <p className="text-xs text-destructive">{regenerateForm.errors.caption}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Format</label>
                                        <select
                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            value={regenerateForm.data.format}
                                            onChange={(e) => regenerateForm.setData('format', e.target.value)}
                                            disabled={regenerateForm.processing}
                                        >
                                            <option value="square">Square (1:1)</option>
                                            <option value="portrait">Portrait (9:16)</option>
                                            <option value="landscape">Landscape (16:9)</option>
                                        </select>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={regenerateForm.processing || !regenerateForm.data.caption.trim()}
                                        className="w-full"
                                    >
                                        {regenerateForm.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Regenerating...
                                            </>
                                        ) : (
                                            'Regenerate Image'
                                        )}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </>
                )}

                {!hasResult && (
                    <Alert className="border-dashed">
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertTitle>Ready to test</AlertTitle>
                        <AlertDescription>
                            Upload images, enter a caption, and click "Analyze & Generate" to see the full workflow: DNA analysis → prompt
                            building → image generation.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </AppLayout>
    );
}
