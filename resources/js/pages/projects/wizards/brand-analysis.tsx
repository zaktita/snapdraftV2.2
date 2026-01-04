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
    fal_result?: any;
    count?: number;
    timestamp?: string;
    errors?: Record<string, string>;
    references?: ReferenceItem[];
    gemini_selected_paths?: string[];
    fal_selected_paths?: string[];
    selected_reference_paths?: string[];
    generated_prompt?: string;
    generated_image?: GenerationImage;
    gemini_prompt?: string;
    fal_prompt?: string;
    gemini_generation?: GenerationImage;
    fal_generation?: GenerationImage;
    caption?: string;
    format?: string;
}

export default function BrandAnalysisWizard({
    result,
    fal_result,
    count,
    timestamp,
    errors,
    references = [],
    gemini_selected_paths = [],
    fal_selected_paths = [],
    selected_reference_paths = [],
    generated_prompt,
    generated_image,
    gemini_prompt,
    fal_prompt,
    gemini_generation,
    fal_generation,
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
                        {/* Two-Column Comparison */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Gemini Analysis */}
                            <Card className="border-2 border-blue-500/30 bg-blue-50/30">
                                <CardHeader className="bg-blue-100/40 border-b border-blue-200/50">
                                    <CardTitle className="text-lg text-blue-900">Gemini 2.5 Pro Analysis</CardTitle>
                                    <div className="text-xs text-blue-700">Google's AI Model</div>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <div className="space-y-2">
                                        <div className="text-xs font-semibold text-muted-foreground uppercase">Brand DNA</div>
                                        <pre className="whitespace-pre-wrap break-words text-xs bg-white/50 p-3 rounded-md font-mono max-h-[300px] overflow-auto border border-blue-200/30">
                                            {JSON.stringify(result, null, 2)}
                                        </pre>
                                    </div>

                                    {gemini_prompt && (
                                        <div className="space-y-2">
                                            <div className="text-xs font-semibold text-muted-foreground uppercase">Generated Prompt</div>
                                            <pre className="whitespace-pre-wrap break-words text-xs bg-white/50 p-3 rounded-md font-mono border border-blue-200/30">
                                                {gemini_prompt}
                                            </pre>
                                        </div>
                                    )}

                                    {gemini_generation && (gemini_generation.image_base64 || gemini_generation.image_data) && (
                                        <div className="space-y-2">
                                            <div className="text-xs font-semibold text-muted-foreground uppercase">Generated Image</div>
                                            <div className="rounded-lg border border-blue-200/50 overflow-hidden bg-white flex justify-center">
                                                <img
                                                    src={`data:${gemini_generation.mime_type || 'image/png'};base64,${gemini_generation.image_base64 || gemini_generation.image_data}`}
                                                    alt="Gemini Generated"
                                                    className="max-h-[400px] w-full object-contain"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* OpenRouter/GPT-5.2 Analysis */}
                            <Card className="border-2 border-purple-500/30 bg-purple-50/30">
                                <CardHeader className="bg-purple-100/40 border-b border-purple-200/50">
                                    <CardTitle className="text-lg text-purple-900">OpenRouter GPT-5.2 Analysis</CardTitle>
                                    <div className="text-xs text-purple-700">Alternative AI Model</div>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <div className="space-y-2">
                                        <div className="text-xs font-semibold text-muted-foreground uppercase">Brand DNA</div>
                                        <pre className="whitespace-pre-wrap break-words text-xs bg-white/50 p-3 rounded-md font-mono max-h-[300px] overflow-auto border border-purple-200/30">
                                            {JSON.stringify(fal_result, null, 2)}
                                        </pre>
                                    </div>

                                    {fal_prompt && (
                                        <div className="space-y-2">
                                            <div className="text-xs font-semibold text-muted-foreground uppercase">Generated Prompt</div>
                                            <pre className="whitespace-pre-wrap break-words text-xs bg-white/50 p-3 rounded-md font-mono border border-purple-200/30">
                                                {fal_prompt}
                                            </pre>
                                        </div>
                                    )}

                                    {fal_generation && (fal_generation.image_base64 || fal_generation.image_data) && (
                                        <div className="space-y-2">
                                            <div className="text-xs font-semibold text-muted-foreground uppercase">Generated Image</div>
                                            <div className="rounded-lg border border-purple-200/50 overflow-hidden bg-white flex justify-center">
                                                <img
                                                    src={`data:${fal_generation.mime_type || 'image/png'};base64,${fal_generation.image_base64 || fal_generation.image_data}`}
                                                    alt="GPT-5.2 Generated"
                                                    className="max-h-[400px] w-full object-contain"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Combined References Section */}
                        {references.length > 0 && (
                            <Card className="border-2 border-amber-500/30">
                                <CardHeader className="bg-amber-100/40 border-b border-amber-200/50">
                                    <CardTitle className="text-lg">All Reference Images ({references.length})</CardTitle>
                                    <div className="text-xs text-muted-foreground">
                                        Images analyzed: {count ?? 0} • {timestamp}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {/* All References Grid */}
                                    <div className="space-y-6">
                                        <div className="space-y-3">
                                            <div className="text-sm font-semibold text-gray-900">Uploaded References</div>
                                            <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                                {references.map((ref, idx) => {
                                                    const geminSelected = gemini_selected_paths?.includes(ref.path);
                                                    const gptSelected = fal_selected_paths?.includes(ref.path);
                                                    const bothSelected = geminSelected && gptSelected;
                                                    const noneSelected = !geminSelected && !gptSelected;
                                                    
                                                    return (
                                                        <div
                                                            key={ref.path}
                                                            className={`relative rounded-lg overflow-hidden border-2 transition-all hover:shadow-md ${
                                                                bothSelected
                                                                    ? 'border-green-400 ring-2 ring-green-200 bg-green-50'
                                                                    : geminSelected
                                                                    ? 'border-blue-400 ring-2 ring-blue-200 bg-blue-50'
                                                                    : gptSelected
                                                                    ? 'border-purple-400 ring-2 ring-purple-200 bg-purple-50'
                                                                    : 'border-gray-300 opacity-70 bg-gray-100'
                                                            }`}
                                                        >
                                                            {/* Image Container */}
                                                            <div className="aspect-square overflow-hidden bg-black/10">
                                                                <img
                                                                    src={ref.url}
                                                                    alt={ref.name ?? 'Reference'}
                                                                    className="w-full h-full object-cover hover:scale-105 transition-transform"
                                                                />
                                                            </div>
                                                            
                                                            {/* Selection Badges */}
                                                            <div className="absolute top-1 left-1 right-1 flex gap-1 flex-wrap">
                                                                {geminSelected && (
                                                                    <span className="inline-flex items-center gap-1 bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                                                        <span className="w-1.5 h-1.5 bg-white rounded-full"></span>G
                                                                    </span>
                                                                )}
                                                                {gptSelected && (
                                                                    <span className="inline-flex items-center gap-1 bg-purple-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                                                        <span className="w-1.5 h-1.5 bg-white rounded-full"></span>P
                                                                    </span>
                                                                )}
                                                            </div>

                                                            {/* Filename */}
                                                            <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-2">
                                                                <div className="text-xs font-medium text-white truncate">
                                                                    {idx + 1}. {ref.name?.split('.')[0] ?? 'Reference'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {/* Selection Summary */}
                                        <div className="grid grid-cols-2 gap-4 pt-4 border-t border-amber-200/50">
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-3 h-3 rounded-full bg-blue-400 ring-2 ring-blue-200"></div>
                                                    <span className="text-sm font-medium text-blue-900">
                                                        Gemini Selected: {gemini_selected_paths?.length ?? 0}
                                                    </span>
                                                </div>
                                                <div className="ml-5 text-xs text-blue-700">
                                                    {gemini_selected_paths?.length > 0 
                                                        ? references
                                                            .filter(r => gemini_selected_paths?.includes(r.path))
                                                            .map(r => r.name?.split('.')[0] ?? 'Image')
                                                            .join(', ')
                                                        : 'No images selected'}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-3 h-3 rounded-full bg-purple-400 ring-2 ring-purple-200"></div>
                                                    <span className="text-sm font-medium text-purple-900">
                                                        GPT-5.2 Selected: {fal_selected_paths?.length ?? 0}
                                                    </span>
                                                </div>
                                                <div className="ml-5 text-xs text-purple-700">
                                                    {fal_selected_paths?.length > 0 
                                                        ? references
                                                            .filter(r => fal_selected_paths?.includes(r.path))
                                                            .map(r => r.name?.split('.')[0] ?? 'Image')
                                                            .join(', ')
                                                        : 'No images selected'}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
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
