import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Upload, X, Zap, ChevronDown, AlertCircle } from 'lucide-react';
import { useState, useRef, ChangeEvent, useEffect } from 'react';
import text from '@/routes/projects/wizards/text';

const formatOptions = [
    { value: '1:1', label: 'Square (1:1)' },
    { value: '4:5', label: 'Portrait (4:5)' },
    { value: '3:4', label: 'Portrait (3:4)' },
    { value: '2:3', label: 'Portrait (2:3)' },
    { value: '9:16', label: 'Portrait / Story (9:16)' },
    { value: '3:2', label: 'Landscape (3:2)' },
    { value: '4:3', label: 'Landscape (4:3)' },
    { value: '5:4', label: 'Landscape (5:4)' },
    { value: '2:1', label: 'Wide (2:1)' },
    { value: '16:9', label: 'Landscape (16:9)' },
    { value: '21:9', label: 'Cinematic (21:9)' },
    { value: '4:1', label: 'Banner (4:1)' },
];

export default function TextWizard() {
    const page = usePage<{ error?: string }>();
    const [currentStep, setCurrentStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [ideaDescription, setIdeaDescription] = useState('');
    const [selectedFormat, setSelectedFormat] = useState('1:1');
    const [styleImages, setStyleImages] = useState<string[]>([]);
    const [styleImageFiles, setStyleImageFiles] = useState<File[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showError, setShowError] = useState(false);

    const imageInputRef = useRef<HTMLInputElement>(null);

    // Show error message if present
    useEffect(() => {
        if (page.props.error) {
            console.error('[TextWizard] page error prop received:', page.props.error);
            setShowError(true);
            const timer = setTimeout(() => setShowError(false), 7000);
            return () => clearTimeout(timer);
        }
    }, [page.props.error]);

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

    // Navigation
    const mapFormat = (value: string): string => {
        return value || '1:1';
    };

    const nextStep = () => {
        if (currentStep < 3) {
            setCurrentStep(currentStep + 1);
        } else {
            // Submit to backend
            if (!projectName.trim() || !ideaDescription.trim() || isSubmitting) return;
            
            setIsSubmitting(true);
            
            const fd = new FormData();
            fd.append('project_name', projectName.trim());
            fd.append('idea_description', ideaDescription.trim());
            fd.append('format', mapFormat(selectedFormat));
            // reference images optional (max 5)
            styleImageFiles.slice(0, 5).forEach((f) => fd.append('reference_images[]', f));

            router.post(text.store.url(), fd, {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => {
                    console.error('[TextWizard] router.post error:', errors);
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            });
        }
    };

    const previousStep = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const canProceed = () => {
        if (currentStep === 1) return projectName.trim().length > 0;
        if (currentStep === 2) return ideaDescription.trim().length > 0;
        return true;
    };

    return (
        <>
            <Head title="Text Wizard" />

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
                        {showError && page.props.error && (
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
                                    {page.props.error}
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
                            {currentStep === 1 && 'Name Your Project'}
                            {currentStep === 2 && 'Describe Your Idea'}
                        </h1>
                        <p style={{
                            fontSize: '14px',
                            color: 'var(--color-muted-foreground)',
                            lineHeight: 1.5,
                            margin: 0
                        }}>
                            {currentStep === 1 && 'Give your project a descriptive name to help organize your work.'}
                            {currentStep === 2 && 'Tell us what you want to create.'}
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
                            {[1, 2].map((step) => (
                                <div 
                                    key={step}
                                    style={{
                                        flex: 1,
                                        height: '4px',
                                        background: step < currentStep ? 'var(--color-primary)' : step === currentStep ? 'hsl(var(--primary) / 0.4)' : 'var(--color-border)',
                                        borderRadius: '2px',
                                        transition: 'all 0.2s ease-out'
                                    }}
                                />
                            ))}
                        </div>
                        <div style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontSize: '12px',
                            color: 'var(--color-muted-foreground)',
                            fontWeight: 500
                        }}>
                            <span style={{ color: currentStep === 1 ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Project Name</span>
                            <span style={{ color: currentStep === 2 ? 'var(--color-foreground)' : 'var(--color-muted-foreground)' }}>Your Idea</span>
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
                                <div style={{ marginBottom: '8px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)', display: 'block', marginBottom: '8px' }}>
                                        Project Name *
                                    </label>
                                    <input 
                                        type="text"
                                        value={projectName}
                                        onChange={(e) => setProjectName(e.target.value)}
                                        placeholder="e.g., Summer Campaign 2025"
                                        style={{
                                            width: '100%',
                                            padding: '12px 16px',
                                            fontSize: '14px',
                                            border: '1px solid var(--color-border)',
                                            borderRadius: '8px',
                                            outline: 'none',
                                            transition: 'all 0.2s ease-out',
                                            background: 'var(--color-card)',
                                            color: 'var(--color-foreground)'
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

                        {/* Step 2: Describe Your Idea */}
                        {currentStep === 2 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{ marginBottom: '20px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)', display: 'block', marginBottom: '8px' }}>
                                        What do you want to create? *
                                    </label>
                                    <textarea 
                                        value={ideaDescription}
                                        onChange={(e) => setIdeaDescription(e.target.value)}
                                        placeholder="e.g., A vibrant social media post showcasing our new eco-friendly water bottle with nature elements in the background..."
                                        rows={8}
                                        style={{
                                            width: '100%',
                                            padding: '12px 16px',
                                            fontSize: '14px',
                                            border: '1px solid var(--color-border)',
                                            borderRadius: '8px',
                                            outline: 'none',
                                            transition: 'all 0.2s ease-out',
                                            fontFamily: 'inherit',
                                            resize: 'vertical',
                                            background: 'var(--color-card)',
                                            color: 'var(--color-foreground)'
                                        }}
                                        onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                        onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                    />
                                    <p style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', marginTop: '6px' }}>
                                        Be as detailed as possible - mention colors, style, mood, key elements
                                    </p>
                                </div>

                                <div style={{ marginBottom: '8px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)', display: 'block', marginBottom: '8px' }}>
                                        Output Aspect Ratio *
                                    </label>
                                    <div style={{ position: 'relative' }}>
                                        <select 
                                            value={selectedFormat}
                                            onChange={(e) => setSelectedFormat(e.target.value)}
                                            style={{
                                                width: '100%',
                                                padding: '12px 16px',
                                                fontSize: '14px',
                                                border: '1px solid var(--color-border)',
                                                borderRadius: '8px',
                                                outline: 'none',
                                                transition: 'all 0.2s ease-out',
                                                appearance: 'none',
                                                background: 'var(--color-card)',
                                                cursor: 'pointer',
                                                paddingRight: '40px',
                                                color: 'var(--color-foreground)'
                                            }}
                                            onFocus={(e) => e.target.style.borderColor = 'var(--color-primary)'}
                                            onBlur={(e) => e.target.style.borderColor = 'var(--color-border)'}
                                        >
                                            {formatOptions.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        <ChevronDown 
                                            size={16} 
                                            style={{ 
                                                position: 'absolute', 
                                                right: '16px', 
                                                top: '50%', 
                                                transform: 'translateY(-50%)',
                                                pointerEvents: 'none',
                                                color: 'var(--color-muted-foreground)'
                                            }} 
                                        />
                                    </div>
                                </div>

                                <div style={{
                                    background: 'var(--color-muted)',
                                    border: '1px solid var(--color-border)',
                                    borderRadius: '12px',
                                    padding: '20px',
                                    marginTop: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '8px' }}>
                                        Tips for better results:
                                    </p>
                                    <ul style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', lineHeight: 1.6, paddingLeft: '20px', margin: 0 }}>
                                        <li>Describe the visual style (minimalist, bold, elegant, etc.)</li>
                                        <li>Mention specific colors or color palettes</li>
                                        <li>Include key elements that must appear</li>
                                        <li>Describe the mood or feeling you want to convey</li>
                                    </ul>
                                </div>
                            </div>
                        )}

                        {/* Step 3: Optional References */}
                        {currentStep === 3 && (
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
                                        <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: 'var(--color-muted-foreground)', margin: '0 auto' }} />
                                    </div>
                                    <div style={{ fontSize: '16px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '8px' }}>
                                        Drag & drop reference images here, or click to upload
                                    </div>
                                    <div style={{ fontSize: '14px', color: 'var(--color-muted-foreground)' }}>
                                        Add style references to guide the AI (optional)
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

                                {styleImages.length > 0 && (
                                    <>
                                        <div style={{ fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)', marginBottom: '12px' }}>
                                            {styleImages.length} reference image{styleImages.length !== 1 ? 's' : ''} uploaded
                                        </div>
                                        <div style={{
                                            display: 'grid',
                                            gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
                                            gap: '12px',
                                            marginBottom: '20px'
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
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            removeImage(index);
                                                        }}
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
                                    </>
                                )}

                                <div style={{
                                    background: 'var(--color-muted)',
                                    border: '1px solid var(--color-border)',
                                    borderRadius: '12px',
                                    padding: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', margin: 0, lineHeight: 1.6 }}>
                                        Reference images help guide the visual style. If you have examples of the look and feel you want, upload them here. Otherwise, we'll generate based on your text description.
                                    </p>
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
                            disabled={!canProceed() || isSubmitting}
                            style={{
                                padding: '10px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: 500,
                                cursor: !canProceed() || isSubmitting ? 'not-allowed' : 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: 'var(--color-primary)',
                                color: 'var(--color-primary-foreground)',
                                border: '1px solid var(--color-primary)',
                                opacity: !canProceed() || isSubmitting ? 0.5 : 1
                            }}
                        >
                            {currentStep === 2 ? (
                                <>
                                    {isSubmitting ? 'Starting Generation...' : 'Generate (1 credit)'}
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
