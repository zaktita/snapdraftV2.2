import { useState, useRef, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { AlertCircle, Loader2, Upload, Trash2 } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

type PageProps = {
    models: string[];
};

type GenerationResult = {
    image_url: string | null;
    duration_ms: number;
    error: string | null;
};

type GenerationResults = {
    [modelName: string]: GenerationResult;
};

export default function TestAiModelsPage() {
    const { props } = usePage<PageProps>();
    const { models } = props;

    const [referenceImages, setReferenceImages] = useState<string[]>([]);
    const [prompt, setPrompt] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [results, setResults] = useState<GenerationResults | null>(null);
    const [savedResults, setSavedResults] = useState<Array<{
        id: string;
        timestamp: string;
        prompt: string;
        results: GenerationResults;
    }>>([]);
    const [showSavedResults, setShowSavedResults] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Load saved results from localStorage on mount
    useEffect(() => {
        const saved = localStorage.getItem('testAiModelResults');
        if (saved) {
            try {
                setSavedResults(JSON.parse(saved));
            } catch (err) {
                console.error('Failed to load saved results:', err);
            }
        }
    }, []);

    // Save results to localStorage when results change
    useEffect(() => {
        if (results) {
            const newSavedResult = {
                id: Date.now().toString(),
                timestamp: new Date().toLocaleString(),
                prompt: prompt,
                results: results,
                metadata: {
                    referenceImagesCount: referenceImages.length,
                    modelsCount: Object.keys(results).length,
                    savedAt: new Date().toISOString(),
                },
            };
            
            // Log what we're saving
            console.log('Saving to localStorage:', {
                id: newSavedResult.id,
                timestamp: newSavedResult.timestamp,
                prompt: newSavedResult.prompt,
                resultsCount: Object.keys(newSavedResult.results).length,
                metadata: newSavedResult.metadata,
            });
            
            const updated = [newSavedResult, ...savedResults].slice(0, 10); // Keep last 10
            setSavedResults(updated);
            localStorage.setItem('testAiModelResults', JSON.stringify(updated));
            
            console.log('Total saved results:', updated.length);
        }
    }, [results]);

    const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        setError(null);

        files.forEach((file) => {
            if (!file.type.startsWith('image/')) {
                setError('Only image files are supported');
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                const dataUrl = event.target?.result as string;
                setReferenceImages((prev) => [...prev, dataUrl]);
            };
            reader.onerror = () => {
                setError('Failed to read image file');
            };
            reader.readAsDataURL(file);
        });

        // Reset input
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const removeImage = (index: number) => {
        setReferenceImages((prev) => prev.filter((_, i) => i !== index));
    };

    const deleteSavedResult = (id: string) => {
        const updated = savedResults.filter((r) => r.id !== id);
        setSavedResults(updated);
        localStorage.setItem('testAiModelResults', JSON.stringify(updated));
    };

    const loadSavedResult = (savedResult: typeof savedResults[0]) => {
        setResults(savedResult.results);
        setPrompt(savedResult.prompt);
        setShowSavedResults(false);
    };

    const clearAllSavedResults = () => {
        if (window.confirm('Are you sure you want to delete all saved results?')) {
            setSavedResults([]);
            localStorage.removeItem('testAiModelResults');
        }
    };

    const handleGenerate = async () => {
        setError(null);
        setResults(null);

        if (referenceImages.length === 0) {
            setError('Please upload at least one reference image');
            return;
        }

        if (!prompt.trim()) {
            setError('Please enter a prompt');
            return;
        }

        setLoading(true);

        try {
            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                setError('CSRF token not found. Please refresh the page and try again.');
                setLoading(false);
                return;
            }

            const response = await fetch('/test-ai-models/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    reference_images: referenceImages,
                    prompt: prompt,
                }),
            });

            const data = await response.json();
            
            // Log the full response for debugging
            console.log('=== AI Models Response ===');
            console.log('Full response:', data);
            console.log('Results:', data.results);
            console.log('Individual model results:');
            Object.entries(data.results || {}).forEach(([model, result]: [string, any]) => {
                console.log(`  ${model}:`, {
                    has_image: !!result.image_url,
                    image_url_preview: result.image_url?.substring(0, 100) + '...',
                    duration_ms: result.duration_ms,
                    error: result.error,
                });
            });
            console.log('=========================');

            if (!data.success) {
                setError(data.error || 'Failed to generate images');
                return;
            }

            setResults(data.results);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An unexpected error occurred');
        } finally {
            setLoading(false);
        }
    };

    const breadcrumbs = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Test AI Models', href: '/test-ai-models' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Test AI Models" />

            <div className="max-w-7xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Test AI Models</h1>
                    <p className="text-slate-600 mt-2">
                        Upload reference images and a prompt to compare image generation across multiple AI models side-by-side.
                    </p>
                </div>

                {/* Input Section */}
                <Card className="p-6">
                    <div className="space-y-6">
                        {/* Reference Images Upload */}
                        <div>
                            <label className="block text-sm font-medium text-slate-900 mb-3">
                                Reference Images
                            </label>

                            {/* Upload Area */}
                            <div
                                className="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center cursor-pointer hover:border-slate-400 transition"
                                onClick={() => fileInputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    e.currentTarget.classList.add('border-slate-400');
                                }}
                                onDragLeave={(e) => {
                                    e.currentTarget.classList.remove('border-slate-400');
                                }}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    e.currentTarget.classList.remove('border-slate-400');
                                    handleImageUpload({
                                        target: { files: e.dataTransfer.files },
                                    } as React.ChangeEvent<HTMLInputElement>);
                                }}
                            >
                                <Upload className="mx-auto h-8 w-8 text-slate-400 mb-2" />
                                <p className="text-sm text-slate-600">
                                    Drag and drop images here, or click to select
                                </p>
                                <p className="text-xs text-slate-500 mt-1">
                                    Supports JPG, PNG, WebP
                                </p>
                            </div>

                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept="image/*"
                                onChange={handleImageUpload}
                                className="hidden"
                            />

                            {/* Image Previews */}
                            {referenceImages.length > 0 && (
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mt-4">
                                    {referenceImages.map((image, idx) => (
                                        <div key={idx} className="relative group">
                                            <img
                                                src={image}
                                                alt={`Reference ${idx + 1}`}
                                                className="w-full h-24 object-cover rounded-lg"
                                            />
                                            <button
                                                onClick={() => removeImage(idx)}
                                                className="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-white text-sm font-medium"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Prompt Input */}
                        <div>
                            <label className="block text-sm font-medium text-slate-900 mb-2">
                                Prompt
                            </label>
                            <Textarea
                                value={prompt}
                                onChange={(e) => setPrompt(e.target.value)}
                                placeholder="Describe what image you want to generate..."
                                className="min-h-24 resize-none"
                            />
                        </div>

                        {/* Error Alert */}
                        {error && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {/* Generate Button */}
                        <Button
                            onClick={handleGenerate}
                            disabled={loading || referenceImages.length === 0 || !prompt.trim()}
                            className="w-full"
                            size="lg"
                        >
                            {loading ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                'Generate Images'
                            )}
                        </Button>
                    </div>
                </Card>

                {/* Saved Results Section */}
                {savedResults.length > 0 && (
                    <Card className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    Saved Results ({savedResults.length})
                                </h2>
                                <p className="text-sm text-slate-500">
                                    Your last 10 generations are automatically saved (check browser console for image data)
                                </p>
                            </div>
                            <div className="space-x-2">
                                <Button
                                    onClick={() => {
                                        console.log('=== FULL LOCALSTORAGE DATA ===');
                                        const stored = localStorage.getItem('testAiModelResults');
                                        if (stored) {
                                            const parsed = JSON.parse(stored);
                                            console.log('Saved results:', parsed);
                                            parsed.forEach((result: any, idx: number) => {
                                                console.log(`\n--- Result ${idx + 1} ---`);
                                                console.log('Timestamp:', result.timestamp);
                                                console.log('Prompt:', result.prompt);
                                                console.log('Models:', Object.keys(result.results));
                                                Object.entries(result.results).forEach(([model, data]: [string, any]) => {
                                                    console.log(`  ${model}:`);
                                                    console.log('    Image URL length:', data.image_url?.length || 0);
                                                    console.log('    Image URL preview:', data.image_url?.substring(0, 100));
                                                    console.log('    Duration:', data.duration_ms, 'ms');
                                                    console.log('    Error:', data.error);
                                                });
                                            });
                                        } else {
                                            console.log('No saved results found');
                                        }
                                        console.log('=========================');
                                    }}
                                    variant="outline"
                                    size="sm"
                                >
                                    Log All Data
                                </Button>
                                <Button
                                    onClick={() => setShowSavedResults(!showSavedResults)}
                                    variant="outline"
                                    size="sm"
                                >
                                    {showSavedResults ? 'Hide' : 'Show'}
                                </Button>
                                <Button
                                    onClick={clearAllSavedResults}
                                    variant="destructive"
                                    size="sm"
                                >
                                    Clear All
                                </Button>
                            </div>
                        </div>

                        {showSavedResults && (
                            <div className="space-y-2 max-h-96 overflow-y-auto">
                                {savedResults.map((saved) => (
                                    <div
                                        key={saved.id}
                                        className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-slate-900 truncate">
                                                {saved.prompt}
                                            </p>
                                            <p className="text-xs text-slate-500">{saved.timestamp}</p>
                                        </div>
                                        <div className="flex items-center gap-2 ml-4">
                                            <Button
                                                onClick={() => loadSavedResult(saved)}
                                                variant="outline"
                                                size="sm"
                                            >
                                                Load
                                            </Button>
                                            <button
                                                onClick={() => deleteSavedResult(saved.id)}
                                                className="p-2 text-slate-400 hover:text-red-600 transition"
                                                title="Delete"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                )}

                {/* Results Section */}
                {results && (
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight mb-4">Results</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {models.map((model) => {
                                const result = results[model];
                                const modelLabel = model.split('/')[1] || model;

                                return (
                                    <Card key={model} className="overflow-hidden flex flex-col">
                                        {/* Image Container */}
                                        <div className="bg-slate-100 aspect-square overflow-hidden">
                                            {result?.image_url ? (
                                                <img
                                                    src={result.image_url}
                                                    alt={model}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect fill=%22%23e2e8f0%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22%236b7280%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EFailed to load%3C/text%3E%3C/svg%3E';
                                                    }}
                                                />
                                            ) : result?.error ? (
                                                <div className="w-full h-full flex items-center justify-center p-4">
                                                    <div className="text-center">
                                                        <AlertCircle className="h-8 w-8 text-red-500 mx-auto mb-2" />
                                                        <p className="text-xs text-red-600 break-words">
                                                            {result.error}
                                                        </p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center">
                                                    <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                                                </div>
                                            )}
                                        </div>

                                        {/* Info Section */}
                                        <div className="p-3 flex flex-col flex-grow">
                                            <h3 className="font-semibold text-sm text-slate-900 truncate">
                                                {modelLabel}
                                            </h3>
                                            <p className="text-xs text-slate-500 mt-1">
                                                {result?.duration_ms
                                                    ? `${(result.duration_ms / 1000).toFixed(1)}s`
                                                    : '—'}
                                            </p>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
