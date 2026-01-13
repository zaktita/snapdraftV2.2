import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload, Zap, X } from 'lucide-react';
import { useState, useRef, DragEvent, ChangeEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import * as quickGenerate from '@/routes/quick-generate';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Quick Generate', href: quickGenerate.index().url },
];

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

export default function QuickGenerateIndex() {
    const [referenceImages, setReferenceImages] = useState<File[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors } = useForm({
        reference_images: [] as File[],
        caption: '',
        format: '1:1',
    });

    const handleDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setIsDragging(false);

        const files = Array.from(e.dataTransfer.files).filter((file) =>
            file.type.startsWith('image/')
        );

        if (files.length > 0) {
            addFiles(files);
        }
    };

    const handleFileSelect = (e: ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            addFiles(files);
        }
    };

    const addFiles = (newFiles: File[]) => {
        console.log('📸 Adding files:', newFiles.length);
        const combined = [...referenceImages, ...newFiles].slice(0, 10);
        console.log('📊 Total images after add:', combined.length);
        setReferenceImages(combined);
        setData('reference_images', combined);
    };

    const removeImage = (index: number) => {
        const updated = referenceImages.filter((_, i) => i !== index);
        setReferenceImages(updated);
        setData('reference_images', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('🚀 Quick Generate Form Submit');
        console.log('📋 Form Data:', {
            reference_images_count: data.reference_images.length,
            caption: data.caption,
            format: data.format,
        });
        console.log('📁 Reference Images:', data.reference_images);
        console.log('🔗 Post URL:', quickGenerate.store().url);
        
        post(quickGenerate.store().url, {
            forceFormData: true, // Force multipart/form-data for file uploads
            onSuccess: (page) => {
                console.log('✅ Form submission successful', page);
            },
            onError: (errors) => {
                console.error('❌ Form submission errors:', errors);
            },
            onFinish: () => {
                console.log('🏁 Form submission finished');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quick Generate" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Quick Generate</h1>
                        <p className="text-muted-foreground mt-1">
                            Upload references + caption → AI generates brand-consistent visual
                        </p>
                    </div>
                    <Zap className="h-10 w-10 text-primary" />
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Step 1: Upload References */}
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <h2 className="mb-4 text-lg font-semibold">Step 1: Upload Reference Images</h2>
                        <p className="text-muted-foreground mb-4 text-sm">
                            Upload 5-10 images that represent your brand style, colors, and typography.
                        </p>

                        <div
                            className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
                                isDragging
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-primary/50'
                            }`}
                            onDrop={handleDrop}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setIsDragging(true);
                            }}
                            onDragLeave={() => setIsDragging(false)}
                        >
                            <Upload className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                            <p className="mb-2 font-medium">
                                Drop images here or{' '}
                                <button
                                    type="button"
                                    className="text-primary hover:underline"
                                    onClick={() => fileInputRef.current?.click()}
                                >
                                    browse
                                </button>
                            </p>
                            <p className="text-muted-foreground text-sm">
                                5-10 images required • JPG, PNG, WebP • Max 10MB each
                            </p>
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept="image/*"
                                className="hidden"
                                onChange={handleFileSelect}
                            />
                        </div>

                        {errors.reference_images && (
                            <p className="text-destructive mt-2 text-sm">{errors.reference_images}</p>
                        )}

                        {/* Image Previews */}
                        {referenceImages.length > 0 && (
                            <div className="mt-4 grid grid-cols-5 gap-3">
                                {referenceImages.map((file, index) => (
                                    <div key={index} className="group relative aspect-square">
                                        <img
                                            src={URL.createObjectURL(file)}
                                            alt={`Reference ${index + 1}`}
                                            className="h-full w-full rounded-md object-cover"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeImage(index)}
                                            className="absolute -right-2 -top-2 rounded-full bg-destructive p-1 text-destructive-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <p className="text-muted-foreground mt-2 text-sm">
                            {referenceImages.length} / 10 images uploaded
                            {referenceImages.length < 5 && ' (minimum 5 required)'}
                        </p>
                    </div>

                    {/* Step 2: Caption */}
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <h2 className="mb-4 text-lg font-semibold">Step 2: Enter Caption</h2>
                        <p className="text-muted-foreground mb-4 text-sm">
                            Describe what visual you want to create. AI will extract title and description.
                        </p>

                        <textarea
                            value={data.caption}
                            onChange={(e) => setData('caption', e.target.value)}
                            placeholder="Example: Summer Sale 2026 - Up to 50% off all items - Shop Now"
                            className="w-full rounded-md border bg-background px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            rows={3}
                            maxLength={500}
                        />
                        {errors.caption && <p className="text-destructive mt-2 text-sm">{errors.caption}</p>}
                        <p className="text-muted-foreground mt-2 text-sm">{data.caption.length} / 500 characters</p>
                    </div>

                    {/* Step 3: Format */}
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <h2 className="mb-4 text-lg font-semibold">Step 3: Select Format</h2>
                        <p className="text-muted-foreground mb-4 text-sm">Choose aspect ratio for your visual.</p>

                        <select
                            value={data.format}
                            onChange={(e) => setData('format', e.target.value)}
                            className="w-full rounded-md border bg-background px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            {formatOptions.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                        {errors.format && <p className="text-destructive mt-2 text-sm">{errors.format}</p>}
                    </div>

                    {/* Submit */}
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                            disabled={processing}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || referenceImages.length < 5 || !data.caption.trim()}
                            onClick={() => {
                                console.log('🔘 Generate Visual button clicked');
                                console.log('⚙️ Button state:', {
                                    processing,
                                    referenceCount: referenceImages.length,
                                    captionLength: data.caption.trim().length,
                                    disabled: processing || referenceImages.length < 5 || !data.caption.trim(),
                                });
                            }}
                        >
                            <Zap className="mr-2 h-4 w-4" />
                            {processing ? 'Processing...' : 'Generate Visual'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
