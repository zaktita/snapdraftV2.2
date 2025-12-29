import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, FileText, Grid, Image as ImageIcon, Upload, X, Clock, AlertCircle, Zap, Plus, Trash2, Edit3 } from 'lucide-react';
import { useState, useRef, DragEvent, ChangeEvent, useEffect } from 'react';
import csv from '@/routes/projects/wizards/csv';

const aspectRatioOptions = [
    { value: '1:1', label: '1:1 Square' },
    { value: '4:5', label: '4:5 Portrait' },
    { value: '3:4', label: '3:4 Portrait' },
    { value: '2:3', label: '2:3 Portrait' },
    { value: '9:16', label: '9:16 Portrait/Story' },
    { value: '3:2', label: '3:2 Landscape' },
    { value: '4:3', label: '4:3 Landscape' },
    { value: '5:4', label: '5:4 Landscape' },
    { value: '2:1', label: '2:1 Wide' },
    { value: '16:9', label: '16:9 Landscape' },
    { value: '21:9', label: '21:9 Cinematic' },
    { value: '4:1', label: '4:1 Banner' },
];

interface CSVRow {
    [key: string]: string;
}

interface ColumnMapping {
    [key: string]: string;
}

type UploadMode = 'upload' | 'create';

const stepContent = {
    1: {
        title: 'Name Your Project',
        subtitle: 'Give your project a descriptive name to help organize your work.'
    },
    2: {
        title: 'Upload Your CSV File',
        subtitle: "We'll analyze your data and detect the structure.",
        titleAfterUpload: 'Review, Map & Edit Data',
        subtitleAfterUpload: 'Confirm your data is correct before proceeding.'
    },
    3: {
        title: 'Review, Map & Edit Data',
        subtitle: 'Confirm your data is correct before proceeding.'
    },
    4: {
        title: 'Add Style References (Optional)',
        subtitle: (count: number) => `Upload 5-10 images to define the visual style for the ${count} selected product${count !== 1 ? 's' : ''}. You can skip this step if you prefer.`
    },
    5: {
        title: 'Preview & Generate',
        subtitle: 'Review your settings and start generation.'
    }
};

