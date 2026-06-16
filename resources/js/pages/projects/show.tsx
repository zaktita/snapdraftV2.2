import { Button } from '@/components/ui/button';
import { BatchProgress } from '@/components/batch-progress';
import { UpscaleModal } from '@/components/canvas-modals';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { GenerationDebugDialog, type GenerationDebugData } from '@/components/generation-debug-dialog';
import { ArrowLeft, Star, Download, MoreHorizontal, BoxSelect, Square, SquareCheck, Edit, Maximize, RotateCw, Share, Trash2, Check, Plus, CheckCircle, X, ChevronLeft, ChevronRight, Upload, Edit3, FileText, Grid, Clock, AlertCircle, Image as ImageIcon, Zap, Sparkles, Bug } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';

interface CSVRow {
    [key: string]: string;
}

interface ColumnMapping {
    [key: string]: string;
}

type UploadMode = 'upload' | 'create';

interface Image {
    id: number;
    url: string;
    thumbnail_url: string;
    prompt?: string;
    is_favorite?: boolean;
    metadata?: Record<string, any> | null;
    can_debug_generation?: boolean;
}

interface Project {
    id: number;
    title: string;
    created_at: string;
    updated_at: string;
    images_count: number;
    is_favorite: boolean;
    description?: string;
    featured_image?: string;
    wizard_type?: string | null;
    brand_reference_count?: number;
    images: Image[];
}

interface ProjectShowProps {
    project: Project;
    justCreated?: boolean; // Flag to indicate project was just created
    expectedImages?: number; // Expected number of images to generate
    batchCompleted?: boolean; // Flag to show completion toast after processing redirect
    hasPendingGenerations?: boolean; // Whether there are pending AI generations
    progress?: {
        total: number;
        completed: number;
        failed: number;
        pending: number;
        failure_reasons?: Array<{ title?: string | null; message: string }>;
    } | null;
    csvRowTitles?: string[] | null; // Titles from CSV data for titled skeleton slots
    generationDebugEnabled?: boolean;
}

