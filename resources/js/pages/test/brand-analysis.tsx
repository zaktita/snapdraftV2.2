import { FormEvent, useMemo, useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle, Upload, Sparkles } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface ColorPalette {
    color: string;
    name: string;
    coverage_percentage: number;
    usage: string;
}

interface ElementCategory {
    [key: string]: boolean | number | string;
}

interface ElementsDetected {
    text_elements: ElementCategory;
    temporal_elements: ElementCategory;
    location_elements: ElementCategory;
    people_elements: ElementCategory;
    ecommerce_elements: ElementCategory;
    marketing_elements: ElementCategory;
    branding_elements: ElementCategory;
    creative_artistic_elements: ElementCategory;
    data_visualization: ElementCategory;
    interactive_ui: ElementCategory;
}

interface ImageAnalysis {
    index: number;
    cluster_id?: number;
    layout_complexity: 'simple' | 'moderate' | 'complex';
    elements_detected: ElementsDetected;
    quality: 'excellent' | 'good' | 'usable' | 'poor';
    name: string;
    url: string;
    path: string;
}

interface StyleCluster {
    cluster_id: number;
    name: string;
    image_indices: number[];
    coherence_score: number;
    dominant_colors: string[];
    typography_style: string;
    mood: string;
    layout_pattern: string;
}

interface BrandAnalysisResult {
    style_clusters: StyleCluster[];
    image_analysis: ImageAnalysis[];
    brand_dna?: {
        visual_identity?: {
            color_system?: {
                primary_palette?: ColorPalette[];
            };
        };
    };
    coherence_analysis?: {
        overall_coherence_score: number;
    };
}

interface PromptResult {
    model: string;
    status: 'success' | 'failed';
    duration_ms: number;
    cost?: number;
    error?: string;
    cluster_id?: number;
    cluster_name?: string;
    prompt?: string;
    raw_response?: string;
    selected_indices?: number[];
    reference_roles?: Record<string, 'style_anchor' | 'typography_reference' | 'composition_guide'>;
    complexity?: 'simple' | 'complex';
    layout_type?: 'hero' | 'split' | 'grid' | 'text-only' | 'custom';
    layout_construction?: {
        layer_order?: string;
        masking_rules?: string;
        text_placement?: string;
        spacing?: string;
        overlays?: string;
    };
    niche?: string;
    reasoning?: string;
}

interface PromptResults {
    successful: PromptResult[];
    failed: PromptResult[];
    total_cost: number;
}

interface PageProps {
    result?: BrandAnalysisResult | null;
    references?: Array<{ path: string; url: string; name: string }>;
    count?: number;
    caption_analysis?: any;
    selection_result?: any;
    test_caption?: string;
    prompt_results?: PromptResults;
    [key: string]: any;
}

