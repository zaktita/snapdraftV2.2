import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, FileText, Grid, Image as ImageIcon, Upload, X, Check, Clock, AlertCircle, Zap } from 'lucide-react';
import { useState, useRef, DragEvent, ChangeEvent } from 'react';

interface CSVRow {
    [key: string]: string;
}

interface ColumnMapping {
    [key: string]: string;
}

const stepContent = {
    1: {
        title: 'Upload Your CSV File',
        subtitle: "We'll analyze your data and detect the structure.",
        titleAfterUpload: 'Review, Map & Edit Data',
        subtitleAfterUpload: 'Confirm your data is correct before proceeding.'
    },
    2: {
        title: 'Review, Map & Edit Data',
        subtitle: 'Confirm your data is correct before proceeding.'
    },
    3: {
        title: 'Add Style References',
        subtitle: (count: number) => `Upload 5-10 images to define the visual style for the ${count} selected product${count !== 1 ? 's' : ''}.`
    },
    4: {
        title: 'Preview & Generate',
        subtitle: 'Review your settings and start generation.'
    }
};

export default function CSVWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [csvData, setCsvData] = useState<CSVRow[]>([]);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const [columnMappings, setColumnMappings] = useState<ColumnMapping>({});
    const [styleImages, setStyleImages] = useState<string[]>([]);
    const [fileName, setFileName] = useState('');
    const [uploadComplete, setUploadComplete] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    
    const csvInputRef = useRef<HTMLInputElement>(null);
    const imageInputRef = useRef<HTMLInputElement>(null);

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
        });
    };

    const removeImage = (index: number) => {
        setStyleImages(prev => prev.filter((_, i) => i !== index));
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
    const nextStep = () => {
        if (currentStep === 1 && !csvData.length) return;
        
        if (currentStep === 1 && uploadComplete) {
            setCurrentStep(3);
        } else if (currentStep < 4) {
            setCurrentStep(currentStep + 1);
        } else {
            // Generate
            showToast('Success! Your images are now generating.');
            setTimeout(() => {
                router.visit('/projects');
            }, 2000);
        }
    };

    const previousStep = () => {
        if (currentStep > 1) {
            if (currentStep === 3 && uploadComplete) {
                setCurrentStep(1);
            } else {
                setCurrentStep(currentStep - 1);
            }
        }
    };

    const showToast = (message: string) => {
        // Toast implementation can be added later
        console.log(message);
    };

    const getTitle = () => {
        if (currentStep === 1 && uploadComplete) {
            return stepContent[1].titleAfterUpload;
        }
        return stepContent[currentStep as keyof typeof stepContent].title;
    };

    const getSubtitle = () => {
        if (currentStep === 1 && uploadComplete) {
            return stepContent[1].subtitleAfterUpload;
        }
        const subtitle = stepContent[currentStep as keyof typeof stepContent].subtitle;
        return typeof subtitle === 'function' ? subtitle(selectedRows.size) : subtitle;
    };

    const headers = csvData.length > 0 ? Object.keys(csvData[0]) : [];

    return (
        <>
            <Head title="Create CSV Project" />
            
            <div style={{
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                background: '#F7F7F5',
                color: '#373737',
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '40px 20px'
            }}>
                <div style={{
                    background: '#ffffff',
                    borderRadius: '12px',
                    boxShadow: '0 4px 12px rgba(0, 0, 0, 0.08)',
                    maxWidth: '900px',
                    width: '100%',
                    overflow: 'hidden'
                }}>
                    {/* Header */}
                    <div style={{
                        padding: '32px 40px',
                        borderBottom: '1px solid #e5e7eb'
                    }}>
                        <Link 
                            href="/projects" 
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '6px',
                                fontSize: '13px',
                                color: '#787774',
                                textDecoration: 'none',
                                marginBottom: '16px',
                                transition: 'all 0.2s ease-out'
                            }}
                        >
                            <ArrowLeft size={14} />
                            Back to Projects
                        </Link>
                        <h1 style={{
                            fontSize: '24px',
                            fontWeight: 600,
                            color: '#373737',
                            marginBottom: '6px'
                        }}>
                            {getTitle()}
                        </h1>
                        <p style={{
                            fontSize: '14px',
                            color: '#787774',
                            lineHeight: 1.5,
                            margin: 0
                        }}>
                            {getSubtitle()}
                        </p>
                    </div>

                    {/* Progress Bar */}
                    <div style={{
                        padding: '0 40px 24px',
                        borderBottom: '1px solid #e5e7eb'
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
                                            background: className === 'completed' ? '#1a1a1a' : className === 'current' ? '#e5e5e5' : '#e5e7eb',
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
                            color: '#9b9a97',
                            fontWeight: 500
                        }}>
                            <span style={{ color: (currentStep === 1 && !uploadComplete) || (currentStep === 1 && uploadComplete) ? '#373737' : '#9b9a97' }}>Upload</span>
                            <span style={{ color: (currentStep === 2) || (currentStep === 1 && uploadComplete) ? '#373737' : '#9b9a97' }}>Review Data</span>
                            <span style={{ color: currentStep === 3 ? '#373737' : '#9b9a97' }}>Style References</span>
                            <span style={{ color: currentStep === 4 ? '#373737' : '#9b9a97' }}>Generate</span>
                        </div>
                    </div>

                    {/* Wizard Body */}
                    <div style={{
                        padding: '40px',
                        minHeight: '400px'
                    }}>
                        {/* Step 1: Upload CSV */}
                        {currentStep === 1 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                {!uploadComplete ? (
                                    <>
                                        <div 
                                            onClick={() => csvInputRef.current?.click()}
                                            onDragOver={handleDragOver}
                                            onDragLeave={handleDragLeave}
                                            onDrop={handleDrop}
                                            style={{
                                                border: '2px dashed #e5e7eb',
                                                borderRadius: '12px',
                                                padding: '60px 40px',
                                                textAlign: 'center',
                                                cursor: 'pointer',
                                                transition: 'all 0.2s ease-out',
                                                background: dragOver ? '#e5e5e5' : '#F7F7F5',
                                                borderColor: dragOver ? '#1a1a1a' : '#e5e7eb'
                                            }}
                                        >
                                            <div style={{ marginBottom: '20px' }}>
                                                <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: '#9b9a97', margin: '0 auto' }} />
                                            </div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                                Drag & drop your CSV file here, or click to upload
                                            </div>
                                            <div style={{ fontSize: '14px', color: '#787774' }}>
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
                                    <>
                                        <div style={{
                                            background: '#F7F7F5',
                                            border: '1px solid #e5e7eb',
                                            borderRadius: '12px',
                                            padding: '24px',
                                            marginBottom: '20px'
                                        }}>
                                            <div style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '12px',
                                                padding: '12px 0',
                                                borderBottom: '1px solid #e5e7eb'
                                            }}>
                                                <FileText size={20} color="#787774" />
                                                <div>
                                                    <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '2px' }}>File Name</div>
                                                    <div style={{ fontSize: '14px', fontWeight: 500, color: '#373737' }}>{fileName}</div>
                                                </div>
                                            </div>
                                            <div style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '12px',
                                                padding: '12px 0'
                                            }}>
                                                <Grid size={20} color="#787774" />
                                                <div>
                                                    <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '2px' }}>Rows Detected</div>
                                                    <div style={{ fontSize: '14px', fontWeight: 500, color: '#373737' }}>{csvData.length} rows</div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Data Table */}
                                        <div style={{ fontSize: '13px', color: '#787774', marginBottom: '12px', fontWeight: 500 }}>
                                            Showing {selectedRows.size} of {csvData.length} selected row{csvData.length !== 1 ? 's' : ''}
                                        </div>
                                        <div style={{ overflowX: 'auto' }}>
                                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                                <thead style={{ background: '#fafafa' }}>
                                                    <tr>
                                                        <th style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid #e5e7eb', width: '40px' }}>
                                                            <input 
                                                                type="checkbox" 
                                                                checked={selectedRows.size === csvData.length}
                                                                onChange={(e) => toggleAllRows(e.target.checked)}
                                                                style={{ width: '17px', height: '17px', cursor: 'pointer', accentColor: '#1a1a1a' }}
                                                            />
                                                        </th>
                                                        {headers.map(header => (
                                                            <th key={header} style={{ padding: '14px 16px', textAlign: 'left', borderBottom: '1px solid #e5e7eb' }}>
                                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                                                    <div style={{ textTransform: 'uppercase', fontSize: '11px', letterSpacing: '0.5px', color: '#9b9a97', fontWeight: 600 }}>
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
                                                                            color: '#787774',
                                                                            outline: 'none'
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {csvData.map((row, rowIndex) => (
                                                        <tr key={rowIndex} style={{ transition: 'background-color 0.15s ease' }}>
                                                            <td style={{ padding: '14px 16px', textAlign: 'center', borderBottom: '1px solid #e5e7eb' }}>
                                                                <input 
                                                                    type="checkbox" 
                                                                    checked={selectedRows.has(rowIndex)}
                                                                    onChange={() => toggleRow(rowIndex)}
                                                                    style={{ width: '17px', height: '17px', cursor: 'pointer', accentColor: '#1a1a1a' }}
                                                                />
                                                            </td>
                                                            {headers.map(header => (
                                                                <td key={header} style={{ padding: '14px 16px', fontSize: '14px', color: '#787774', borderBottom: '1px solid #e5e7eb' }}>
                                                                    {row[header]}
                                                                </td>
                                                            ))}
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {/* Step 3: Style References */}
                        {currentStep === 3 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div 
                                    onClick={() => imageInputRef.current?.click()}
                                    style={{
                                        border: '2px dashed #e5e7eb',
                                        borderRadius: '12px',
                                        padding: '60px 40px',
                                        textAlign: 'center',
                                        cursor: 'pointer',
                                        transition: 'all 0.2s ease-out',
                                        background: '#F7F7F5',
                                        marginBottom: '20px'
                                    }}
                                >
                                    <div style={{ marginBottom: '20px' }}>
                                        <ImageIcon style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: '#9b9a97', margin: '0 auto' }} />
                                    </div>
                                    <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        Drag & drop your style images here, or click to upload
                                    </div>
                                    <div style={{ fontSize: '14px', color: '#787774' }}>
                                        Upload 5-10 images to define the visual style
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
                                            border: '1px solid #e5e7eb'
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
                                                    background: 'rgba(0, 0, 0, 0.6)',
                                                    borderRadius: '50%',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    cursor: 'pointer',
                                                    transition: 'all 0.2s ease-out'
                                                }}
                                            >
                                                <X size={12} color="white" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Step 4: Review & Generate */}
                        {currentStep === 4 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{
                                    background: '#F7F7F5',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '12px',
                                    padding: '32px'
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid #e5e7eb' }}>
                                        <ImageIcon size={24} color="#787774" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '4px' }}>Generation Count</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737' }}>
                                                {selectedRows.size} Image{selectedRows.size !== 1 ? 's' : ''} will be generated
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid #e5e7eb' }}>
                                        <FileText size={24} color="#787774" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '4px' }}>Data Source</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737' }}>
                                                Using {selectedRows.size} selected row{selectedRows.size !== 1 ? 's' : ''} from {fileName}
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid #e5e7eb' }}>
                                        <Grid size={24} color="#787774" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '4px' }}>Style References</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737' }}>
                                                {styleImages.length} reference image{styleImages.length !== 1 ? 's' : ''}
                                            </div>
                                            <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
                                                {styleImages.slice(0, 3).map((src, index) => (
                                                    <img key={index} src={src} alt={`Preview ${index + 1}`} style={{
                                                        width: '48px',
                                                        height: '48px',
                                                        objectFit: 'cover',
                                                        borderRadius: '4px',
                                                        border: '1px solid #e5e7eb'
                                                    }} />
                                                ))}
                                                {styleImages.length > 3 && (
                                                    <div style={{
                                                        width: '48px',
                                                        height: '48px',
                                                        borderRadius: '4px',
                                                        border: '1px solid #e5e7eb',
                                                        background: '#fafafa',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontSize: '12px',
                                                        color: '#787774',
                                                        fontWeight: 500
                                                    }}>
                                                        +{styleImages.length - 3}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0', borderBottom: '1px solid #e5e7eb' }}>
                                        <Clock size={24} color="#787774" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '4px' }}>Estimated Time</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737' }}>
                                                ~2-3 minutes
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '16px', padding: '20px 0' }}>
                                        <AlertCircle size={24} color="#787774" />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '13px', color: '#9b9a97', marginBottom: '4px' }}>Credit Cost</div>
                                            <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737' }}>
                                                This will use {selectedRows.size} of your 1,250 available credits
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div style={{
                        padding: '24px 40px',
                        borderTop: '1px solid #e5e7eb',
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
                                color: '#787774',
                                border: '1px solid #e5e7eb',
                                visibility: currentStep === 1 ? 'hidden' : 'visible'
                            }}
                        >
                            <ArrowLeft size={16} />
                            Previous
                        </button>
                        <button 
                            onClick={nextStep}
                            disabled={currentStep === 1 && !csvData.length}
                            style={{
                                padding: '10px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: 500,
                                cursor: (currentStep === 1 && !csvData.length) ? 'not-allowed' : 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: '#1a1a1a',
                                color: 'white',
                                border: '1px solid #1a1a1a',
                                opacity: (currentStep === 1 && !csvData.length) ? 0.5 : 1
                            }}
                        >
                            {currentStep === 4 ? (
                                <>
                                    Generate {selectedRows.size} Image{selectedRows.size !== 1 ? 's' : ''}
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
            `}</style>
        </>
    );
}
