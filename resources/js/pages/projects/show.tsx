import { Button } from '@/components/ui/button';
import { BatchProgress } from '@/components/batch-progress';
import { useGenerationProgress } from '@/hooks/use-generation-progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { ArrowLeft, Star, Download, MoreHorizontal, BoxSelect, Square, SquareCheck, Edit, Maximize, RotateCw, Share, Trash2, Check, Plus, CheckCircle, X, ChevronLeft, ChevronRight, Upload, Edit3, FileText, Grid, Clock, AlertCircle, Image as ImageIcon, Zap } from 'lucide-react';
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
    hasPendingGenerations?: boolean; // Whether there are pending AI generations
    progress?: {
        total: number;
        completed: number;
        failed: number;
        pending: number;
    } | null;
}

export default function ProjectShow({ project, justCreated = false, expectedImages = 0, hasPendingGenerations = false, progress = null }: ProjectShowProps) {
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
    const [textAccurate, setTextAccurate] = useState(false);
    const [csvSubmitting, setCsvSubmitting] = useState(false);
    const [csvDragOver, setCsvDragOver] = useState(false);
    const [isEditingTitle, setIsEditingTitle] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const [showSuccess, setShowSuccess] = useState(false);
    // Initialize banner state based on flash message OR pending generations prop
    const [showGeneratingBanner, setShowGeneratingBanner] = useState(!!page.props.generating || hasPendingGenerations);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [lightboxImageIndex, setLightboxImageIndex] = useState(0);
    const titleInputRef = useRef<HTMLInputElement>(null);
    const csvInputRef = useRef<HTMLInputElement>(null);

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
        if (page.props.success) {
            setShowSuccess(true);
            // Auto-hide after 5 seconds
            const timer = setTimeout(() => setShowSuccess(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [page.props.success]);
    
    // Simple polling: check every 5 seconds if generating, reload when image count changes
    useEffect(() => {
        if (!showGeneratingBanner) return;
        
        const initialImageCount = project.images.length;
        
        const interval = setInterval(() => {
            router.reload({ 
                only: ['project', 'hasPendingGenerations', 'progress'],
                onSuccess: (page) => {
                    const newProject = (page.props as any).project;
                    const stillPending = (page.props as any).hasPendingGenerations;
                    
                    // Stop polling if we have new images AND no more pending generations
                    if (newProject.images.length > initialImageCount && !stillPending) {
                        setShowGeneratingBanner(false);
                        clearInterval(interval);
                    } else if (!stillPending && newProject.images.length === initialImageCount) {
                        // Case where generation might have failed (no new images, but no longer pending)
                        setShowGeneratingBanner(false);
                        clearInterval(interval);
                    }
                }
            });
        }, 3000); // Poll every 3 seconds
        
        return () => clearInterval(interval);
    }, [showGeneratingBanner, project.images.length]);
    
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
        setTextAccurate(false);
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
        setShowGeneratingBanner(true);

        router.post(`/projects/${project.id}/csv`, { csv_file: csvFile, text_accurate: textAccurate ? '1' : '0' }, {
            forceFormData: true,
            preserveScroll: true,
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
                                    marginBottom: '6px',
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
                                    : 'Upload or create a CSV. Style references are already set for this project.'}
                            </p>
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
                                                <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>This will use {textAccurate ? selectedRows.size * 4 : selectedRows.size} credits</div>
                                            </div>
                                        </div>

                                        <div
                                            style={{
                                                marginTop: '24px',
                                                padding: '20px',
                                                background: 'var(--color-card)',
                                                border: `2px solid ${textAccurate ? 'hsl(var(--primary))' : 'var(--color-border)'}`,
                                                borderRadius: '12px',
                                                transition: 'all 0.2s ease',
                                            }}
                                        >
                                            <label style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', cursor: 'pointer' }}>
                                                <input
                                                    type="checkbox"
                                                    checked={textAccurate}
                                                    onChange={(e) => setTextAccurate(e.target.checked)}
                                                    style={{ marginTop: '2px', width: '18px', height: '18px', cursor: 'pointer', accentColor: 'hsl(var(--primary))' }}
                                                />
                                                <div style={{ flex: 1 }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
                                                        <span style={{ fontSize: '15px', fontWeight: 600, color: 'var(--color-foreground)' }}>Increase Text Accuracy</span>
                                                        <span style={{ fontSize: '11px', fontWeight: 600, padding: '2px 8px', borderRadius: '12px', background: 'hsl(var(--primary) / 0.1)', color: 'hsl(var(--primary))' }}>4× CREDITS</span>
                                                    </div>
                                                    <p style={{ margin: 0, fontSize: '13px', lineHeight: 1.5, color: 'var(--color-muted-foreground)' }}>
                                                        Enable superior text rendering and accuracy for images with headlines, product labels, or precise typography.
                                                    </p>
                                                </div>
                                            </label>
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
                                                Generate ({textAccurate ? selectedRows.size * 4 : selectedRows.size} credits)
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
                    {showSuccess && page.props.success && (
                        <div className="mb-6 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
                            <div className="flex items-center gap-3">
                                <CheckCircle className="size-5" />
                                <span className="font-medium">{page.props.success}</span>
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

                    {/* Generation Status Banner */}
                    {showGeneratingBanner && (
                        <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                            <div className="flex items-center gap-3">
                                <div className="size-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600 dark:border-blue-800 dark:border-t-blue-400"></div>
                                <div>
                                    <p className="font-medium text-blue-900 dark:text-blue-100">
                                        Generating your image...
                                    </p>
                                    <p className="text-sm text-blue-700 dark:text-blue-300">
                                        This may take 30-60 seconds. The page will automatically update when complete.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Generation Progress - show optimistic progress when a generation was just started or pending */}
                    {(justCreated || hasPendingGenerations || (progress && progress.pending > 0)) && (
                        <div className="mb-6">
                            <BatchProgress
                                total={progress ? progress.total : (expectedImages || 1)}
                                completed={progress ? progress.completed : 0}
                                failed={progress ? progress.failed : 0}
                                status={progress && progress.pending === 0 ? 'completed' : 'processing'}
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
                    {project.images.length > 0 ? (
                        <div className="grid grid-cols-5 gap-4">
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
                                            style={{ backgroundColor: 'var(--color-muted)' }}
                                            title="Expand"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                openLightbox(index);
                                            }}
                                        >
                                            <Maximize className="size-4" style={{ color: 'var(--color-foreground)' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: 'var(--color-muted)' }}
                                            title="Edit"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Open canvas editor with image
                                                const encodedImageUrl = encodeURIComponent(image.url);
                                                const encodedTitle = encodeURIComponent(project.title);
                                                router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}`);
                                            }}
                                        >
                                            <Edit className="size-4" style={{ color: 'var(--color-foreground)' }} />
                                        </button>
                                        <button 
                                            className={`flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg ${regeneratingByImageId[image.id] ? 'cursor-not-allowed opacity-60 hover:scale-100 hover:shadow-none' : ''}`}
                                            style={{ backgroundColor: 'var(--color-muted)' }}
                                            title="Regenerate"
                                            disabled={!!regeneratingByImageId[image.id]}
                                            aria-disabled={!!regeneratingByImageId[image.id]}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                regenerateImage(image.id);
                                            }}
                                        >
                                            <RotateCw className={`size-4 ${regeneratingByImageId[image.id] ? 'animate-spin' : ''}`} style={{ color: 'var(--color-foreground)' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: 'var(--color-muted)' }}
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
                                            <Download className="size-4" style={{ color: 'var(--color-foreground)' }} />
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
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
                        <div className="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white/10 p-3 backdrop-blur-sm">
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30"
                                title="Edit"
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    const encodedImageUrl = encodeURIComponent(image.url);
                                    const encodedTitle = encodeURIComponent(project.title);
                                    router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}`);
                                }}
                            >
                                <Edit className="size-4" />
                            </button>
                            
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30"
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
                                className={`flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30 ${regeneratingByImageId[project.images[lightboxImageIndex].id] ? 'cursor-not-allowed opacity-60 hover:bg-white/20' : ''}`}
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
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}