export default function BrandAnalysisTest() {
    const { result: propsResult, references, caption_analysis, selection_result, test_caption, prompt_results } = usePage<PageProps>().props;
    const [result, setResult] = useState<BrandAnalysisResult | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedFile, setSelectedFile] = useState<FileList | null>(null);
    const [caption, setCaption] = useState('');
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [textDensity, setTextDensity] = useState<'light' | 'standard' | 'heavy'>('standard');

    // Debug logging
    useEffect(() => {
        console.log('Component props updated:', {
            hasPropsResult: !!propsResult,
            hasPromptResults: !!prompt_results,
            promptResultsStructure: prompt_results,
        });
    }, [propsResult, prompt_results]);

    // Initialize from props or localStorage on first mount
    useEffect(() => {
        if (propsResult) {
            // Props take priority
            setResult(propsResult);
            localStorage.setItem('brand_analysis_test_result', JSON.stringify(propsResult));
        } else {
            // Try to restore from localStorage
            const stored = localStorage.getItem('brand_analysis_test_result');
            if (stored) {
                try {
                    setResult(JSON.parse(stored));
                } catch {
                    console.error('Failed to parse stored analysis');
                }
            }
        }
    }, [propsResult]);

    const handleFileChange = (e: FormEvent<HTMLInputElement>) => {
        const target = e.currentTarget;
        if (target.files) {
            setSelectedFile(target.files);
        }
    };

    const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!selectedFile || selectedFile.length === 0) {
            alert('Please select at least one image');
            return;
        }

        const formData = new FormData();
        for (let i = 0; i < selectedFile.length; i++) {
            formData.append('reference_images[]', selectedFile[i]);
        }

        // Clear any cached analysis so the next result overwrites localStorage
        localStorage.removeItem('brand_analysis_test_result');

        setIsLoading(true);
        router.post('/test/brand-analysis', formData as any, {
            onFinish: () => setIsLoading(false),
        });
    };

    const handleTestCaption = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!caption) {
            alert('Please enter a caption');
            return;
        }

        if (!result) {
            alert('Please upload and analyze images first');
            return;
        }

        const formData = new FormData();
        formData.append('caption', caption);
        formData.append('title', title);
        formData.append('description', description);
        formData.append('text_density', textDensity);
        formData.append('analysis_json', JSON.stringify(result));

        setIsLoading(true);
        router.post('/test/brand-analysis/caption', formData as any, {
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('Caption analysis successful:', page.props);
            },
            onError: (errors) => {
                console.error('Caption analysis error:', errors);
                alert('Error: ' + Object.values(errors).flat().join(', '));
            },
            onFinish: () => {
                console.log('Caption analysis finished');
                setIsLoading(false);
            },
        });
    };

    const clusters = useMemo(() => {
        if (!result?.style_clusters) return [];
        return result.style_clusters.sort((a, b) => b.coherence_score - a.coherence_score);
    }, [result]);

    const images = useMemo(() => {
        if (!result?.image_analysis) return [];
        return result.image_analysis;
    }, [result]);

    const getElementCount = (elements: ElementsDetected): number => {
        let count = 0;
        Object.values(elements).forEach(category => {
            if (typeof category === 'object') {
                Object.values(category).forEach(value => {
                    if (value === true || (typeof value === 'number' && value > 0)) {
                        count++;
                    }
                });
            }
        });
        return count;
    };

    const renderElements = (elements: ElementsDetected) => {
        const categories = [
            { key: 'text_elements', label: 'Text Elements', icon: '📝' },
            { key: 'temporal_elements', label: 'Temporal', icon: '📅' },
            { key: 'location_elements', label: 'Location', icon: '📍' },
            { key: 'people_elements', label: 'People', icon: '👥' },
            { key: 'ecommerce_elements', label: 'Ecommerce', icon: '🛒' },
            { key: 'marketing_elements', label: 'Marketing', icon: '📢' },
            { key: 'branding_elements', label: 'Branding', icon: '🎨' },
            { key: 'creative_artistic_elements', label: 'Creative/Artistic', icon: '🖼️' },
            { key: 'data_visualization', label: 'Data Visualization', icon: '📊' },
            { key: 'interactive_ui', label: 'Interactive UI', icon: '🖱️' },
        ];

        return (
            <div className="space-y-4">
                {categories.map(({ key, label, icon }) => {
                    const category = elements[key as keyof ElementsDetected] as ElementCategory;
                    if (!category) return null;

                    const detected = Object.entries(category)
                        .filter(([_, value]) => value === true || (typeof value === 'number' && value > 0))
                        .map(([name]) => name);

                    if (detected.length === 0) return null;

                    return (
                        <div key={key} className="space-y-2">
                            <h4 className="font-semibold text-sm flex items-center gap-2">
                                <span>{icon}</span>
                                {label}
                            </h4>
                            <div className="flex flex-wrap gap-2">
                                {detected.map(element => (
                                    <Badge key={element} variant="secondary" className="text-xs">
                                        {element.replace(/_/g, ' ')}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    };

    const complexityColor = (complexity: string) => {
        switch (complexity) {
            case 'simple':
                return 'bg-blue-100 text-blue-800';
            case 'moderate':
                return 'bg-yellow-100 text-yellow-800';
            case 'complex':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const qualityColor = (quality: string) => {
        switch (quality) {
            case 'excellent':
                return 'text-green-600';
            case 'good':
                return 'text-blue-600';
            case 'usable':
                return 'text-yellow-600';
            case 'poor':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    return (
        <div className="min-h-screen bg-gray-50 py-8">
            <Head title="Brand Analysis Test" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-4xl font-bold text-gray-900 mb-2">Brand Analysis Test UI</h1>
                        <p className="text-gray-600">Upload reference images to visualize style clustering and element detection</p>
                    </div>
                    {result && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                localStorage.removeItem('brand_analysis_test_result');
                                setResult(null);
                            }}
                        >
                            Clear Cache
                        </Button>
                    )}
                </div>

                {/* Upload Section */}
                <Card className="mb-8">
                    <CardHeader>
                        <CardTitle>Step 1: Upload Reference Images</CardTitle>
                        <CardDescription>Select 1-10 images to analyze</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-gray-400 transition">
                                <Upload className="mx-auto h-12 w-12 text-gray-400 mb-3" />
                                <label className="cursor-pointer">
                                    <input
                                        type="file"
                                        multiple
                                        accept="image/*"
                                        onChange={handleFileChange}
                                        className="sr-only"
                                    />
                                    <span className="font-semibold text-gray-900">Click to upload</span>
                                    {' '}or drag and drop
                                </label>
                                <p className="text-sm text-gray-500 mt-1">PNG, JPG, WebP up to 10MB each</p>
                            </div>

                            {selectedFile && (
                                <div className="mt-4">
                                    <p className="text-sm font-semibold text-gray-700 mb-2">
                                        {selectedFile.length} file(s) selected
                                    </p>
                                    <ul className="space-y-1 text-sm text-gray-600">
                                        {Array.from(selectedFile).map((file, i) => (
                                            <li key={i}>• {file.name}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <Button
                                type="submit"
                                disabled={isLoading || !selectedFile || selectedFile.length === 0}
                                className="w-full"
                            >
                                {isLoading ? 'Analyzing...' : 'Analyze Images'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Caption Testing Section - Only show if analysis exists */}
                {result && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5" />
                                Step 2: Test Caption Matching
                            </CardTitle>
                            <CardDescription>Enter a caption to see which cluster and images get selected</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleTestCaption} className="space-y-4">
                                <div>
                                    <Label htmlFor="caption">Caption *</Label>
                                    <Textarea
                                        id="caption"
                                        placeholder="E.g., 'Join us at Career Expo 2024 on March 15th'"
                                        value={caption}
                                        onChange={(e) => setCaption(e.target.value)}
                                        className="mt-1"
                                        rows={2}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="text_density">Text Density</Label>
                                    <div className="grid grid-cols-3 gap-2 mt-2">
                                        {[
                                            { value: 'light', label: 'Light' },
                                            { value: 'standard', label: 'Standard' },
                                            { value: 'heavy', label: 'Heavy' },
                                        ].map(option => (
                                            <Button
                                                key={option.value}
                                                type="button"
                                                variant={textDensity === option.value ? 'default' : 'outline'}
                                                className="w-full"
                                                onClick={() => setTextDensity(option.value as 'light' | 'standard' | 'heavy')}
                                            >
                                                {option.label}
                                            </Button>
                                        ))}
                                    </div>
                                    <p className="text-xs text-gray-500 mt-1">
                                        Light: only essentials • Standard: concise headline • Heavy: keep most details without filler
                                    </p>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="title">Title (optional)</Label>
                                        <Input
                                            id="title"
                                            placeholder="E.g., 'Career Event'"
                                            value={title}
                                            onChange={(e) => setTitle(e.target.value)}
                                            className="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="description">Description (optional)</Label>
                                        <Input
                                            id="description"
                                            placeholder="E.g., 'Networking opportunity'"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                            className="mt-1"
                                        />
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    disabled={isLoading || !caption}
                                    className="w-full"
                                >
                                    {isLoading ? 'Analyzing...' : 'Test Caption Matching'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Model Prompt Results - Show after caption testing */}
                {(() => {
                    console.log('Checking prompt results display condition:', {
                        hasPromptResults: !!prompt_results,
                        successfulLength: prompt_results?.successful?.length,
                        failedLength: prompt_results?.failed?.length,
                        shouldDisplay: !!(prompt_results && (prompt_results.successful?.length > 0 || prompt_results.failed?.length > 0)),
                    });
                    return null;
                })()}
                {prompt_results && (prompt_results.successful?.length > 0 || prompt_results.failed?.length > 0) && (
                    <Card className="border-blue-200 bg-blue-50">
                        <CardHeader>
                            <CardTitle className="text-blue-900">🤖 AI Model Prompt Generation Results</CardTitle>
                            <CardDescription className="text-blue-700">
                                {prompt_results.successful?.length || 0} model(s) successfully generated prompts
                                {prompt_results.failed?.length > 0 && ` • ${prompt_results.failed.length} model(s) failed`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {/* Show failed models first if all failed */}
                            {prompt_results.failed && prompt_results.failed.length > 0 && prompt_results.successful?.length === 0 && (
                                <div className="mb-6">
                                    <Alert className="border-red-400 bg-red-50">
                                        <AlertCircle className="h-4 w-4 text-red-600" />
                                        <AlertDescription className="text-red-800">
                                            <strong>All models failed to generate prompts.</strong> See details below:
                                        </AlertDescription>
                                    </Alert>
                                    <div className="mt-4 space-y-2">
                                        {prompt_results.failed.map((result, idx) => (
                                            <div key={`failed-${idx}`} className="bg-white p-4 rounded border border-red-200">
                                                <div className="flex items-center justify-between mb-2">
                                                    <span className="font-semibold text-gray-900">{result.model}</span>
                                                    <Badge variant="destructive">Failed</Badge>
                                                </div>
                                                <div className="text-sm text-red-600">{result.error}</div>
                                                <div className="text-xs text-gray-500 mt-1">Duration: {result.duration_ms}ms</div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {prompt_results.successful && prompt_results.successful.length > 0 && (
                            <Tabs defaultValue={`model-${prompt_results.successful[0]?.model}`} className="w-full">
                                <TabsList className="grid w-full" style={{ gridTemplateColumns: `repeat(${Math.min(prompt_results.successful.length, 5)}, 1fr)` }}>
                                    {prompt_results.successful.map(result => (
                                        <TabsTrigger key={`tab-${result.model}`} value={`model-${result.model}`} className="text-xs">
                                            {result.model.split('/').pop()}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>

                                {prompt_results.successful.map(result => (
                                    <TabsContent key={`content-${result.model}`} value={`model-${result.model}`} className="space-y-6">
                                        {/* Model Info Header */}
                                        <div className="bg-white p-4 rounded-lg border">
                                            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                                                <div>
                                                    <div className="text-sm text-gray-600">Model</div>
                                                    <div className="font-semibold text-gray-900">{result.model}</div>
                                                </div>
                                                <div>
                                                    <div className="text-sm text-gray-600">Generation Time</div>
                                                    <div className="font-semibold text-blue-600">{result.duration_ms}ms</div>
                                                </div>
                                                <div>
                                                    <div className="text-sm text-gray-600">Cost</div>
                                                    <div className="font-semibold text-green-600">${result.cost?.toFixed(3)}</div>
                                                </div>
                                                <div>
                                                    <div className="text-sm text-gray-600">Complexity</div>
                                                    <Badge className={result.complexity === 'complex' ? 'bg-orange-600' : 'bg-blue-600'}>
                                                        {result.complexity || 'N/A'}
                                                    </Badge>
                                                </div>
                                                <div>
                                                    <div className="text-sm text-gray-600">Layout Type</div>
                                                    <Badge variant="outline">
                                                        {result.layout_type || 'N/A'}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-4 mt-3 pt-3 border-t">
                                                <div>
                                                    <div className="text-sm text-gray-600">Selected Cluster</div>
                                                    <div className="font-semibold text-purple-600">
                                                        {result.cluster_name || `Cluster ${result.cluster_id}`}
                                                    </div>
                                                </div>
                                                {result.niche && (
                                                    <div>
                                                        <div className="text-sm text-gray-600">Detected Niche</div>
                                                        <div className="font-semibold text-indigo-600 capitalize">{result.niche}</div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Model's Reasoning */}
                                        {result.reasoning && (
                                            <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                                <h4 className="font-semibold text-blue-900 mb-2">Model's Analysis & Reasoning</h4>
                                                <p className="text-sm text-blue-800 whitespace-pre-wrap">{result.reasoning}</p>
                                            </div>
                                        )}

                                        {/* Model's Selected Reference Images with Roles */}
                                        {result.selected_indices && result.selected_indices.length > 0 && (
                                            <div className="bg-white p-4 rounded-lg border">
                                                <h4 className="font-semibold text-gray-900 mb-3">Images Selected by {result.model.split('/').pop()}</h4>
                                                <div className="grid grid-cols-3 gap-4">
                                                    {result.selected_indices.map((index, idx) => {
                                                        const image = propsResult?.image_analysis?.[index];
                                                        const role = result.reference_roles?.[index.toString()];
                                                        const roleLabels: Record<string, string> = {
                                                            'style_anchor': '🎨 Style Anchor',
                                                            'typography_reference': '✍️ Typography',
                                                            'composition_guide': '📐 Composition',
                                                        };
                                                        const roleColors: Record<string, string> = {
                                                            'style_anchor': 'bg-purple-100 text-purple-800',
                                                            'typography_reference': 'bg-blue-100 text-blue-800',
                                                            'composition_guide': 'bg-green-100 text-green-800',
                                                        };
                                                        const label = role ? roleLabels[role] : `Reference ${idx + 1}`;
                                                        const badgeColor = role ? roleColors[role] : 'bg-gray-100 text-gray-800';
                                                        
                                                        return (
                                                            <div key={`ref-${index}`} className="relative">
                                                                <div className="aspect-square rounded-lg overflow-hidden border-2 border-gray-200">
                                                                    {image?.url && (
                                                                        <img src={image.url} alt={`Reference ${index}`} className="w-full h-full object-cover" />
                                                                    )}
                                                                </div>
                                                                <div className={`absolute top-2 left-2 px-2 py-1 rounded text-xs font-semibold ${badgeColor}`}>
                                                                    {label}
                                                                </div>
                                                                <div className="mt-1 text-xs text-gray-600 text-center">
                                                                    Image {index}
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}

                                        {/* Layout Construction Details (Complex layouts only) */}
                                        {result.complexity === 'complex' && result.layout_construction && (
                                            <div className="bg-orange-50 p-4 rounded-lg border border-orange-200">
                                                <h4 className="font-semibold text-orange-900 mb-3 flex items-center gap-2">
                                                    <span>🏗️</span>
                                                    Layout Construction (Complex)
                                                </h4>
                                                <div className="space-y-3 text-sm">
                                                    {result.layout_construction.layer_order && (
                                                        <div>
                                                            <div className="font-semibold text-orange-800">Layer Order:</div>
                                                            <div className="text-orange-700 mt-1">{result.layout_construction.layer_order}</div>
                                                        </div>
                                                    )}
                                                    {result.layout_construction.masking_rules && (
                                                        <div>
                                                            <div className="font-semibold text-orange-800">Masking Rules:</div>
                                                            <div className="text-orange-700 mt-1">{result.layout_construction.masking_rules}</div>
                                                        </div>
                                                    )}
                                                    {result.layout_construction.text_placement && (
                                                        <div>
                                                            <div className="font-semibold text-orange-800">Text Placement:</div>
                                                            <div className="text-orange-700 mt-1">{result.layout_construction.text_placement}</div>
                                                        </div>
                                                    )}
                                                    {result.layout_construction.spacing && (
                                                        <div>
                                                            <div className="font-semibold text-orange-800">Spacing:</div>
                                                            <div className="text-orange-700 mt-1">{result.layout_construction.spacing}</div>
                                                        </div>
                                                    )}
                                                    {result.layout_construction.overlays && (
                                                        <div>
                                                            <div className="font-semibold text-orange-800">Overlays/Depth:</div>
                                                            <div className="text-orange-700 mt-1">{result.layout_construction.overlays}</div>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Generated Prompt */}
                                        <div className="bg-white p-4 rounded-lg border">
                                            <div className="flex items-center justify-between mb-3">
                                                <h4 className="font-semibold text-gray-900">Generated Prompt</h4>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(result.prompt || '');
                                                    }}
                                                >
                                                    Copy
                                                </Button>
                                            </div>
                                            <div className="bg-gray-50 p-3 rounded text-sm text-gray-700 whitespace-pre-wrap font-mono max-h-96 overflow-y-auto">
                                                {result.prompt}
                                            </div>
                                        </div>

                                        {/* Select & Generate Button */}
                                        <Button className="w-full bg-blue-600 hover:bg-blue-700 text-white">
                                            Use {result.model.split('/').pop()}'s Selection & Generate Image
                                        </Button>
                                    </TabsContent>
                                ))}
                            </Tabs>
                            )}

                            {/* Failed Models Notice */}
                            {prompt_results.failed.length > 0 && (
                                <div className="mt-6 pt-6 border-t">
                                    <p className="text-sm font-semibold text-gray-700 mb-3">Failed Models:</p>
                                    <div className="space-y-2">
                                        {prompt_results.failed.map(result => (
                                            <div key={`failed-${result.model}`} className="flex items-center justify-between bg-red-50 p-3 rounded border border-red-200">
                                                <span className="text-sm text-gray-700">{result.model}</span>
                                                <span className="text-xs text-red-600">{result.error}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Cost Summary */}
                            <div className="mt-6 pt-6 border-t text-sm">
                                <span className="text-gray-600">Total cost for all prompts: </span>
                                <span className="font-bold text-green-600">${prompt_results.total_cost?.toFixed(3)}</span>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Results Section */}
                {result && (
                    <div className="space-y-8">
                        {/* Overall Stats */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Analysis Results</CardTitle>
                                <CardDescription>Cluster and element detection overview</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div className="bg-blue-50 p-4 rounded-lg">
                                        <div className="text-2xl font-bold text-blue-600">{clusters.length}</div>
                                        <div className="text-sm text-gray-600">Style Clusters</div>
                                    </div>
                                    <div className="bg-green-50 p-4 rounded-lg">
                                        <div className="text-2xl font-bold text-green-600">{images.length}</div>
                                        <div className="text-sm text-gray-600">Images</div>
                                    </div>
                                    <div className="bg-purple-50 p-4 rounded-lg">
                                        <div className="text-2xl font-bold text-purple-600">
                                            {Math.round(result.coherence_analysis?.overall_coherence_score ?? 0)}%
                                        </div>
                                        <div className="text-sm text-gray-600">Coherence</div>
                                    </div>
                                    <div className="bg-orange-50 p-4 rounded-lg">
                                        <div className="text-2xl font-bold text-orange-600">
                                            {result.brand_dna?.visual_identity?.color_system?.primary_palette?.length ?? 0}
                                        </div>
                                        <div className="text-sm text-gray-600">Colors</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Caption Analysis & Selection Results */}
                        {selection_result && caption_analysis && (
                            <Card className="border-blue-200 bg-blue-50">
                                <CardHeader>
                                    <CardTitle className="text-blue-900">📊 Caption Matching Results</CardTitle>
                                    <CardDescription className="text-blue-700">
                                        Testing caption: "{test_caption}"
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Caption Analysis */}
                                    <div>
                                        <h3 className="font-semibold text-lg mb-3">Caption Requirements</h3>
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            <div className="bg-white p-3 rounded border">
                                                <div className="text-sm text-gray-600">Complexity</div>
                                                <div className="font-bold text-blue-600 capitalize">
                                                    {caption_analysis.layout_complexity}
                                                </div>
                                            </div>
                                            <div className="bg-white p-3 rounded border">
                                                <div className="text-sm text-gray-600">Intent</div>
                                                <div className="font-bold text-blue-600 capitalize">
                                                    {caption_analysis.intent}
                                                </div>
                                            </div>
                                            <div className="bg-white p-3 rounded border">
                                                <div className="text-sm text-gray-600">Tone</div>
                                                <div className="font-bold text-blue-600 capitalize">
                                                    {caption_analysis.tone}
                                                </div>
                                            </div>
                                            <div className="bg-white p-3 rounded border">
                                                <div className="text-sm text-gray-600">Required Elements</div>
                                                <div className="font-bold text-blue-600">
                                                    {caption_analysis.priority_elements?.length || 0}
                                                </div>
                                            </div>
                                        </div>

                                        {caption_analysis.priority_elements && (
                                            <div className="mt-3">
                                                <div className="text-sm font-semibold text-gray-700 mb-2">Priority Elements:</div>
                                                <div className="flex flex-wrap gap-2">
                                                    {caption_analysis.priority_elements.map((el: string) => (
                                                        <Badge key={el} variant="secondary">{el.replace(/_/g, ' ')}</Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Mismatch Warning */}
                                    {selection_result.mismatch_warning && (
                                        <Alert className="border-yellow-400 bg-yellow-50">
                                            <AlertCircle className="h-4 w-4 text-yellow-600" />
                                            <AlertDescription className="text-yellow-800">
                                                <strong>⚠️ Mismatch Detected:</strong> {selection_result.mismatch_details}
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Selected Images */}
                                    <div>
                                        <h3 className="font-semibold text-lg mb-3">
                                            ✅ Selected Images ({selection_result.selected?.length || 0})
                                        </h3>
                                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                                            {selection_result.selected?.map((img: any, idx: number) => (
                                                <div key={idx} className="bg-white p-3 rounded-lg border-2 border-green-500 shadow-md">
                                                    <div className="aspect-square bg-gray-100 rounded mb-2 overflow-hidden">
                                                        <img src={img.url} alt={img.name} className="w-full h-full object-cover" />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <div className="text-xs font-semibold truncate" title={img.name}>
                                                            {img.name}
                                                        </div>
                                                        <div className="flex items-center justify-between">
                                                            <Badge className="bg-green-600 text-white text-xs">
                                                                Score: {img.match_score?.toFixed(1)}
                                                            </Badge>
                                                            <span className="text-xs text-gray-500">
                                                                Cluster {img.cluster_id}
                                                            </span>
                                                        </div>
                                                        <div className="text-xs text-gray-600">
                                                            {img.layout_complexity} • {img.quality}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Scoring Breakdown for Top Image */}
                                    {selection_result.selected?.[0] && (
                                        <div className="bg-white p-4 rounded-lg border">
                                            <h4 className="font-semibold mb-3">Top Match Breakdown</h4>
                                            <div className="space-y-2 text-sm">
                                                <div className="flex justify-between">
                                                    <span>Image:</span>
                                                    <span className="font-semibold">{selection_result.selected[0].name}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Total Score:</span>
                                                    <span className="font-bold text-green-600">
                                                        {selection_result.selected[0].match_score?.toFixed(2)}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-gray-500 mt-2">
                                                    Scoring: Style Match (40%) + Element Match (30%) + Complexity (20%) + Quality (10%)
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Tabs for Clusters */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Style Clusters</CardTitle>
                                <CardDescription>Images grouped by visual similarity</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Tabs defaultValue={`cluster-${clusters[0]?.cluster_id}`} className="w-full">
                                    <TabsList className="grid w-full" style={{ gridTemplateColumns: `repeat(${Math.min(clusters.length, 5)}, 1fr)` }}>
                                        {clusters.map(cluster => (
                                            <TabsTrigger key={`tab-${cluster.cluster_id}`} value={`cluster-${cluster.cluster_id}`}>
                                                Cluster {cluster.cluster_id}
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>

                                    {clusters.map(cluster => (
                                        <TabsContent key={`content-${cluster.cluster_id}`} value={`cluster-${cluster.cluster_id}`} className="space-y-6">
                                            {/* Cluster Header Info */}
                                            <div className="bg-gray-50 p-6 rounded-lg space-y-4">
                                                <div>
                                                    <h3 className="text-xl font-bold text-gray-900">{cluster.name}</h3>
                                                    <p className="text-sm text-gray-600 mt-1">{cluster.image_indices.length} images in this cluster</p>
                                                </div>

                                                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                                    <div>
                                                        <div className="text-sm font-semibold text-gray-700">Coherence Score</div>
                                                        <div className="text-2xl font-bold text-blue-600">{cluster.coherence_score}%</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-sm font-semibold text-gray-700">Typography</div>
                                                        <div className="text-sm text-gray-600">{cluster.typography_style}</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-sm font-semibold text-gray-700">Mood</div>
                                                        <div className="text-sm text-gray-600">{cluster.mood}</div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <div className="text-sm font-semibold text-gray-700 mb-2">Dominant Colors</div>
                                                    <div className="flex gap-2">
                                                        {cluster.dominant_colors.map((color, i) => (
                                                            <div
                                                                key={i}
                                                                className="w-12 h-12 rounded border border-gray-300 shadow-sm"
                                                                style={{ backgroundColor: color }}
                                                                title={color}
                                                            />
                                                        ))}
                                                    </div>
                                                </div>

                                                <div>
                                                    <div className="text-sm font-semibold text-gray-700 mb-2">Layout Pattern</div>
                                                    <p className="text-sm text-gray-600">{cluster.layout_pattern}</p>
                                                </div>
                                            </div>

                                            {/* Images in Cluster */}
                                            <div>
                                                <h4 className="font-semibold text-gray-900 mb-4">Images in Cluster</h4>
                                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                                    {cluster.image_indices.map(imageIndex => {
                                                        const image = images[imageIndex];
                                                        if (!image) return null;

                                                        return (
                                                            <div key={imageIndex} className="space-y-3">
                                                                {/* Image Preview */}
                                                                <div className="aspect-square bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                                                                    <img
                                                                        src={image.url}
                                                                        alt={image.name}
                                                                        className="w-full h-full object-cover"
                                                                    />
                                                                </div>

                                                                {/* Image Info */}
                                                                <div className="space-y-2">
                                                                    <div>
                                                                        <p className="text-sm font-semibold text-gray-900 truncate" title={image.name}>
                                                                            {image.name}
                                                                        </p>
                                                                        <p className="text-xs text-gray-500">Index: {image.index}</p>
                                                                    </div>

                                                                    <div className="flex gap-2 flex-wrap">
                                                                        <Badge className={complexityColor(image.layout_complexity)}>
                                                                            {image.layout_complexity}
                                                                        </Badge>
                                                                        <Badge className={qualityColor(image.quality)}>
                                                                            {image.quality}
                                                                        </Badge>
                                                                    </div>

                                                                    <div className="text-xs text-gray-600">
                                                                        <strong>{getElementCount(image.elements_detected)}</strong> elements detected
                                                                    </div>

                                                                    {/* Expandable Element Details */}
                                                                    <details className="text-xs">
                                                                        <summary className="cursor-pointer font-semibold text-blue-600 hover:underline">
                                                                            View all elements →
                                                                        </summary>
                                                                        <div className="mt-3 p-3 bg-gray-50 rounded border border-gray-200">
                                                                            {renderElements(image.elements_detected)}
                                                                        </div>
                                                                    </details>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </TabsContent>
                                    ))}
                                </Tabs>
                            </CardContent>
                        </Card>

                        {/* All Images Grid */}
                        <Card>
                            <CardHeader>
                                <CardTitle>All Images Overview</CardTitle>
                                <CardDescription>Complete list with clustering assignment</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {images.map(image => (
                                        <div key={image.index} className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                            <div className="flex gap-4">
                                                {/* Thumbnail */}
                                                <div className="flex-shrink-0">
                                                    <img
                                                        src={image.url}
                                                        alt={image.name}
                                                        className="w-24 h-24 object-cover rounded border border-gray-200"
                                                    />
                                                </div>

                                                {/* Info */}
                                                <div className="flex-grow space-y-2">
                                                    <div>
                                                        <p className="font-semibold text-gray-900">{image.name}</p>
                                                        <p className="text-sm text-gray-500">Index {image.index} • Cluster {image.cluster_id}</p>
                                                    </div>

                                                    <div className="flex flex-wrap gap-2">
                                                        <Badge className={complexityColor(image.layout_complexity)}>
                                                            {image.layout_complexity} complexity
                                                        </Badge>
                                                        <Badge className={qualityColor(image.quality)}>
                                                            {image.quality}
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            {getElementCount(image.elements_detected)} elements
                                                        </Badge>
                                                    </div>

                                                    {/* Quick Element Preview */}
                                                    <div className="text-xs text-gray-600">
                                                        <p className="font-semibold mb-1">Detected elements:</p>
                                                        <div className="flex flex-wrap gap-1">
                                                            {Object.entries(image.elements_detected)
                                                                .flatMap(([_, category]) => {
                                                                    if (typeof category !== 'object') return [];
                                                                    return Object.entries(category)
                                                                        .filter(([_, value]) => value === true || (typeof value === 'number' && value > 0))
                                                                        .map(([name]) => name);
                                                                })
                                                                .slice(0, 8)
                                                                .map(element => (
                                                                    <span key={element} className="bg-gray-100 px-2 py-0.5 rounded">
                                                                        {element.replace(/_/g, ' ')}
                                                                    </span>
                                                                ))}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Raw JSON (expandable) */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Raw JSON Data</CardTitle>
                                <CardDescription>Full analysis response for debugging</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <details className="w-full">
                                    <summary className="cursor-pointer font-semibold text-blue-600 hover:underline">
                                        Expand to view raw JSON
                                    </summary>
                                    <pre className="mt-4 bg-gray-900 text-gray-100 p-4 rounded-lg overflow-auto max-h-96 text-xs">
                                        {JSON.stringify(result, null, 2)}
                                    </pre>
                                </details>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Empty State */}
                {!result && (
                    <Card className="border-dashed">
                        <CardContent className="pt-12 pb-12 text-center">
                            <AlertCircle className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">No analysis yet</h3>
                            <p className="text-gray-600 mb-6">Upload images above to see clustering and element detection results</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}