export default function CSVWizard() {
    const page = usePage<{ error?: string }>();
    const [currentStep, setCurrentStep] = useState(1);
    const [csvData, setCsvData] = useState<CSVRow[]>([]);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const [columnMappings, setColumnMappings] = useState<ColumnMapping>({});
    const [styleImages, setStyleImages] = useState<string[]>([]);
    const [styleImageFiles, setStyleImageFiles] = useState<File[]>([]);
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [projectName, setProjectName] = useState('');
    const [fileName, setFileName] = useState('');
    const [uploadComplete, setUploadComplete] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [uploadMode, setUploadMode] = useState<UploadMode>('upload');
    const [editableData, setEditableData] = useState<CSVRow[]>([]);
    const [editableHeaders, setEditableHeaders] = useState<string[]>(['title', 'description', 'format']);
    const [showError, setShowError] = useState(false);
    const [textAccurate, setTextAccurate] = useState(false);
    
    const csvInputRef = useRef<HTMLInputElement>(null);
    const imageInputRef = useRef<HTMLInputElement>(null);

    // Show error message if present (supports legacy top-level or flash container)
    const errorMessage = (page.props as any).error || (page.props as any).flash?.error;

    useEffect(() => {
        if (errorMessage) {
            setShowError(true);
            const timer = setTimeout(() => setShowError(false), 7000);
            return () => clearTimeout(timer);
        }
    }, [errorMessage]);

    // Parse CSV
    const parseCSV = (text: string): CSVRow[] => {
        const lines = text.trim().split('\n');
        const headers = lines[0].split(',').map(h => h.trim());
        const data: CSVRow[] = [];
        
        for (let i = 1; i < Math.min(lines.length, 6); i++) {
            const values = lines[i].split(',').map(v => v.trim());
            const row: CSVRow = {};
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            data.push(row);
        }
        
        // Auto-detect column mappings
        const mappings: ColumnMapping = {};
        headers.forEach(header => {
            const lower = header.toLowerCase();
            if (lower.includes('title') || lower.includes('name')) {
                mappings[header] = 'Product Title';
            } else if (lower.includes('description') || lower.includes('prompt')) {
                mappings[header] = 'Image Prompt';
            } else if (lower.includes('format')) {
                mappings[header] = 'Format';
            } else if (lower.includes('id')) {
                mappings[header] = 'Product ID';
            } else {
                mappings[header] = 'Ignore this column';
            }
        });
        setColumnMappings(mappings);
        
        return data;
    };

    // Handle file upload
    const handleFileUpload = (file: File) => {
        setFileName(file.name);
        setCsvFile(file);
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const text = e.target?.result as string;
            const data = parseCSV(text);
            setCsvData(data);
            
            // Initialize selected rows
            setSelectedRows(new Set(data.map((_, index) => index)));
            
            setUploadComplete(true);
        };
        
        reader.readAsText(file);
    };

    // Drag and drop handlers
    const handleDragOver = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setDragOver(true);
    };

    const handleDragLeave = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setDragOver(false);
    };

    const handleDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        setDragOver(false);
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    };

    const handleFileInputChange = (e: ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            handleFileUpload(files[0]);
        }
    };

    // CSV cell editing
    const updateCellValue = (rowIndex: number, header: string, value: string) => {
        const newData = [...csvData];
        newData[rowIndex][header] = value;
        setCsvData(newData);
        
        // Regenerate CSV file
        const headers = Object.keys(csvData[0]);
        const csvContent = [
            headers.join(','),
            ...newData.map(row => headers.map(h => `"${row[h] || ''}"`).join(','))
        ].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const file = new File([blob], fileName || 'edited.csv', { type: 'text/csv' });
        setCsvFile(file);
    };

    // CSV Creator functions
    const addRow = () => {
        const newRow: CSVRow = {};
        editableHeaders.forEach(header => {
            newRow[header] = header.toLowerCase() === 'format' ? '1:1' : '';
        });
        setEditableData([...editableData, newRow]);
    };

    const removeRow = (index: number) => {
        setEditableData(editableData.filter((_, i) => i !== index));
    };

    const updateCell = (rowIndex: number, header: string, value: string) => {
        const newData = [...editableData];
        newData[rowIndex][header] = value;
        setEditableData(newData);
    };

    const addColumn = () => {
        const newHeader = `column_${editableHeaders.length + 1}`;
        setEditableHeaders([...editableHeaders, newHeader]);
        setEditableData(editableData.map(row => ({ ...row, [newHeader]: '' })));
    };

    const removeColumn = (header: string) => {
        if (editableHeaders.length <= 1) return;
        setEditableHeaders(editableHeaders.filter(h => h !== header));
        setEditableData(editableData.map(row => {
            const newRow = { ...row };
            delete newRow[header];
            return newRow;
        }));
    };

    const updateHeaderName = (oldHeader: string, newHeader: string) => {
        if (!newHeader.trim() || editableHeaders.includes(newHeader)) return;
        setEditableHeaders(editableHeaders.map(h => h === oldHeader ? newHeader : h));
        setEditableData(editableData.map(row => {
            const newRow = { ...row };
            newRow[newHeader] = newRow[oldHeader];
            delete newRow[oldHeader];
            return newRow;
        }));
    };

    const confirmCSVEditor = () => {
        if (editableData.length === 0) return;
        
        setCsvData(editableData.slice(0, 5));
        setFileName('Custom CSV');
        
        const mappings: ColumnMapping = {};
        editableHeaders.forEach(header => {
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
        
        setSelectedRows(new Set(editableData.slice(0, 5).map((_, index) => index)));
        
        const csvContent = [
            editableHeaders.join(','),
            ...editableData.slice(0, 5).map(row => 
                editableHeaders.map(h => `"${row[h] || ''}"`).join(',')
            )
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const file = new File([blob], 'custom.csv', { type: 'text/csv' });
        setCsvFile(file);
        
        setUploadComplete(true);
    };

    // Handle image upload
    const handleImageUpload = (e: ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files) return;
        
        Array.from(files).forEach(file => {
            const reader = new FileReader();
            reader.onload = (e) => {
                setStyleImages(prev => [...prev, e.target?.result as string]);
            };
            reader.readAsDataURL(file);
            setStyleImageFiles(prev => [...prev, file]);
        });
    };

    const removeImage = (index: number) => {
        setStyleImages(prev => prev.filter((_, i) => i !== index));
        setStyleImageFiles(prev => prev.filter((_, i) => i !== index));
    };

    // Toggle row selection
    const toggleRow = (index: number) => {
        setSelectedRows(prev => {
            const newSet = new Set(prev);
            if (newSet.has(index)) {
                newSet.delete(index);
            } else {
                newSet.add(index);
            }
            return newSet;
        });
    };

    const toggleAllRows = (checked: boolean) => {
        if (checked) {
            setSelectedRows(new Set(csvData.map((_, index) => index)));
        } else {
            setSelectedRows(new Set());
        }
    };

    // Update column mapping
    const updateMapping = (header: string, value: string) => {
        setColumnMappings(prev => ({ ...prev, [header]: value }));
    };

    // Navigation
    const submitToBackend = () => {
        // Build a sensible default name if not provided
        const name = projectName.trim().length
            ? projectName.trim()
            : (fileName ? fileName.replace(/\.[^.]+$/, '') : 'CSV Project');

        if (!csvFile) return;
        if (isSubmitting) return;

        setIsSubmitting(true);

        const fd = new FormData();
        fd.append('project_name', name);
        fd.append('csv_file', csvFile);
        fd.append('text_accurate', textAccurate ? '1' : '0');
        
        // Add reference images only if provided (optional)
        if (styleImageFiles.length > 0) {
            styleImageFiles.slice(0, 10).forEach((f) => fd.append('reference_images[]', f));
        }
        // product_images optional: skip for now

        router.post(csv.store.url(), fd, {
            forceFormData: true,
            preserveScroll: false,
            onError: () => setIsSubmitting(false),
            onFinish: () => setIsSubmitting(false),
        });
    };

    const nextStep = () => {
        // Step 1: Project Name - must have name
        if (currentStep === 1 && !projectName.trim()) return;
        
        // Step 2: CSV Upload - must have data
        if (currentStep === 2 && !csvData.length) return;
        
        // If on step 2 and upload is complete, skip to step 4 (style references)
        if (currentStep === 2 && uploadComplete) {
            setCurrentStep(4);
        } else if (currentStep < 5) {
            setCurrentStep(currentStep + 1);
        } else {
            // Step 5: Submit to backend
            submitToBackend();
        }
    };

    const previousStep = () => {
        if (currentStep > 1) {
            // If on step 4 and we skipped step 3, go back to step 2
            if (currentStep === 4 && uploadComplete) {
                setCurrentStep(2);
            } else {
                setCurrentStep(currentStep - 1);
            }
        }
    };


    const getTitle = () => {
        if (currentStep === 2 && uploadComplete) {
            return stepContent[2].titleAfterUpload;
        }
        return stepContent[currentStep as keyof typeof stepContent].title;
    };

    const getSubtitle = () => {
        if (currentStep === 2 && uploadComplete) {
            return stepContent[2].subtitleAfterUpload;
        }
        const subtitle = stepContent[currentStep as keyof typeof stepContent].subtitle;
        return typeof subtitle === 'function' ? subtitle(selectedRows.size) : subtitle;
    };

    const headers = csvData.length > 0 ? Object.keys(csvData[0]) : [];

    return (
        <>
            <Head title="Create CSV Project" />
            
                        {/* Loading Overlay During Submission */}
                        {isSubmitting && (
                            <div style={{
                                position: 'fixed',
                                top: 0,
                                left: 0,
                                right: 0,
                                bottom: 0,
                                background: 'rgba(0, 0, 0, 0.8)',
                                zIndex: 9999,
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                justifyContent: 'center',
                                backdropFilter: 'blur(4px)',
                            }}>
                                <div style={{
                                    background: 'var(--color-card)',
                                    borderRadius: '16px',
                                    padding: '40px 48px',
                                    maxWidth: '400px',
                                    textAlign: 'center',
                                    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.3)',
                                }}>
                                    <div style={{
                                        width: '64px',
                                        height: '64px',
                                        margin: '0 auto 24px',
                                        border: '4px solid var(--color-muted)',
                                        borderTopColor: 'hsl(var(--primary))',
                                        borderRadius: '50%',
                                        animation: 'spin 0.8s linear infinite',
                                    }} />
                                    <h3 style={{
                                        fontSize: '20px',
                                        fontWeight: 600,
                                        color: 'var(--color-foreground)',
                                        marginBottom: '12px',
                                    }}>
                                        Starting Generation...
                                    </h3>
                                    <p style={{
                                        fontSize: '14px',
                                        color: 'var(--color-muted-foreground)',
                                        lineHeight: 1.6,
                                        margin: 0,
                                    }}>
                                        Setting up your project and queuing {selectedRows.size} image{selectedRows.size !== 1 ? 's' : ''} for generation. You'll be redirected to your project dashboard shortly.
                                    </p>
                                </div>
                            </div>
                        )}
            
            <div style={{
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                background: 'var(--color-muted)',
                color: 'var(--color-foreground)',
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '40px 20px'
            }}>
                <div style={{
                    background: 'var(--color-card)',
                    borderRadius: '12px',
                    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.08)',
                    maxWidth: '900px',
                    width: '100%',
                    overflow: 'hidden'
                }}>
                    {/* Header */}
                    <div style={{
                        padding: '32px 40px',
                        borderBottom: '1px solid var(--color-border)'
                    }}>
                        <Link 
                            href="/projects" 
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '6px',
                                fontSize: '13px',
                                color: 'var(--color-muted-foreground)',
                                textDecoration: 'none',
                                marginBottom: '16px',
                                transition: 'all 0.2s ease-out'
                            }}
                        >
                            <ArrowLeft size={14} />
                            Back to Projects
                        </Link>
                        
                        {/* Error Alert */}
                        {showError && errorMessage && (
                            <div style={{
                                padding: '12px 16px',
                                marginBottom: '16px',
                                backgroundColor: 'hsl(var(--destructive) / 0.1)',
                                border: '1px solid hsl(var(--destructive) / 0.3)',
                                borderRadius: '8px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '12px'
                            }}>
                                <AlertCircle size={18} style={{ color: 'hsl(var(--destructive))', flexShrink: 0 }} />
                                <p style={{ 
                                    margin: 0, 
                                    fontSize: '14px', 
                                    color: 'hsl(var(--destructive))',
                                    lineHeight: 1.5
                                }}>
                                    {errorMessage}
                                </p>
                                <button
                                    onClick={() => setShowError(false)}
                                    style={{
                                        marginLeft: 'auto',
                                        background: 'none',
                                        border: 'none',
                                        cursor: 'pointer',
                                        color: 'hsl(var(--destructive))',
                                        padding: '4px',
                                        display: 'flex',
                                        alignItems: 'center'
                                    }}
                                >
                                    <X size={16} />
                                </button>
                            </div>
                        )}
                        
                        <h1 style={{
                            fontSize: '24px',
                            fontWeight: 600,
                            color: 'var(--color-foreground)',
                            marginBottom: '6px'
                        }}>
                            {getTitle()}
                        </h1>
                        <p style={{
                            fontSize: '14px',
                            color: 'var(--color-muted-foreground)',
                            lineHeight: 1.5,
                            margin: 0
                        }}>
                            {getSubtitle()}
                        </p>
                    </div>

                    {/* Progress Bar */}
                    <div style={{
                        padding: '0 40px 24px',
                        borderBottom: '1px solid var(--color-border)'
                    }}>
                        <div style={{
                            display: 'flex',
                            gap: '8px',
                            marginBottom: '12px'
                        }}>
                            {[1, 2, 3, 4].map((step) => {
                                let className = '';
                                if (currentStep === 1 && uploadComplete) {
                                    if (step === 1) className = 'completed';
                                    if (step === 2) className = 'current';
                                } else {
                                    if (step < currentStep) className = 'completed';
                                    if (step === currentStep) className = 'current';
                                }
                                
                                return (
                                    <div 
                                        key={step}
                                        style={{
                                            flex: 1,
                                            height: '4px',
                                            background: className === 'completed' ? 'var(--color-primary)' : className === 'current' ? 'var(--color-muted)' : 'var(--color-border)',
                                            borderRadius: '2px',
                                            transition: 'all 0.2s ease-out'
                                        }}
                                    />
                                );
                            })}
                        </div>
                        <div style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontSize: '12px',
                            color: 'var(--color-muted-foreground)',
                            fontWeight: 500
                        }}>
                            <span style={{ color: currentStep === 1 ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Project Name</span>
                            <span style={{ color: (currentStep === 2 && !uploadComplete) || (currentStep === 2 && uploadComplete) ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Upload</span>
                            <span style={{ color: (currentStep === 3) || (currentStep === 2 && uploadComplete) ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Review Data</span>
                            <span style={{ color: currentStep === 4 ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Style References</span>
                            <span style={{ color: currentStep === 5 ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Generate</span>
                        </div>
                    </div>

                    {/* Wizard Body */}
                    <div style={{
                        padding: '40px',
                        minHeight: '400px'
                    }}>
                        {/* Step 1: Project Name */}
                        {currentStep === 1 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{ maxWidth: '500px', margin: '0 auto' }}>
                                    <label style={{
                                        display: 'block',
                                        fontSize: '14px',
                                        fontWeight: 500,
                                        color: 'var(--color-foreground)',
                                        marginBottom: '8px'
                                    }}>
                                        Project Name
                                    </label>
                                    <input
                                        type="text"
                                        value={projectName}
                                        onChange={(e) => setProjectName(e.target.value)}
                                        placeholder="e.g., Summer Campaign 2025"
                                        style={{
                                            width: '100%',
                                            padding: '12px 16px',
                                            fontSize: '15px',
                                            border: '1px solid var(--color-border)',
                                            borderRadius: '8px',
                                            background: 'var(--color-background)',
                                            color: 'var(--color-foreground)',
                                            outline: 'none',
                                            transition: 'border-color 0.2s ease'
                                        }}
                                        onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                        onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                    />
                                    <p style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginTop: '6px' }}>
                                        Choose a name that helps you identify this project later
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Step 2: Upload CSV */}
                        {currentStep === 2 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                {!uploadComplete ? (
                                    <>
                                        {/* Tab Switcher */}
                                            <div style={{
                                                display: 'flex',
                                                gap: '8px',
                                                marginBottom: '24px',
                                                borderBottom: '1px solid var(--color-border)',
                                                paddingBottom: '0'
                                            }}>
                                            <button
                                                onClick={() => setUploadMode('upload')}
                                                style={{
                                                    padding: '12px 20px',
                                                    fontSize: '14px',
                                                    fontWeight: 500,
                                                    background: 'transparent',
                                                    border: 'none',
                                                    borderBottom: uploadMode === 'upload' ? '2px solid var(--color-primary)' : '2px solid transparent',
                                                    color: uploadMode === 'upload' ? 'var(--color-foreground)' : 'var(--color-muted-foreground)',
                                                    cursor: 'pointer',
                                                    transition: 'all 0.2s ease'
                                                }}
                                            >
                                                <Upload size={16} style={{ display: 'inline', marginRight: '6px', verticalAlign: 'middle' }} />
                                                Upload CSV
                                            </button>
                                            <button
                                                onClick={() => {
                                                    setUploadMode('create');
                                                    if (editableData.length === 0) {
                                                        const initialRows = Array(3).fill(null).map(() => {
                                                            const row: CSVRow = {};
                                                            editableHeaders.forEach(h => row[h] = '');
                                                            return row;
                                                        });
                                                        setEditableData(initialRows);
                                                    }
                                                }}
                                                style={{
                                                    padding: '12px 20px',
                                                    fontSize: '14px',
                                                    fontWeight: 500,
                                                    background: 'transparent',
                                                    border: 'none',
                                                    borderBottom: uploadMode === 'create' ? '2px solid var(--color-primary)' : '2px solid transparent',
                                                    color: uploadMode === 'create' ? 'var(--color-foreground)' : 'var(--color-muted-foreground)',
                                                    cursor: 'pointer',
                                                    transition: 'all 0.2s ease'
                                                }}
                                            >
                                                <Edit3 size={16} style={{ display: 'inline', marginRight: '6px', verticalAlign: 'middle' }} />
                                                Create CSV
                                            </button>
                                        </div>

                                        {uploadMode === 'upload' ? (
                                            <>
                                                <div 
                                                    onClick={() => csvInputRef.current?.click()}
                                                    onDragOver={handleDragOver}
                                                    onDragLeave={handleDragLeave}
                                                    onDrop={handleDrop}
                                                    style={{
                                                        border: '2px dashed var(--color-border)',
                                                        borderRadius: '12px',
                                                        padding: '60px 40px',
                                                        textAlign: 'center',
                                                        cursor: 'pointer',
                                                        transition: 'all 0.2s ease-out',
                                                        background: 'var(--color-muted)',
                                                        borderColor: dragOver ? 'var(--color-primary)' : 'var(--color-border)'
                                                    }}
                                                >
                                                    <div style={{ marginBottom: '20px' }}>
                                                        <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: 'var(--color-muted-foreground)', margin: '0 auto' }} />
                                                    </div>
                                                    <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '8px' }}>
                                                        Drag & drop your CSV file here, or click to upload
                                                    </div>
                                                    <div style={{ fontSize: '14px', color: 'var(--color-muted-foreground)' }}>
                                                        Maximum 5 rows for bulk generation
                                                    </div>
                                                </div>
                                                <input 
                                                    ref={csvInputRef}
                                                    type="file" 
                                                    accept=".csv" 
                                                    onChange={handleFileInputChange}
                                                    style={{ display: 'none' }} 
                                                />
                                            </>
                                        ) : (
                                            <div style={{
                                                background: 'var(--color-muted)',
                                                border: '1px solid var(--color-border)',
                                                borderRadius: '12px',
                                                padding: '20px'
                                            }}>
                                                <div style={{ marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                                    <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)' }}>
                                                        Create your data inline (max 5 rows)
                                                    </div>
                                                    <div style={{ display: 'flex', gap: '8px' }}>
                                                        <button
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
                                                                opacity: editableHeaders.length >= 6 ? 0.5 : 1
                                                            }}
                                                        >
                                                            <Plus size={14} style={{ marginRight: '4px', display: 'inline', verticalAlign: 'middle' }} />
                                                            Add Column
                                                        </button>
                                                        <button
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
                                                                opacity: editableData.length >= 5 ? 0.5 : 1
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
                                                                                    color: 'var(--color-foreground)'
                                                                                }}
                                                                                onFocus={(e) => e.target.style.border = '1px solid var(--color-primary)'}
                                                                                onBlur={(e) => e.target.style.border = '1px solid transparent'}
                                                                            />
                                                                            {editableHeaders.length > 1 && (
                                                                                <button
                                                                                    onClick={() => removeColumn(header)}
                                                                                    style={{
                                                                                        padding: '4px',
                                                                                        background: 'transparent',
                                                                                        border: 'none',
                                                                                        cursor: 'pointer',
                                                                                        color: 'var(--color-muted-foreground)'
                                                                                    }}
                                                                                >
                                                                                    <X size={16} />
                                                                                </button>
                                                                            )}
                                                                        </div>
                                                                    </th>
                                                                ))}
                                                                <th style={{ padding: '12px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '60px' }}>
                                                                    Actions
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {editableData.map((row, rowIndex) => (
                                                                <tr key={rowIndex}>
                                                                    <td style={{ padding: '12px 16px', textAlign: 'center', fontSize: '13px', color: 'var(--color-muted-foreground)', borderBottom: '1px solid var(--color-border)' }}>
                                                                        {rowIndex + 1}
                                                                    </td>
                                                                    {editableHeaders.map(header => (
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
                                                                                    color: 'var(--color-foreground)'
                                                                                }}
                                                                                onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                                                                onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                                                            />
                                                                        </td>
                                                                    ))}
                                                                    <td style={{ padding: '12px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)' }}>
                                                                        <button
                                                                            onClick={() => removeRow(rowIndex)}
                                                                            style={{
                                                                                padding: '6px',
                                                                                background: 'transparent',
                                                                                border: 'none',
                                                                                cursor: 'pointer',
                                                                                color: 'var(--color-muted-foreground)',
                                                                                borderRadius: '4px'
                                                                            }}
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
                                                    <div style={{
                                                        textAlign: 'center',
                                                        padding: '40px 20px',
                                                        color: 'var(--color-muted-foreground)',
                                                        fontSize: '14px'
                                                    }}>
                                                        No rows yet. Click "Add Row" to start creating your CSV data.
                                                    </div>
                                                )}

                                                <div style={{ marginTop: '16px', display: 'flex', justifyContent: 'flex-end' }}>
                                                    <button
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
                                                            opacity: editableData.length === 0 ? 0.5 : 1
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
                                        <div style={{
                                            background: 'var(--color-muted)',
                                            border: '1px solid var(--color-border)',
                                            borderRadius: '12px',
                                            padding: '24px',
                                            marginBottom: '20px'
                                        }}>
                                            <div style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '12px',
                                                padding: '12px 0',
                                                borderBottom: '1px solid var(--color-border)'
                                            }}>
                                                <FileText size={20} color="var(--color-muted-foreground)" />
                                                <div>
                                                    <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '2px' }}>File Name</div>
                                                    <div style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)' }}>{fileName}</div>
                                                </div>
                                            </div>
                                            <div style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '12px',
                                                padding: '12px 0'
                                            }}>
                                                <Grid size={20} color="var(--color-muted-foreground)" />
                                                <div>
                                                    <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '2px' }}>Rows Detected</div>
                                                    <div style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)' }}>{csvData.length} rows</div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Data Table */}
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
                                                        {headers.map(header => (
                                                            <th key={header} style={{ padding: '14px 16px', textAlign: 'left', borderBottom: '1px solid var(--color-border)' }}>
                                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                                                    <div style={{ textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.5px', color: 'var(--color-muted-foreground)', fontWeight: 600 }}>
                                                                        {header}
                                                                    </div>
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
                                                                            outline: 'none'
                                                                        }}
                                                                    >
                                                                        <option value="Product Title">Product Title</option>
                                                                        <option value="Image Prompt">Image Prompt</option>
                                                                        <option value="Format">Format</option>
                                                                        <option value="Product ID">Product ID</option>
                                                                        <option value="Style Reference URL">Style Reference URL</option>
                                                                        <option value="Ignore this column">Ignore this column</option>
                                                                    </select>
                                                                </div>
                                                            </th>
                                                        ))}
                                                        <th style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)', width: '60px' }}>
                                                            Actions
                                                        </th>
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
                                                            {headers.map(header => {
                                                                const isFormat = header.toLowerCase() === 'format';
                                                                return (
                                                                    <td key={header} style={{ padding: '14px 16px', borderBottom: '1px solid var(--color-border)' }}>
                                                                        {isFormat ? (
                                                                            <select
                                                                                value={row[header] || '1:1'}
                                                                                onChange={(e) => updateCellValue(rowIndex, header, e.target.value)}
                                                                                style={{
                                                                                    width: '100%',
                                                                                    fontSize: '14px',
                                                                                    padding: '10px 12px',
                                                                                    border: '1px solid var(--color-border)',
                                                                                    borderRadius: '6px',
                                                                                    background: 'var(--color-card)',
                                                                                    color: 'var(--color-foreground)',
                                                                                    cursor: 'pointer'
                                                                                }}
                                                                                onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                                                                onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                                                            >
                                                                                {aspectRatioOptions.map((opt) => (
                                                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                                                ))}
                                                                            </select>
                                                                        ) : (
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
                                                                                    color: 'var(--color-foreground)'
                                                                                }}
                                                                                onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                                                                onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                                                            />
                                                                        )}
                                                                    </td>
                                                                );
                                                            })}
                                                            <td style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid var(--color-border)' }}>
                                                                <button
                                                                    onClick={() => {
                                                                        // Remove row from csvData
                                                                        const newData = csvData.filter((_, i) => i !== rowIndex);
                                                                        setCsvData(newData);
                                                                        
                                                                        // Update selected rows
                                                                        const newSelectedRows = new Set<number>();
                                                                        selectedRows.forEach(index => {
                                                                            if (index < rowIndex) {
                                                                                newSelectedRows.add(index);
                                                                            } else if (index > rowIndex) {
                                                                                newSelectedRows.add(index - 1);
                                                                            }
                                                                        });
                                                                        setSelectedRows(newSelectedRows);
                                                                        
                                                                        // Regenerate CSV file
                                                                        if (newData.length > 0) {
                                                                            const headers = Object.keys(newData[0]);
                                                                            const csvContent = [
                                                                                headers.join(','),
                                                                                ...newData.map(row => headers.map(h => `"${row[h] || ''}"`).join(','))
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
                                                                        transition: 'color 0.15s ease'
                                                                    }}
                                                                    onMouseEnter={(e) => {
                                                                        if (csvData.length > 1) {
                                                                            e.currentTarget.style.color = 'var(--color-destructive)';
                                                                        }
                                                                    }}
                                                                    onMouseLeave={(e) => {
                                                                        if (csvData.length > 1) {
                                                                            e.currentTarget.style.color = 'var(--color-muted-foreground)';
                                                                        }
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

                        {/* Step 3: Review Data (skipped if upload from step 2) */}
                        {currentStep === 3 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                {/* This step would show data review, but it's currently incorporated in step 2 */}
                            </div>
                        )}

                        {/* Step 4: Style References (Optional) */}
                        {currentStep === 4 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div 
                                    onClick={() => imageInputRef.current?.click()}
                                    style={{
                                        border: '2px dashed var(--color-border)',
                                        borderRadius: '12px',
                                        padding: '60px 40px',
                                        textAlign: 'center',
                                        cursor: 'pointer',
                                        transition: 'all 0.2s ease-out',
                                        background: 'var(--color-muted)',
                                        marginBottom: '20px'
                                    }}
                                >
                                    <div style={{ marginBottom: '20px' }}>
                                        <ImageIcon style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: 'var(--color-muted-foreground)', margin: '0 auto' }} />
                                    </div>
                                    <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '8px' }}>
                                        Drag & drop your style images here, or click to upload
                                    </div>
                                    <div style={{ fontSize: '14px', color: 'var(--color-muted-foreground)' }}>
                                        Upload 5-10 images to define the visual style (optional)
                                    </div>
                                </div>
                                <input 
                                    ref={imageInputRef}
                                    type="file" 
                                    accept="image/*" 
                                    multiple
                                    onChange={handleImageUpload}
                                    style={{ display: 'none' }} 
                                />

                                <div style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
                                    gap: '12px',
                                    marginTop: '20px'
                                }}>
                                    {styleImages.map((src, index) => (
                                        <div key={index} style={{
                                            position: 'relative',
                                            aspectRatio: '1',
                                            borderRadius: '8px',
                                            overflow: 'hidden',
                                            border: '1px solid var(--color-border)'
                                        }}>
                                            <img src={src} alt={`Style ${index + 1}`} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                            <div 
                                                onClick={() => removeImage(index)}
                                                style={{
                                                    position: 'absolute',
                                                    top: '6px',
                                                    right: '6px',
                                                    width: '24px',
                                                    height: '24px',
                                                    background: 'color-mix(in srgb, var(--color-background) 60%, transparent)',
                                                    borderRadius: '50%',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    cursor: 'pointer',
                                                    transition: 'all 0.2s ease-out'
                                                }}
                                            >
                                                <X size={12} color="var(--color-foreground)" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Step 5: Review & Generate */}
                        {currentStep === 5 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{
                                    background: 'var(--color-muted)',
                                    border: '1px solid var(--color-border)',
                                    borderRadius: '12px',
                                    padding: '32px'
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                        <ImageIcon size={24} color="var(--color-muted-foreground)" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Generation Count</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                {selectedRows.size} Image{selectedRows.size !== 1 ? 's' : ''} will be generated
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                        <FileText size={24} color="var(--color-muted-foreground)" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Data Source</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                Using {selectedRows.size} selected row{selectedRows.size !== 1 ? 's' : ''} from {fileName}
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                        <Grid size={24} color="var(--color-muted-foreground)" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Style References</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                {styleImages.length} reference image{styleImages.length !== 1 ? 's' : ''}
                                            </div>
                                            <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
                                                {styleImages.slice(0, 3).map((src, index) => (
                                                    <img key={index} src={src} alt={`Preview ${index + 1}`} style={{
                                                        width: '48px',
                                                        height: '48px',
                                                        objectFit: 'cover',
                                                        borderRadius: '4px',
                                                        border: '1px solid var(--color-border)'
                                                    }} />
                                                ))}
                                                {styleImages.length > 3 && (
                                                    <div style={{
                                                        width: '48px',
                                                        height: '48px',
                                                        borderRadius: '4px',
                                                        border: '1px solid var(--color-border)',
                                                        background: 'var(--color-card)',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontSize: '12px',
                                                        color: 'var(--color-muted-foreground)',
                                                        fontWeight: 500
                                                    }}>
                                                        +{styleImages.length - 3}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid var(--color-border)' }}>
                                        <Clock size={24} color="var(--color-muted-foreground)" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Estimated Time</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                ~2-3 minutes
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0' }}>
                                        <AlertCircle size={24} color="var(--color-muted-foreground)" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginBottom: '4px' }}>Credit Cost</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                This will use {textAccurate ? selectedRows.size * 4 : selectedRows.size} of your 1,250 available credits
                                            </div>
                                        </div>
                                    </div>

                                    {/* Text Accuracy Toggle */}
                                    <div style={{
                                        marginTop: '24px',
                                        padding: '20px',
                                        background: 'var(--color-card)',
                                        border: `2px solid ${textAccurate ? 'hsl(var(--primary))' : 'var(--color-border)'}`,
                                        borderRadius: '12px',
                                        transition: 'all 0.2s ease',
                                    }}>
                                        <label style={{
                                            display: 'flex',
                                            alignItems: 'flex-start',
                                            gap: '16px',
                                            cursor: 'pointer',
                                        }}>
                                            <input
                                                type="checkbox"
                                                checked={textAccurate}
                                                onChange={(e) => setTextAccurate(e.target.checked)}
                                                style={{
                                                    marginTop: '2px',
                                                    width: '18px',
                                                    height: '18px',
                                                    cursor: 'pointer',
                                                    accentColor: 'hsl(var(--primary))',
                                                }}
                                            />
                                            <div style={{ flex: 1 }}>
                                                <div style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '8px',
                                                    marginBottom: '6px',
                                                }}>
                                                    <span style={{
                                                        fontSize: '15px',
                                                        fontWeight: 600,
                                                        color: 'var(--color-foreground)',
                                                    }}>
                                                        Increase Text Accuracy
                                                    </span>
                                                    <span style={{
                                                        fontSize: '11px',
                                                        fontWeight: 600,
                                                        padding: '2px 8px',
                                                        borderRadius: '12px',
                                                        background: 'hsl(var(--primary) / 0.1)',
                                                        color: 'hsl(var(--primary))',
                                                    }}>
                                                        4× CREDITS
                                                    </span>
                                                </div>
                                                <p style={{
                                                    margin: 0,
                                                    fontSize: '13px',
                                                    lineHeight: 1.5,
                                                    color: 'var(--color-muted-foreground)',
                                                }}>
                                                    Enable superior text rendering and accuracy for images with headlines, product labels, or precise typography.
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div style={{
                        padding: '24px 40px',
                        borderTop: '1px solid var(--color-border)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center'
                    }}>
                        <button 
                            onClick={previousStep}
                            style={{
                                padding: '10px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: 500,
                                cursor: 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: 'transparent',
                                color: 'var(--color-muted-foreground)',
                                border: '1px solid var(--color-border)',
                                visibility: currentStep === 1 ? 'hidden' : 'visible'
                            }}
                        >
                            <ArrowLeft size={16} />
                            Previous
                        </button>
                        <button 
                            onClick={nextStep}
                            disabled={(currentStep === 1 && !projectName.trim()) || (currentStep === 2 && !csvData.length) || isSubmitting}
                            style={{
                                padding: '10px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: 500,
                                cursor: ((currentStep === 1 && !projectName.trim()) || (currentStep === 2 && !csvData.length) || isSubmitting) ? 'not-allowed' : 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: 'var(--color-primary)',
                                color: 'var(--color-primary-foreground)',
                                border: '1px solid var(--color-primary)',
                                opacity: ((currentStep === 1 && !projectName.trim()) || (currentStep === 2 && !csvData.length) || isSubmitting) ? 0.5 : 1
                            }}
                        >
                            {isSubmitting ? (
                                <>
                                    <span style={{
                                        display: 'inline-block',
                                        width: '16px',
                                        height: '16px',
                                        border: '2px solid color-mix(in srgb, var(--color-primary-foreground) 40%, transparent)',
                                        borderTopColor: 'var(--color-primary-foreground)',
                                        borderRadius: '50%',
                                        animation: 'spin 0.6s linear infinite'
                                    }} />
                                    Creating Project...
                                </>
                            ) : currentStep === 4 ? (
                                <>
                                    {styleImages.length > 0 ? 'Next' : 'Skip Style References'}
                                    <ArrowRight size={16} />
                                </>
                            ) : currentStep === 5 ? (
                                <>
                                    Generate ({textAccurate ? selectedRows.size * 4 : selectedRows.size} credits)
                                    <Zap size={16} />
                                </>
                            ) : (
                                <>
                                    Next
                                    <ArrowRight size={16} />
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                @keyframes spin {
                    from {
                        transform: rotate(0deg);
                    }
                    to {
                        transform: rotate(360deg);
                    }
                }
            `}</style>
        </>
    );
}