export default function ProjectShow({ project, justCreated = false, expectedImages = 0, batchCompleted = false, hasPendingGenerations = false, progress = null, csvRowTitles = null, generationDebugEnabled = false }: ProjectShowProps) {
    const page = usePage<{ success?: string; generating?: boolean }>();
    const [selectedImages, setSelectedImages] = useState<number[]>([]);
    const [favoriteImages, setFavoriteImages] = useState<number[]>([]);
    const [regeneratingByImageId, setRegeneratingByImageId] = useState<Record<number, boolean>>({});
    const [csvModalOpen, setCsvModalOpen] = useState(false);
    const [csvCurrentStep, setCsvCurrentStep] = useState<2 | 5>(2);
    const [csvData, setCsvData] = useState<CSVRow[]>([]);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const [columnMappings, setColumnMappings] = useState<ColumnMapping>({});
    const [uploadMode, setUploadMode] = useState<UploadMode>('upload');
    const [uploadComplete, setUploadComplete] = useState(false);
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [fileName, setFileName] = useState('');
    const [editableData, setEditableData] = useState<CSVRow[]>([]);
    const [editableHeaders, setEditableHeaders] = useState<string[]>(['title', 'description', 'format']);
    const [csvSubmitting, setCsvSubmitting] = useState(false);
    const [csvDragOver, setCsvDragOver] = useState(false);
    const [isEditingTitle, setIsEditingTitle] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const [showSuccess, setShowSuccess] = useState(false);
    // Initialize banner state based on flash message OR pending generations prop
    const [showGeneratingBanner, setShowGeneratingBanner] = useState(!!page.props.generating || hasPendingGenerations);
    const [openImageMenuIndex, setOpenImageMenuIndex] = useState<number | null>(null);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [lightboxImageIndex, setLightboxImageIndex] = useState(0);
    const [upscaleModalOpen, setUpscaleModalOpen] = useState(false);
    const [upscaleFactor, setUpscaleFactor] = useState(2);
    const [upscaleImageId, setUpscaleImageId] = useState<number | null>(null);
    const [upscalingByImageId, setUpscalingByImageId] = useState<Record<number, boolean>>({});
    const [debugImage, setDebugImage] = useState<Image | null>(null);
    const [debugData, setDebugData] = useState<GenerationDebugData | null>(null);
    const [debugLoading, setDebugLoading] = useState(false);
    const [debugError, setDebugError] = useState<string | null>(null);
    const titleInputRef = useRef<HTMLInputElement>(null);
    const csvInputRef = useRef<HTMLInputElement>(null);
    const isGenerationPending = showGeneratingBanner;
    const skeletonCount = Math.max(1, (progress?.total ?? expectedImages) || 1);

    useEffect(() => {
        console.log('[snapdraft:generation-debug] ProjectShow mounted', {
            build: '2026-06-16-prompt-cluster-v2',
            projectId: project.id,
            wizardType: project.wizard_type,
            generationDebugEnabled,
            imageCount: project.images.length,
            images: project.images.map((img) => ({
                id: img.id,
                can_debug_generation: img.can_debug_generation,
                csv_row_index: img.metadata?.csv_row_index,
            })),
        });
    }, [project.id, project.wizard_type, project.images, generationDebugEnabled]);

    const openImageGenerationDebug = (image: Image) => {
        const url = `/projects/${project.id}/images/${image.id}/generation-debug`;
        console.log('[snapdraft:generation-debug] Opening debug dialog', {
            projectId: project.id,
            imageId: image.id,
            url,
            metadata: image.metadata,
            can_debug_generation: image.can_debug_generation,
        });

        setDebugImage(image);
        setDebugData(null);
        setDebugError(null);
        setDebugLoading(true);

        fetch(url)
            .then((res) => {
                console.log('[snapdraft:generation-debug] Debug API response', {
                    status: res.status,
                    ok: res.ok,
                    url: res.url,
                });
                if (!res.ok) throw new Error(`Failed to load debug data (${res.status})`);
                return res.json();
            })
            .then((data: GenerationDebugData) => {
                console.log('[snapdraft:generation-debug] Debug payload', data);
                setDebugData(data);
            })
            .catch((err) => {
                console.error('[snapdraft:generation-debug] Debug fetch failed', err);
                setDebugError('Could not load generation debug data for this image.');
            })
            .finally(() => setDebugLoading(false));
    };

    const closeImageGenerationDebug = () => {
        setDebugImage(null);
        setDebugData(null);
        setDebugError(null);
    };

    const parseCSV = (text: string): CSVRow[] => {
        const lines = text.trim().split('\n');
        if (lines.length === 0) return [];

        const headers = lines[0]
            .split(',')
            .map((h, idx) => {
                const trimmed = h.trim();
                if (idx === 0) return trimmed.replace(/^\uFEFF/, '');
                return trimmed;
            })
            .filter(Boolean);

        const data: CSVRow[] = [];
        for (let i = 1; i < Math.min(lines.length, 6); i++) {
            const values = lines[i].split(',').map((v) => v.trim());
            const row: CSVRow = {};
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            data.push(row);
        }

        const mappings: ColumnMapping = {};
        headers.forEach((header) => {
            const lower = header.toLowerCase();
            if (lower.includes('title') || lower.includes('name')) {
                mappings[header] = 'Product Title';
            } else if (lower.includes('description') || lower.includes('prompt')) {
                mappings[header] = 'Image Prompt';
            } else if (lower.includes('id')) {
                mappings[header] = 'Product ID';
            } else {
                mappings[header] = 'Ignore this column';
            }
        });
        setColumnMappings(mappings);

        return data;
    };

    const handleFileUpload = (file: File) => {
        setFileName(file.name);
        setCsvFile(file);

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = (e.target?.result as string) ?? '';
            const data = parseCSV(text);
            setCsvData(data);
            setSelectedRows(new Set(data.map((_, index) => index)));
            setUploadComplete(true);
        };
        reader.readAsText(file);
    };

    const toggleRow = (rowIndex: number) => {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (next.has(rowIndex)) next.delete(rowIndex);
            else next.add(rowIndex);
            return next;
        });
    };

    const toggleAllRows = (checked: boolean) => {
        if (checked) {
            setSelectedRows(new Set(csvData.map((_, index) => index)));
        } else {
            setSelectedRows(new Set());
        }
    };

    const updateMapping = (header: string, value: string) => {
        setColumnMappings((prev) => ({ ...prev, [header]: value }));
    };

    const updateCellValue = (rowIndex: number, header: string, value: string) => {
        const newData = [...csvData];
        newData[rowIndex][header] = value;
        setCsvData(newData);

        if (newData.length === 0) return;

        const headers = Object.keys(newData[0]);
        const csvContent = [
            headers.join(','),
            ...newData.map((row) => headers.map((h) => `"${row[h] || ''}"`).join(',')),
        ].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const file = new File([blob], fileName || 'edited.csv', { type: 'text/csv' });
        setCsvFile(file);
    };

    const addRow = () => {
        const newRow: CSVRow = {};
        editableHeaders.forEach((header) => {
            newRow[header] = '';
        });
        setEditableData((prev) => [...prev, newRow].slice(0, 5));
    };

    const removeRow = (index: number) => {
        setEditableData((prev) => prev.filter((_, i) => i !== index));
    };

    const updateCell = (rowIndex: number, header: string, value: string) => {
        const newData = [...editableData];
        newData[rowIndex][header] = value;
        setEditableData(newData);
    };

    const addColumn = () => {
        if (editableHeaders.length >= 6) return;
        const newHeader = `column_${editableHeaders.length + 1}`;
        setEditableHeaders((prev) => [...prev, newHeader]);
        setEditableData((prev) => prev.map((row) => ({ ...row, [newHeader]: '' })));
    };

    const removeColumn = (header: string) => {
        if (editableHeaders.length <= 1) return;
        setEditableHeaders((prev) => prev.filter((h) => h !== header));
        setEditableData((prev) =>
            prev.map((row) => {
                const next = { ...row };
                delete next[header];
                return next;
            }),
        );
    };

    const updateHeaderName = (oldHeader: string, newHeader: string) => {
        if (!newHeader.trim() || editableHeaders.includes(newHeader)) return;

        setEditableHeaders((prev) => prev.map((h) => (h === oldHeader ? newHeader : h)));
        setEditableData((prev) =>
            prev.map((row) => {
                const next = { ...row };
                next[newHeader] = next[oldHeader];
                delete next[oldHeader];
                return next;
            }),
        );
    };

    const confirmCSVEditor = () => {
        if (editableData.length === 0) return;

        const preview = editableData.slice(0, 5);
        setCsvData(preview);
        setFileName('Custom CSV');

        const mappings: ColumnMapping = {};
        editableHeaders.forEach((header) => {
            const lower = header.toLowerCase();
            if (lower.includes('title') || lower.includes('name')) {
                mappings[header] = 'Product Title';
            } else if (lower.includes('description') || lower.includes('prompt')) {
                mappings[header] = 'Image Prompt';
            } else if (lower.includes('format')) {
                mappings[header] = 'Product ID';
            } else {
                mappings[header] = 'Ignore this column';
            }
        });
        setColumnMappings(mappings);
        setSelectedRows(new Set(preview.map((_, index) => index)));

        const csvContent = [
            editableHeaders.join(','),
            ...preview.map((row) => editableHeaders.map((h) => `"${row[h] || ''}"`).join(',')),
        ].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const file = new File([blob], 'custom.csv', { type: 'text/csv' });
        setCsvFile(file);
        setUploadComplete(true);
    };

    // Show success message on mount if present
    useEffect(() => {
        if (page.props.success || batchCompleted) {
            setShowSuccess(true);

            // Clean one-time completion flag from URL without reloading.
            if (batchCompleted && typeof window !== 'undefined') {
                const url = new URL(window.location.href);
                if (url.searchParams.has('batchCompleted')) {
                    url.searchParams.delete('batchCompleted');
                    const next = `${url.pathname}${url.searchParams.toString() ? `?${url.searchParams.toString()}` : ''}${url.hash}`;
                    window.history.replaceState({}, '', next);
                }
            }

            // Auto-hide after 5 seconds
            const timer = setTimeout(() => setShowSuccess(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [page.props.success, batchCompleted]);

    // Adaptive polling: fast right after start, then slower to reduce load.
    useEffect(() => {
        if (!showGeneratingBanner) return;

        const startedAt = Date.now();
        let timeoutId: ReturnType<typeof setTimeout> | null = null;
        let stopped = false;
        let drainPolls = 0;
        const MAX_DRAIN_POLLS = 3;
        let consecutiveReloadErrors = 0;
        let warnedAboutPollErrors = false;

        const scheduleNext = (delayOverride?: number) => {
            if (stopped) return;

            const elapsedMs = Date.now() - startedAt;
            const nextDelay = delayOverride !== undefined ? delayOverride : (elapsedMs < 20_000 ? 1200 : 3000);

            timeoutId = setTimeout(() => {
                router.reload({
                    only: ['project', 'hasPendingGenerations', 'progress'],
                    onSuccess: (page) => {
                        consecutiveReloadErrors = 0;
                        warnedAboutPollErrors = false;
                        const stillPending = (page.props as any).hasPendingGenerations;

                        if (!stillPending) {
                            if (drainPolls < MAX_DRAIN_POLLS) {
                                // Do a few more quick polls to catch last-second image saves
                                drainPolls++;
                                scheduleNext(1000);
                                return;
                            }
                            setShowGeneratingBanner(false);
                            stopped = true;
                            return;
                        }

                        // Reset drain counter in case a new batch starts
                        drainPolls = 0;
                        scheduleNext();
                    },
                    onError: () => {
                        consecutiveReloadErrors += 1;
                        if (consecutiveReloadErrors >= 6 && !warnedAboutPollErrors) {
                            warnedAboutPollErrors = true;
                            toast.error(
                                'Could not refresh generation status (network or timeout). If this persists, reload the page or confirm queue workers are running on the server.',
                                { duration: 12_000 },
                            );
                        }
                        scheduleNext();
                    },
                });
            }, nextDelay);
        };

        scheduleNext(0);

        return () => {
            stopped = true;
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    }, [showGeneratingBanner]);

    useEffect(() => {
        if (isEditingTitle && titleInputRef.current) {
            titleInputRef.current.focus();
            titleInputRef.current.select();
        }
    }, [isEditingTitle]);

    const regenerateImage = (imageId: number, options?: { closeLightbox?: boolean }) => {
        if (regeneratingByImageId[imageId]) return;

        setRegeneratingByImageId(prev => ({ ...prev, [imageId]: true }));
        setShowGeneratingBanner(true);

        router.post(`/projects/${project.id}/images/${imageId}/regenerate`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setRegeneratingByImageId(prev => {
                    const next = { ...prev };
                    delete next[imageId];
                    return next;
                });
            },
        });

        if (options?.closeLightbox) {
            closeLightbox();
        }
    };

    const fileToDataUrl = (file: Blob): Promise<string> => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result ?? ''));
            reader.onerror = () => reject(new Error('Failed to read image file.'));
            reader.readAsDataURL(file);
        });
    };

    const openUpscaleSelector = (imageId: number) => {
        if (upscalingByImageId[imageId]) return;
        setUpscaleImageId(imageId);
        setUpscaleFactor(2);
        setUpscaleModalOpen(true);
    };

    const runUpscale = async () => {
        if (!upscaleImageId) return;
        const image = project.images.find((img) => img.id === upscaleImageId);
        if (!image) return;

        const creditCost = upscaleFactor;
        setUpscalingByImageId((prev) => ({ ...prev, [upscaleImageId]: true }));
        setUpscaleModalOpen(false);

        try {
            const sourceResponse = await fetch(`/storage/${image.url}`);
            const sourceBlob = await sourceResponse.blob();
            const imageDataUrl = await fileToDataUrl(sourceBlob);

            const response = await fetch('/api/upscale-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    image: imageDataUrl,
                    scale: upscaleFactor,
                }),
            });

            if (!response.ok) {
                const text = await response.text();
                let errorMessage = `Upscale failed (${response.status})`;
                try {
                    const json = JSON.parse(text);
                    errorMessage = json?.error || json?.message || errorMessage;
                } catch {
                    if (text) errorMessage = text;
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();
            const upscaledDataUrl: string | undefined = result?.upscaledImage;
            if (!upscaledDataUrl) {
                throw new Error('Upscale did not return image data.');
            }

            const link = document.createElement('a');
            link.href = upscaledDataUrl;
            link.download = `${project.title}_image_${image.id}_${upscaleFactor}x.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            toast.success(`Upscaled ${upscaleFactor}x. ${creditCost} credit(s) used.`);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Upscale failed.');
        } finally {
            setUpscalingByImageId((prev) => {
                const next = { ...prev };
                delete next[upscaleImageId];
                return next;
            });
        }
    };

    const handleTitleDoubleClick = () => {
        setIsEditingTitle(true);
    };

    const handleTitleBlur = () => {
        setIsEditingTitle(false);
        if (editTitle.trim() && editTitle !== project.title) {
            router.patch(`/projects/${project.id}`, { title: editTitle }, {
                preserveScroll: true,
            });
        } else {
            setEditTitle(project.title);
        }
    };

    const handleTitleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleTitleBlur();
        } else if (e.key === 'Escape') {
            setIsEditingTitle(false);
            setEditTitle(project.title);
        }
    };

    const handleGenerateMore = () => {
        setCsvCurrentStep(2);
        setCsvData([]);
        setSelectedRows(new Set());
        setColumnMappings({});
        setUploadMode('upload');
        setUploadComplete(false);
        setCsvFile(null);
        setFileName('');
        setEditableHeaders(['title', 'description', 'format']);
        setEditableData([]);
        setCsvSubmitting(false);
        setCsvDragOver(false);
        setCsvModalOpen(true);
    };

    const handleCsvDragOver = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        if (!csvSubmitting) setCsvDragOver(true);
    };

    const handleCsvDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setCsvDragOver(false);
    };

    const handleCsvDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setCsvDragOver(false);

        if (csvSubmitting) return;
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    };

    const submitCsvGeneration = () => {
        if (csvSubmitting) return;

        if (!csvFile) return;

        setCsvSubmitting(true);

        router.post(`/projects/${project.id}/generate`, {
            csv_file: csvFile,
            column_mappings: JSON.stringify(columnMappings),
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setShowGeneratingBanner(true);
            },
            onFinish: () => {
                setCsvSubmitting(false);
                setCsvModalOpen(false);
            },
        });
    };

    const csvHeaders = csvData.length > 0 ? Object.keys(csvData[0]) : [];

    const openLightbox = (index: number) => {
        setLightboxImageIndex(index);
        setLightboxOpen(true);
    };

    const closeLightbox = () => {
        setLightboxOpen(false);
    };

    const goToPrevImage = () => {
        setLightboxImageIndex((prev) => (prev === 0 ? project.images.length - 1 : prev - 1));
    };

    const goToNextImage = () => {
        setLightboxImageIndex((prev) => (prev === project.images.length - 1 ? 0 : prev + 1));
    };

    // Close image menu on Escape or outside click
    useEffect(() => {
        if (openImageMenuIndex === null) return;
        const handleClose = (e: MouseEvent | KeyboardEvent) => {
            if ('key' in e && e.key !== 'Escape') return;
            setOpenImageMenuIndex(null);
        };
        document.addEventListener('click', handleClose);
        document.addEventListener('keydown', handleClose);
        return () => {
            document.removeEventListener('click', handleClose);
            document.removeEventListener('keydown', handleClose);
        };
    }, [openImageMenuIndex]);

    // Keyboard navigation for lightbox
    useEffect(() => {
        if (!lightboxOpen) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') goToPrevImage();
            if (e.key === 'ArrowRight') goToNextImage();
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [lightboxOpen, project.images.length]);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Projects',
            href: '/projects',
        },
        {
            title: project.title,
            href: `/projects/${project.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.title} />

            <Dialog open={csvModalOpen} onOpenChange={setCsvModalOpen}>
                <DialogContent className="max-h-[90vh] w-[calc(100vw-2rem)] max-w-[900px] overflow-hidden p-0 sm:max-w-[900px]">
                    <div
                        style={{
                            background: 'var(--color-card)',
                            borderRadius: '12px',
                            boxShadow: '0 4px 12px rgba(0, 0, 0, 0.08)',
                            width: '100%',
                            overflow: 'hidden',
                            display: 'flex',
                            flexDirection: 'column',
                            maxHeight: '90vh',
                        }}
                    >
                        {/* Header (matches CSV Wizard) */}
                        <div
                            style={{
                                padding: '32px 40px',
                                borderBottom: '1px solid var(--color-border)',
                            }}
                        >
                            <h2
                                style={{
                                    fontSize: '24px',
                                    fontWeight: 600,
                                    color: 'var(--color-foreground)',
                                    marginBottom: '4px',
                                }}
                            >
                                {csvCurrentStep === 2 && uploadComplete ? 'Review, Map & Edit Data' : 'Upload Your CSV File'}
                            </h2>
                            <p
                                style={{
                                    fontSize: '14px',
                                    color: 'var(--color-muted-foreground)',
                                    lineHeight: 1.5,
                                    margin: 0,
                                }}
                            >
                                {csvCurrentStep === 2 && uploadComplete
                                    ? 'Confirm your data is correct before proceeding.'
                                    : 'Upload or create a CSV. Style references are already set for this project.'
                                }
                            </p>
                            {csvCurrentStep === 2 && !uploadComplete && (
                                <p style={{
                                    fontSize: '12px',
                                    color: 'var(--color-muted-foreground)',
                                    marginTop: '6px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '4px',
                                }}>
                                    <AlertCircle size={12} style={{ flexShrink: 0 }} />
                                    Max 5 rows per generation here. Need more?{' '}
                                    <a href="/projects/create/csv" style={{ color: 'var(--color-primary)', textDecoration: 'none' }}>Use the CSV Wizard</a>
                                </p>
                            )}
                        </div>

                        {/* Body */}
                        <div style={{ padding: '40px', overflowY: 'auto', flex: 1 }}>
                            {csvCurrentStep === 2 && (
                                <div style={{ animation: 'csvModalFadeIn 0.3s ease' }}>
                                    {!uploadComplete ? (
                                        <>
                                            {/* Tab Switcher */}
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: '8px',
                                                    marginBottom: '24px',
                                                    borderBottom: '1px solid var(--color-border)',
                                                    paddingBottom: '0',
                                                }}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => setUploadMode('upload')}
                                                    disabled={csvSubmitting}
                                                    style={{
                                                        padding: '12px 20px',
                                                        fontSize: '14px',
                                                        fontWeight: 500,
                                                        background: 'transparent',
                                                        border: 'none',
                                                        borderBottom: uploadMode === 'upload' ? '2px solid var(--color-primary)' : '2px solid transparent',
                                                        color: uploadMode === 'upload' ? 'var(--color-foreground)' : 'var(--color-muted-foreground)',
                                                        cursor: csvSubmitting ? 'not-allowed' : 'pointer',
                                                        transition: 'all 0.2s ease',
                                                        opacity: csvSubmitting ? 0.6 : 1,
                                                    }}
                                                >
                                                    <Upload size={16} style={{ display: 'inline', marginRight: '6px', verticalAlign: 'middle' }} />
                                                    Upload CSV
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setUploadMode('create');
                                                        if (editableData.length === 0) {
                                                            const initialRows = Array(3)
                                                                .fill(null)
                                                                .map(() => {
                                                                    const row: CSVRow = {};
                                                                    editableHeaders.forEach((h) => (row[h] = ''));
                                                                    return row;
                                                                });
                                                            setEditableData(initialRows);
                                                        }
                                                    }}
                                                    disabled={csvSubmitting}
                                                    style={{
                                                        padding: '12px 20px',
                                                        fontSize: '14px',
                                                        fontWeight: 500,
                                                        background: 'transparent',
                                                        border: 'none',
                                                        borderBottom: uploadMode === 'create' ? '2px solid var(--color-primary)' : '2px solid transparent',
                                                        color: uploadMode === 'create' ? 'var(--color-foreground)' : 'var(--color-muted-foreground)',
                                                        cursor: csvSubmitting ? 'not-allowed' : 'pointer',
                                                        transition: 'all 0.2s ease',
                                                        opacity: csvSubmitting ? 0.6 : 1,
                                                    }}
                                                >
                                                    <Edit3 size={16} style={{ display: 'inline', marginRight: '6px', verticalAlign: 'middle' }} />
                                                    Create CSV
                                                </button>
                                            </div>

                                            {uploadMode === 'upload' ? (
                                                <>
                                                    <div
                                                        onClick={() => (!csvSubmitting ? csvInputRef.current?.click() : undefined)}
                                                        onDragOver={handleCsvDragOver}
                                                        onDragLeave={handleCsvDragLeave}
                                                        onDrop={handleCsvDrop}
                                                        style={{
                                                            border: '2px dashed var(--color-border)',
                                                            borderRadius: '12px',
                                                            padding: '60px 40px',
                                                            textAlign: 'center',
                                                            cursor: csvSubmitting ? 'not-allowed' : 'pointer',
                                                            transition: 'all 0.2s ease-out',
                                                            background: 'var(--color-muted)',
                                                            borderColor: csvDragOver ? 'var(--color-primary)' : 'var(--color-border)',
                                                            opacity: csvSubmitting ? 0.6 : 1,
                                                        }}
                                                    >
                                                        <div style={{ marginBottom: '20px' }}>
                                                            <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: 'var(--color-muted-foreground)', margin: '0 auto' }} />
                                                        </div>
                                                        <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '8px' }}>
                                                            Drag & drop your CSV file here, or click to upload
                                                        </div>
                                                        <div style={{ fontSize: '14px', color: 'var(--color-muted-foreground)' }}>Maximum 5 rows for bulk generation</div>
                                                    </div>
                                                    <input
                                                        ref={csvInputRef}
                                                        type="file"
                                                        accept=".csv,text/csv,text/plain"
                                                        onChange={(e) => {
                                                            const file = e.target.files?.[0] ?? null;
                                                            if (!file) return;
                                                            handleFileUpload(file);
                                                        }}
                                                        style={{ display: 'none' }}
                                                        disabled={csvSubmitting}
                                                    />
                                                </>
                                            ) : (
                                                <div
                                                    style={{
                                                        background: 'var(--color-muted)',
                                                        border: '1px solid var(--color-border)',
                                                        borderRadius: '12px',
                                                        padding: '20px',
                                                    }}
                                                >
                                                    <div style={{ marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                                        <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)' }}>Create your data inline (max 5 rows)</div>
                                                        <div style={{ display: 'flex', gap: '8px' }}>
                                                            <button
                                                                type="button"
                                                                onClick={addColumn}
                                                                disabled={editableHeaders.length >= 6}
                                                                style={{
                                                                    padding: '6px 12px',
                                                                    fontSize: '13px',
                                                                    fontWeight: 500,
                                                                    background: 'var(--color-card)',
                                                                    border: '1px solid var(--color-border)',
                                                                    borderRadius: '6px',
                                                                    color: 'var(--color-foreground)',
                                                                    cursor: editableHeaders.length >= 6 ? 'not-allowed' : 'pointer',
                                                                    opacity: editableHeaders.length >= 6 ? 0.5 : 1,
                                                                }}
                                                            >
                                                                <Plus size={14} style={{ marginRight: '4px', display: 'inline', verticalAlign: 'middle' }} />
                                                                Add Column
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={addRow}
                                                                disabled={editableData.length >= 5}
                                                                style={{
                                                                    padding: '6px 12px',
                                                                    fontSize: '13px',
                                                                    fontWeight: 500,
                                                                    background: 'var(--color-primary)',
                                                                    border: 'none',
                                                                    borderRadius: '6px',
                                                                    color: 'var(--color-primary-foreground)',
                                                                    cursor: editableData.length >= 5 ? 'not-allowed' : 'pointer',
                                                                    opacity: editableData.length >= 5 ? 0.5 : 1,
                                                                }}
                                                            >
                                                                <Plus size={14} style={{ marginRight: '4px', display: 'inline', verticalAlign: 'middle' }} />
                                                                Add Row
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div style={{ overflowX: 'auto', background: 'var(--color-card)', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
                                                        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                                            <thead style={{ background: 'var(--color-muted)' }}>
                                                                <tr>
                                                                    <th style={{ padding: '12px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '40px' }}>#</th>
                                                                    {editableHeaders.map((header) => (
                                                                        <th key={header} style={{ padding: '12px 16px', textAlign: 'left', borderBottom: '1px solid var(--color-border)', minWidth: '200px' }}>
                                                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                                                <input
                                                                                    type="text"
                                                                                    value={header}
                                                                                    onChange={(e) => updateHeaderName(header, e.target.value)}
                                                                                    style={{
                                                                                        flex: 1,
                                                                                        fontSize: '13px',
                                                                                        fontWeight: 600,
                                                                                        padding: '4px 8px',
                                                                                        border: '1px solid transparent',
                                                                                        borderRadius: '4px',
                                                                                        background: 'transparent',
                                                                                        color: 'var(--color-foreground)',
                                                                                    }}
                                                                                    onFocus={(e) => (e.target.style.border = '1px solid var(--color-primary)')}
                                                                                    onBlur={(e) => (e.target.style.border = '1px solid transparent')}
                                                                                />
                                                                                {editableHeaders.length > 1 && (
                                                                                    <button
                                                                                        type="button"
                                                                                        onClick={() => removeColumn(header)}
                                                                                        style={{ padding: '4px', background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--color-muted-foreground)' }}
                                                                                    >
                                                                                        <X size={16} />
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                        </th>
                                                                    ))}
                                                                    <th style={{ padding: '12px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '60px' }}>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {editableData.map((row, rowIndex) => (
                                                                    <tr key={rowIndex}>
                                                                        <td style={{ padding: '12px 16px', textAlign: 'center', fontSize: '13px', color: 'var(--color-muted-foreground)', borderBottom: '1px solid var(--color-border)' }}>{rowIndex + 1}</td>
                                                                        {editableHeaders.map((header) => (
                                                                            <td key={header} style={{ padding: '12px 16px', borderBottom: '1px solid var(--color-border)' }}>
                                                                                <input
                                                                                    type="text"
                                                                                    value={row[header] || ''}
                                                                                    onChange={(e) => updateCell(rowIndex, header, e.target.value)}
                                                                                    placeholder={`Enter ${header}...`}
                                                                                    style={{
                                                                                        width: '100%',
                                                                                        fontSize: '14px',
                                                                                        padding: '8px 12px',
                                                                                        border: '1px solid var(--color-border)',
                                                                                        borderRadius: '6px',
                                                                                        background: 'var(--color-card)',
                                                                                        color: 'var(--color-foreground)',
                                                                                    }}
                                                                                    onFocus={(e) => (e.target.style.borderColor = 'var(--color-primary)')}
                                                                                    onBlur={(e) => (e.target.style.borderColor = 'var(--color-border)')}
                                                                                />
                                                                            </td>
                                                                        ))}
                                                                        <td style={{ padding: '12px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)' }}>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => removeRow(rowIndex)}
                                                                                style={{ padding: '6px', background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--color-muted-foreground)', borderRadius: '4px' }}
                                                                            >
                                                                                <Trash2 size={16} />
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    {editableData.length === 0 && (
                                                        <div style={{ textAlign: 'center', padding: '40px 20px', color: 'var(--color-muted-foreground)', fontSize: '14px' }}>
                                                            No rows yet. Click "Add Row" to start creating your CSV data.
                                                        </div>
                                                    )}

                                                    <div style={{ marginTop: '16px', display: 'flex', justifyContent: 'flex-end' }}>
                                                        <button
                                                            type="button"
                                                            onClick={confirmCSVEditor}
                                                            disabled={editableData.length === 0}
                                                            style={{
                                                                padding: '10px 24px',
                                                                fontSize: '14px',
                                                                fontWeight: 500,
                                                                background: 'var(--color-primary)',
                                                                border: 'none',
                                                                borderRadius: '8px',
                                                                color: 'var(--color-primary-foreground)',
                                                                cursor: editableData.length === 0 ? 'not-allowed' : 'pointer',
                                                                opacity: editableData.length === 0 ? 0.5 : 1,
                                                            }}
                                                        >
                                                            Confirm & Continue
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            <div
                                                style={{
                                                    background: 'var(--color-muted)',
                                                    border: '1px solid var(--color-border)',
                                                    borderRadius: '12px',
                                                    padding: '24px',
                                                    marginBottom: '20px',
                                                }}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 0', borderBottom: '1px solid var(--color-border)' }}>
                                                    <FileText size={20} color="var(--color-muted-foreground)" />
                                                    <div>
                                                        <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '2px' }}>File Name</div>
                                                        <div style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)' }}>{fileName || (csvFile?.name ?? '')}</div>
                                                    </div>
                                                </div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 0' }}>
                                                    <Grid size={20} color="var(--color-muted-foreground)" />
                                                    <div>
                                                        <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '2px' }}>Rows Detected</div>
                                                        <div style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)' }}>{csvData.length} rows</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '12px', fontWeight: 500 }}>
                                                Showing {selectedRows.size} of {csvData.length} selected row{csvData.length !== 1 ? 's' : ''}
                                            </div>
                                            <div style={{ overflowX: 'auto' }}>
                                                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                                    <thead style={{ background: 'var(--color-muted)' }}>
                                                        <tr>
                                                            <th style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '40px' }}>
                                                                <input
                                                                    type="checkbox"
                                                                    checked={selectedRows.size === csvData.length}
                                                                    onChange={(e) => toggleAllRows(e.target.checked)}
                                                                    style={{ width: '17px', height: '17px', cursor: 'pointer', accentColor: 'var(--color-primary)' }}
                                                                />
                                                            </th>
                                                            {csvHeaders.map((header) => (
                                                                <th key={header} style={{ padding: '14px 16px', textAlign: 'left', borderBottom: '1px solid var(--color-border)' }}>
                                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                                                        <div style={{ textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.5px', color: 'var(--color-muted-foreground)', fontWeight: 600 }}>{header}</div>
                                                                        <select
                                                                            value={columnMappings[header] || 'Ignore this column'}
                                                                            onChange={(e) => updateMapping(header, e.target.value)}
                                                                            style={{
                                                                                fontSize: '13px',
                                                                                padding: '4px 8px 4px 0',
                                                                                border: 'none',
                                                                                background: 'transparent',
                                                                                cursor: 'pointer',
                                                                                fontWeight: 400,
                                                                                color: 'var(--color-muted-foreground)',
                                                                                outline: 'none',
                                                                            }}
                                                                        >
                                                                            <option value="Product Title">Product Title</option>
                                                                            <option value="Image Prompt">Image Prompt</option>
                                                                            <option value="Product ID">Product ID</option>
                                                                            <option value="Style Reference URL">Style Reference URL</option>
                                                                            <option value="Ignore this column">Ignore this column</option>
                                                                        </select>
                                                                    </div>
                                                                </th>
                                                            ))}
                                                            <th style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '60px' }}>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {csvData.map((row, rowIndex) => (
                                                            <tr key={rowIndex} style={{ transition: 'background-color 0.15s ease' }}>
                                                                <td style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)' }}>
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selectedRows.has(rowIndex)}
                                                                        onChange={() => toggleRow(rowIndex)}
                                                                        style={{ width: '17px', height: '17px', cursor: 'pointer', accentColor: 'var(--color-primary)' }}
                                                                    />
                                                                </td>
                                                                {csvHeaders.map((header) => (
                                                                    <td key={header} style={{ padding: '14px 16px', borderBottom: '1px solid var(--color-border)' }}>
                                                                        <input
                                                                            type="text"
                                                                            value={row[header] || ''}
                                                                            onChange={(e) => updateCellValue(rowIndex, header, e.target.value)}
                                                                            style={{
                                                                                width: '100%',
                                                                                fontSize: '14px',
                                                                                padding: '8px 12px',
                                                                                border: '1px solid var(--color-border)',
                                                                                borderRadius: '6px',
                                                                                background: 'var(--color-card)',
                                                                                color: 'var(--color-foreground)',
                                                                            }}
                                                                            onFocus={(e) => (e.target.style.borderColor = 'var(--color-primary)')}
                                                                            onBlur={(e) => (e.target.style.borderColor = 'var(--color-border)')}
                                                                        />
                                                                    </td>
                                                                ))}
                                                                <td style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)' }}>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => {
                                                                            const newData = csvData.filter((_, i) => i !== rowIndex);
                                                                            setCsvData(newData);

                                                                            const newSelectedRows = new Set<number>();
                                                                            selectedRows.forEach((index) => {
                                                                                if (index < rowIndex) newSelectedRows.add(index);
                                                                                else if (index > rowIndex) newSelectedRows.add(index - 1);
                                                                            });
                                                                            setSelectedRows(newSelectedRows);

                                                                            if (newData.length > 0) {
                                                                                const headers = Object.keys(newData[0]);
                                                                                const csvContent = [
                                                                                    headers.join(','),
                                                                                    ...newData.map((r) => headers.map((h) => `"${r[h] || ''}"`).join(',')),
                                                                                ].join('\n');
                                                                                const blob = new Blob([csvContent], { type: 'text/csv' });
                                                                                const file = new File([blob], fileName || 'edited.csv', { type: 'text/csv' });
                                                                                setCsvFile(file);
                                                                            }
                                                                        }}
                                                                        disabled={csvData.length <= 1}
                                                                        style={{
                                                                            padding: '6px',
                                                                            background: 'transparent',
                                                                            border: 'none',
                                                                            cursor: csvData.length <= 1 ? 'not-allowed' : 'pointer',
                                                                            color: csvData.length <= 1 ? 'var(--color-border)' : 'var(--color-muted-foreground)',
                                                                            borderRadius: '4px',
                                                                            opacity: csvData.length <= 1 ? 0.5 : 1,
                                                                            transition: 'color 0.15s ease',
                                                                        }}
                                                                        title={csvData.length <= 1 ? 'Cannot delete the last row' : 'Delete row'}
                                                                    >
                                                                        <Trash2 size={16} />
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}

                            {csvCurrentStep === 5 && (
                                <div style={{ animation: 'csvModalFadeIn 0.3s ease' }}>
                                    <div style={{ background: 'var(--color-muted)', border: '1px solid var(--color-border)', borderRadius: '12px', padding: '32px' }}>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                            <ImageIcon size={24} color="var(--color-muted-foreground)" />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Generation Count</div>
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>{selectedRows.size} Image{selectedRows.size !== 1 ? 's' : ''} will be generated</div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                            <FileText size={24} color="var(--color-muted-foreground)" />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Data Source</div>
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>Using {selectedRows.size} selected row{selectedRows.size !== 1 ? 's' : ''} from {fileName || (csvFile?.name ?? 'CSV')}</div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                            <Grid size={24} color="var(--color-muted-foreground)" />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Style References</div>
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>{project.brand_reference_count ?? 0} reference image{(project.brand_reference_count ?? 0) !== 1 ? 's' : ''}</div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                            <Clock size={24} color="var(--color-muted-foreground)" />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Estimated Time</div>
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>~2-3 minutes</div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0' }}>
                                            <AlertCircle size={24} color="var(--color-muted-foreground)" />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Credit Cost</div>
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>This will use {selectedRows.size} credits</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer (matches CSV Wizard button styling) */}
                        <div
                            style={{
                                padding: '24px 40px',
                                borderTop: '1px solid var(--color-border)',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                            }}
                        >
                            <button
                                type="button"
                                onClick={() => {
                                    if (csvCurrentStep === 5) {
                                        setCsvCurrentStep(2);
                                        return;
                                    }
                                    setCsvModalOpen(false);
                                }}
                                disabled={csvSubmitting}
                                style={{
                                    padding: '10px 24px',
                                    borderRadius: '8px',
                                    fontSize: '14px',
                                    fontWeight: 500,
                                    cursor: csvSubmitting ? 'not-allowed' : 'pointer',
                                    transition: 'all 0.2s ease-out',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: '8px',
                                    background: 'transparent',
                                    color: 'var(--color-muted-foreground)',
                                    border: '1px solid var(--color-border)',
                                    opacity: csvSubmitting ? 0.6 : 1,
                                }}
                            >
                                {csvCurrentStep === 5 ? (
                                    <>
                                        <ArrowLeft size={16} />
                                        Previous
                                    </>
                                ) : (
                                    'Cancel'
                                )}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    if (csvCurrentStep === 2) {
                                        if (!csvData.length) return;
                                        setCsvCurrentStep(5);
                                        return;
                                    }
                                    submitCsvGeneration();
                                }}
                                disabled={csvSubmitting || (csvCurrentStep === 2 ? !csvData.length : !csvFile)}
                                style={{
                                    padding: '10px 24px',
                                    borderRadius: '8px',
                                    fontSize: '14px',
                                    fontWeight: 500,
                                    cursor: csvSubmitting || (csvCurrentStep === 2 ? !csvData.length : !csvFile) ? 'not-allowed' : 'pointer',
                                    transition: 'all 0.2s ease-out',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: '8px',
                                    background: 'var(--color-primary)',
                                    color: 'var(--color-primary-foreground)',
                                    border: '1px solid var(--color-primary)',
                                    opacity: csvSubmitting || (csvCurrentStep === 2 ? !csvData.length : !csvFile) ? 0.5 : 1,
                                }}
                            >
                                {csvSubmitting ? (
                                    <>
                                        <span
                                            style={{
                                                display: 'inline-block',
                                                width: '16px',
                                                height: '16px',
                                                border: '2px solid color-mix(in srgb, var(--color-primary-foreground) 40%, transparent)',
                                                borderTopColor: 'var(--color-primary-foreground)',
                                                borderRadius: '50%',
                                                animation: 'csvModalSpin 0.6s linear infinite',
                                            }}
                                        />
                                        Generating…
                                    </>
                                ) : (
                                    <>
                                        {csvCurrentStep === 2 ? (
                                            <>
                                                Next
                                                <ChevronRight size={16} />
                                            </>
                                        ) : (
                                            <>
                                                Generate ({selectedRows.size} credits)
                                                <Zap size={16} />
                                            </>
                                        )}
                                    </>
                                )}
                            </button>
                        </div>
                    </div>

                    <style>{`
                        @keyframes csvModalFadeIn {
                            from { opacity: 0; transform: translateY(10px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes csvModalSpin {
                            from { transform: rotate(0deg); }
                            to { transform: rotate(360deg); }
                        }
                    `}</style>
                </DialogContent>
            </Dialog>

            <div className="min-h-screen bg-white">
                <div className="mx-auto px-8 py-8">
                    {/* Success Toast */}
                    {showSuccess && (page.props.success || batchCompleted) && (
                        <div className="mb-6 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
                            <div className="flex items-center gap-3">
                                <CheckCircle className="size-5" />
                                <span className="font-medium">{page.props.success || 'Batch generation complete. Your new images are ready.'}</span>
                            </div>
                            <button
                                onClick={() => setShowSuccess(false)}
                                className="text-green-600 hover:text-green-800"
                            >
                                <Plus className="size-4 rotate-45" />
                            </button>
                        </div>
                    )}

                    {/* Header */}
                    <div className="mb-8 flex items-center justify-between">
                        {/* Left side */}
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="sm" asChild className="text-gray-500">
                                <Link href="/projects" className="flex items-center gap-2">
                                    <ArrowLeft className="size-4" />
                                    My Projects
                                </Link>
                            </Button>

                        </div>

                        {/* Right side */}
                        <div className="flex items-center gap-3">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="gap-2 border"
                                style={{ borderColor: 'var(--color-border)', color: 'var(--color-foreground)', minWidth: '125px', background: 'var(--color-card)' }}
                                onClick={() => {
                                    if (selectedImages.length === project.images.length) {
                                        setSelectedImages([]);
                                    } else {
                                        setSelectedImages(project.images.map((_, idx) => idx));
                                    }
                                }}
                            >
                                {selectedImages.length === project.images.length ? (
                                    <>
                                        <Square className="size-4" />
                                        Deselect All
                                    </>
                                ) : (
                                    <>
                                        <SquareCheck className="size-4" />
                                        Select All
                                    </>
                                )}
                            </Button>
                            <Button
                                size="sm"
                                className="gap-2"
                                style={{
                                    backgroundColor: selectedImages.length > 0 ? 'var(--color-primary)' : 'var(--color-muted)',
                                    color: selectedImages.length > 0 ? 'var(--color-primary-foreground)' : 'var(--color-muted-foreground)',
                                    cursor: selectedImages.length === 0 ? 'not-allowed' : 'pointer',
                                    border: 'none'
                                }}
                                disabled={selectedImages.length === 0}
                                onClick={async () => {
                                    if (selectedImages.length === 0) return;

                                    // Import JSZip dynamically
                                    const JSZip = (await import('jszip')).default;
                                    const zip = new JSZip();

                                    // Download each selected image and add to zip
                                    const promises = selectedImages.map(async (index) => {
                                        const image = project.images[index];
                                        try {
                                            const response = await fetch(`/storage/${image.url}`);
                                            const blob = await response.blob();
                                            const filename = `${project.title}_image_${index + 1}.jpg`;
                                            zip.file(filename, blob);
                                        } catch (error) {
                                            console.error(`Failed to download image ${index}:`, error);
                                        }
                                    });

                                    await Promise.all(promises);

                                    // Generate zip file and trigger download
                                    const content = await zip.generateAsync({ type: 'blob' });
                                    const link = document.createElement('a');
                                    link.href = URL.createObjectURL(content);
                                    link.download = `${project.title}_images.zip`;
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    URL.revokeObjectURL(link.href);
                                }}
                            >
                                <Download className="size-4" />
                                Download ( {selectedImages.length} )
                            </Button>
                        </div>
                    </div>

                    {/* Generation Progress - show while pending OR when there were failures */}
                    {progress && (progress.pending > 0 || progress.failed > 0) && (
                        <div className="mb-6">
                            <BatchProgress
                                total={progress.total}
                                completed={progress.completed}
                                failed={progress.failed}
                                status={
                                    progress.pending > 0
                                        ? 'processing'
                                        : progress.failed > 0 && progress.completed > 0
                                            ? 'partial'
                                            : progress.failed > 0
                                                ? 'failed'
                                                : 'completed'
                                }
                                failures={progress.failure_reasons}
                            />
                        </div>
                    )}

                    {/* Project Title */}
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            {isEditingTitle ? (
                                <input
                                    ref={titleInputRef}
                                    type="text"
                                    value={editTitle}
                                    onChange={(e) => setEditTitle(e.target.value)}
                                    onBlur={handleTitleBlur}
                                    onKeyDown={handleTitleKeyDown}
                                    className="text-2xl font-semibold text-gray-900 border-b-2 border-blue-500 bg-transparent outline-none mb-1"
                                />
                            ) : (
                                <h1
                                    className="text-2xl font-semibold text-gray-900 mb-1 cursor-text hover:opacity-80 transition-opacity"
                                    onDoubleClick={handleTitleDoubleClick}
                                    title="Double-click to rename"
                                >
                                    {project.title}
                                </h1>
                            )}
                            <p className="text-gray-500">
                                {project.images_count} images
                            </p>
                        </div>

                        <Button
                            size="sm"
                            className="gap-2"
                            style={{ backgroundColor: 'var(--color-primary)', color: 'var(--color-primary-foreground)', border: 'none' }}
                            onClick={handleGenerateMore}
                        >
                            <Plus className="size-4" />
                            Generate More
                        </Button>
                    </div>

                    {/* Images Grid */}
                    {project.images.length > 0 || isGenerationPending ? (
                        <>
                            {isGenerationPending && (!progress || progress.pending > 0) && (
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="size-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" />
                                    <span className="text-sm font-medium text-gray-600">
                                        {progress && progress.total > 0
                                            ? `Generating ${project.images.length} of ${progress.total}${progress.failed > 0 ? ` (${progress.failed} failed)` : ''}…`
                                            : 'Preparing your images…'}
                                    </span>
                                </div>
                            )}
                            <div className="grid grid-cols-3 gap-4 xl:grid-cols-4">
                                {project.images.map((image, index) => {
                                    const isSelected = selectedImages.includes(index);
                                    const isFavorite = favoriteImages.includes(index);

                                    return (
                                        <div
                                            key={image.id}
                                            className="group relative aspect-square overflow-hidden cursor-pointer"
                                            style={{ borderRadius: '12px' }}
                                            onClick={() => {
                                                setSelectedImages(prev =>
                                                    prev.includes(index)
                                                        ? prev.filter(i => i !== index)
                                                        : [...prev, index]
                                                );
                                            }}
                                        >
                                            <img
                                                src={`/storage/${image.thumbnail_url || image.url}`}
                                                alt={image.prompt || `${project.title} - Image ${index + 1}`}
                                                className="h-full w-full object-cover"
                                            />

                                            {/* Persistent 3-dot menu — always visible, accessible on touch/keyboard */}
                                            <div className="absolute right-2 top-2 z-20">
                                                <button
                                                    className="flex size-7 items-center justify-center rounded-full shadow-sm transition-all hover:scale-110"
                                                    style={{ backgroundColor: '#ffffff', opacity: 1 }}
                                                    title="More options"
                                                    aria-label="More options"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        const next = openImageMenuIndex === index ? null : index;
                                                        console.log('[snapdraft:generation-debug] Image menu toggled', {
                                                            index,
                                                            imageId: image.id,
                                                            open: next !== null,
                                                            menuIncludesPromptCluster: true,
                                                        });
                                                        setOpenImageMenuIndex(next);
                                                    }}
                                                >
                                                    <MoreHorizontal className="size-4" style={{ color: '#000000' }} />
                                                </button>
                                                {openImageMenuIndex === index && (
                                                    <div
                                                        className="absolute right-0 top-8 z-30 w-40 overflow-hidden rounded-lg border shadow-lg"
                                                        style={{ background: '#ffffff', borderColor: 'var(--color-border)' }}
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        <button
                                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                                            onClick={() => { setOpenImageMenuIndex(null); openLightbox(index); }}
                                                        >
                                                            <Maximize className="size-3.5" /> Expand
                                                        </button>
                                                        <button
                                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                                            onClick={() => {
                                                                console.log('[snapdraft:generation-debug] Menu: Prompt & cluster clicked', { imageId: image.id });
                                                                setOpenImageMenuIndex(null);
                                                                openImageGenerationDebug(image);
                                                            }}
                                                        >
                                                            <Bug className="size-3.5" /> Prompt & cluster
                                                        </button>
                                                        <button
                                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                                            onClick={() => {
                                                                setOpenImageMenuIndex(null);
                                                                const encodedImageUrl = encodeURIComponent(image.url);
                                                                const encodedTitle = encodeURIComponent(project.title);
                                                                router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}&imageId=${image.id}`);
                                                            }}
                                                        >
                                                            <Edit className="size-3.5" /> Edit in Canvas
                                                        </button>
                                                        <button
                                                            className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted ${regeneratingByImageId[image.id] ? 'cursor-not-allowed opacity-50' : ''}`}
                                                            disabled={!!regeneratingByImageId[image.id]}
                                                            onClick={() => { setOpenImageMenuIndex(null); regenerateImage(image.id); }}
                                                        >
                                                            <RotateCw className={`size-3.5 ${regeneratingByImageId[image.id] ? 'animate-spin' : ''}`} />
                                                            {regeneratingByImageId[image.id] ? 'Regenerating…' : 'Regenerate'}
                                                        </button>
                                                        <button
                                                            className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted ${upscalingByImageId[image.id] ? 'cursor-not-allowed opacity-50' : ''}`}
                                                            disabled={!!upscalingByImageId[image.id]}
                                                            onClick={() => { setOpenImageMenuIndex(null); openUpscaleSelector(image.id); }}
                                                        >
                                                            <Sparkles className={`size-3.5 ${upscalingByImageId[image.id] ? 'animate-pulse' : ''}`} />
                                                            {upscalingByImageId[image.id] ? 'Upscaling…' : 'Upscale'}
                                                        </button>
                                                        <button
                                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                                            onClick={async () => {
                                                                setOpenImageMenuIndex(null);
                                                                try {
                                                                    const response = await fetch(`/storage/${image.url}`);
                                                                    const blob = await response.blob();
                                                                    const url = URL.createObjectURL(blob);
                                                                    const link = document.createElement('a');
                                                                    link.href = url;
                                                                    link.download = `${project.title}_image_${index + 1}.jpg`;
                                                                    document.body.appendChild(link);
                                                                    link.click();
                                                                    document.body.removeChild(link);
                                                                    URL.revokeObjectURL(url);
                                                                } catch (error) {
                                                                    console.error('Failed to download image:', error);
                                                                }
                                                            }}
                                                        >
                                                            <Download className="size-3.5" /> Download
                                                        </button>
                                                    </div>
                                                )}
                                            </div>

                                            {/* Selection Indicator - Always visible when selected */}
                                            {isSelected && (
                                                <div className="absolute inset-0 bg-black/20 pointer-events-none" />
                                            )}

                                            {/* Hover Overlay */}
                                            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none" />

                                            {/* Checkbox - Always visible when selected, or on hover (above overlay) */}
                                            <div className={`absolute left-3 top-3 z-20 ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'} transition-opacity duration-200`}>
                                                <button
                                                    className="flex size-6 items-center justify-center rounded-xl transition-colors hover:scale-110"
                                                    style={{
                                                        backgroundColor: isSelected ? 'var(--color-primary)' : 'var(--color-card)',
                                                    }}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setSelectedImages(prev =>
                                                            prev.includes(index)
                                                                ? prev.filter(i => i !== index)
                                                                : [...prev, index]
                                                        );
                                                    }}
                                                >
                                                    {isSelected && <Check className="size-4" style={{ color: 'var(--color-primary-foreground)' }} />}
                                                </button>
                                            </div>

                                            {/* Bottom Center - Action Icons (above overlay) */}
                                            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-20">
                                                <button
                                                    className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title="Expand"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        openLightbox(index);
                                                    }}
                                                >
                                                    <Maximize className="size-4" style={{ color: '#000000' }} />
                                                </button>
                                                <button
                                                    className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title="Prompt & cluster"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        openImageGenerationDebug(image);
                                                    }}
                                                >
                                                    <Bug className="size-4" style={{ color: '#000000' }} />
                                                </button>
                                                <button
                                                    className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title="Edit"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        // Open canvas editor with image
                                                        const encodedImageUrl = encodeURIComponent(image.url);
                                                        const encodedTitle = encodeURIComponent(project.title);
                                                        router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}&imageId=${image.id}`);
                                                    }}
                                                >
                                                    <Edit className="size-4" style={{ color: '#000000' }} />
                                                </button>
                                                <button
                                                    className={`flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg ${regeneratingByImageId[image.id] ? 'cursor-not-allowed opacity-60 hover:scale-100 hover:shadow-none' : ''}`}
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title="Regenerate"
                                                    disabled={!!regeneratingByImageId[image.id]}
                                                    aria-disabled={!!regeneratingByImageId[image.id]}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        regenerateImage(image.id);
                                                    }}
                                                >
                                                    <RotateCw className={`size-4 ${regeneratingByImageId[image.id] ? 'animate-spin' : ''}`} style={{ color: '#000000' }} />
                                                </button>
                                                <button
                                                    className={`flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg ${upscalingByImageId[image.id] ? 'cursor-not-allowed opacity-60 hover:scale-100 hover:shadow-none' : ''}`}
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title={upscalingByImageId[image.id] ? 'Upscaling…' : 'Upscale'}
                                                    disabled={!!upscalingByImageId[image.id]}
                                                    aria-disabled={!!upscalingByImageId[image.id]}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        openUpscaleSelector(image.id);
                                                    }}
                                                >
                                                    <Sparkles className={`size-4 ${upscalingByImageId[image.id] ? 'animate-pulse' : ''}`} style={{ color: '#000000' }} />
                                                </button>
                                                <button
                                                    className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                                    style={{ backgroundColor: '#ffffff' }}
                                                    title="Download"
                                                    onClick={async (e) => {
                                                        e.stopPropagation();
                                                        // Handle download
                                                        try {
                                                            const response = await fetch(`/storage/${image.url}`);
                                                            const blob = await response.blob();
                                                            const url = URL.createObjectURL(blob);
                                                            const link = document.createElement('a');
                                                            link.href = url;
                                                            link.download = `${project.title}_image_${index + 1}.jpg`;
                                                            document.body.appendChild(link);
                                                            link.click();
                                                            document.body.removeChild(link);
                                                            URL.revokeObjectURL(url);
                                                        } catch (error) {
                                                            console.error('Failed to download image:', error);
                                                        }
                                                    }}
                                                >
                                                    <Download className="size-4" style={{ color: '#000000' }} />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                                {/* Skeleton slots for images still being generated */}
                                {isGenerationPending && (() => {
                                    // If we have CSV row titles, render titled skeletons matching by csv_row_index
                                    if (csvRowTitles && csvRowTitles.length > 0) {
                                        const completedIndices = new Set(
                                            project.images
                                                .map(img => img.metadata?.csv_row_index)
                                                .filter((idx): idx is number => idx !== undefined && idx !== null)
                                        );
                                        const pendingItems = csvRowTitles
                                            .map((title, idx) => ({ title, idx }))
                                            .filter(({ idx }) => !completedIndices.has(idx));

                                        return pendingItems.map(({ title, idx }) => (
                                            <div
                                                key={`skeleton-csv-${idx}`}
                                                className="aspect-square overflow-hidden rounded-xl border bg-gray-100"
                                            >
                                                <div className="relative h-full w-full animate-pulse bg-gradient-to-br from-gray-100 via-gray-200 to-gray-100">
                                                    <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-gray-300/80 to-transparent px-3 py-2">
                                                        <p className="truncate text-xs font-medium text-gray-500">{title}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        ));
                                    }

                                    // Fallback: generic count-based skeletons
                                    const totalSlots = progress?.total ?? skeletonCount;
                                    const pendingSlots = Math.max(0, totalSlots - project.images.length);
                                    return Array.from({ length: pendingSlots }).map((_, i) => (
                                        <div
                                            key={`skeleton-${i}`}
                                            className="aspect-square overflow-hidden rounded-xl border bg-gray-100"
                                        >
                                            <div className="h-full w-full animate-pulse bg-gradient-to-br from-gray-100 via-gray-200 to-gray-100" />
                                        </div>
                                    ));
                                })()}
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-gray-500 mb-4">No images generated yet.</p>
                            <Button
                                onClick={handleGenerateMore}
                                className="gap-2"
                                style={{ backgroundColor: 'var(--color-primary)', color: 'var(--color-primary-foreground)', border: 'none' }}
                            >
                                <Plus className="size-4" />
                                Generate Images
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {/* Lightbox Modal */}
            {lightboxOpen && project.images.length > 0 && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
                    onClick={closeLightbox}
                >
                    {/* Close Button */}
                    <button
                        className="absolute right-6 top-6 z-50 flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                        onClick={closeLightbox}
                        title="Close (Esc)"
                    >
                        <X className="size-6" />
                    </button>

                    {/* Image Counter */}
                    <div className="absolute left-1/2 top-6 z-50 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm text-white backdrop-blur-sm">
                        {lightboxImageIndex + 1} / {project.images.length}
                    </div>

                    {/* Previous Button */}
                    {project.images.length > 1 && (
                        <button
                            className="absolute left-6 top-1/2 z-50 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                            onClick={(e) => {
                                e.stopPropagation();
                                goToPrevImage();
                            }}
                            title="Previous (←)"
                        >
                            <ChevronLeft className="size-6" />
                        </button>
                    )}

                    {/* Next Button */}
                    {project.images.length > 1 && (
                        <button
                            className="absolute right-6 top-1/2 z-50 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                            onClick={(e) => {
                                e.stopPropagation();
                                goToNextImage();
                            }}
                            title="Next (→)"
                        >
                            <ChevronRight className="size-6" />
                        </button>
                    )}

                    {/* Main Image */}
                    <div
                        className="relative max-h-[90vh] max-w-[90vw]"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <img
                            src={`/storage/${project.images[lightboxImageIndex].url}`}
                            alt={project.images[lightboxImageIndex].prompt || `${project.title} - Image ${lightboxImageIndex + 1}`}
                            className="max-h-[90vh] max-w-[90vw] object-contain"
                        />

                        {/* Image Actions */}
                        <div className="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white p-3">
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white text-black transition-all hover:bg-gray-100"
                                title="Edit"
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    const encodedImageUrl = encodeURIComponent(image.url);
                                    const encodedTitle = encodeURIComponent(project.title);
                                    router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}&imageId=${image.id}`);
                                }}
                            >
                                <Edit className="size-4" />
                            </button>

                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white text-black transition-all hover:bg-gray-100"
                                title="Download"
                                onClick={async () => {
                                    const image = project.images[lightboxImageIndex];
                                    try {
                                        const response = await fetch(`/storage/${image.url}`);
                                        const blob = await response.blob();
                                        const url = URL.createObjectURL(blob);
                                        const link = document.createElement('a');
                                        link.href = url;
                                        link.download = `${project.title}_image_${lightboxImageIndex + 1}.jpg`;
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                        URL.revokeObjectURL(url);
                                    } catch (error) {
                                        console.error('Failed to download image:', error);
                                    }
                                }}
                            >
                                <Download className="size-4" />
                            </button>

                            <button
                                className={`flex size-10 items-center justify-center rounded-full bg-white text-black transition-all hover:bg-gray-100 ${regeneratingByImageId[project.images[lightboxImageIndex].id] ? 'cursor-not-allowed opacity-60 hover:bg-white' : ''}`}
                                title={regeneratingByImageId[project.images[lightboxImageIndex].id] ? 'Regenerating…' : 'Regenerate'}
                                disabled={!!regeneratingByImageId[project.images[lightboxImageIndex].id]}
                                aria-disabled={!!regeneratingByImageId[project.images[lightboxImageIndex].id]}
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    regenerateImage(image.id, { closeLightbox: true });
                                }}
                            >
                                <RotateCw className={`size-4 ${regeneratingByImageId[project.images[lightboxImageIndex].id] ? 'animate-spin' : ''}`} />
                            </button>
                            <button
                                className={`flex size-10 items-center justify-center rounded-full bg-white text-black transition-all hover:bg-gray-100 ${upscalingByImageId[project.images[lightboxImageIndex].id] ? 'cursor-not-allowed opacity-60 hover:bg-white' : ''}`}
                                title={upscalingByImageId[project.images[lightboxImageIndex].id] ? 'Upscaling…' : 'Upscale'}
                                disabled={!!upscalingByImageId[project.images[lightboxImageIndex].id]}
                                aria-disabled={!!upscalingByImageId[project.images[lightboxImageIndex].id]}
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    openUpscaleSelector(image.id);
                                }}
                            >
                                <Sparkles className={`size-4 ${upscalingByImageId[project.images[lightboxImageIndex].id] ? 'animate-pulse' : ''}`} />
                            </button>
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white text-black transition-all hover:bg-gray-100"
                                title="Prompt & cluster"
                                onClick={() => {
                                    openImageGenerationDebug(project.images[lightboxImageIndex]);
                                }}
                            >
                                <Bug className="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            )}
            <UpscaleModal
                isOpen={upscaleModalOpen}
                selectedFactor={upscaleFactor}
                onClose={() => {
                    if (!upscaleImageId || !upscalingByImageId[upscaleImageId]) {
                        setUpscaleModalOpen(false);
                    }
                }}
                onSelectFactor={(factor) => setUpscaleFactor(factor)}
                onConfirm={runUpscale}
                isLoading={!!(upscaleImageId && upscalingByImageId[upscaleImageId])}
            />

            <GenerationDebugDialog
                open={debugImage !== null}
                onOpenChange={(open) => !open && closeImageGenerationDebug()}
                title={debugImage ? `Image #${debugImage.id}` : 'Generation debug'}
                loading={debugLoading}
                error={debugError}
                data={debugData}
            />
        </AppLayout>
    );
}