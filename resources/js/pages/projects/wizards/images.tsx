import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Upload, X, Zap } from 'lucide-react';
import { useState, useRef, DragEvent, ChangeEvent } from 'react';
import images from '@/routes/projects/wizards/images';

export default function ImagesWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [styleImages, setStyleImages] = useState<string[]>([]);
    const [styleImageFiles, setStyleImageFiles] = useState<File[]>([]);
    const [contentDescription, setContentDescription] = useState('');
    const [dragOver, setDragOver] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    
    const imageInputRef = useRef<HTMLInputElement>(null);

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
            Array.from(files).forEach(file => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    setStyleImages(prev => [...prev, e.target?.result as string]);
                };
                reader.readAsDataURL(file);
                setStyleImageFiles(prev => [...prev, file]);
            });
        }
    };

    // Navigation
    const submitToBackend = () => {
        if (projectName.trim().length === 0) return;
        if (styleImageFiles.length < 5) return;
        if (contentDescription.trim().length === 0) return;
        if (isSubmitting) return;

        setIsSubmitting(true);

        const fd = new FormData();
        fd.append('project_name', projectName.trim());
        fd.append('content_description', contentDescription.trim());
        // format optional; default handled server-side; leave out or send 'square'
        // fd.append('format', 'square');
        styleImageFiles.slice(0, 10).forEach((f) => fd.append('reference_images[]', f));

        router.post(images.store.url(), fd, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setIsSubmitting(false),
        });
    };

    const nextStep = () => {
        if (currentStep < 3) {
            setCurrentStep(currentStep + 1);
        } else {
            // Submit to backend
            submitToBackend();
        }
    };

    const previousStep = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const canProceed = () => {
        if (currentStep === 1) return projectName.trim().length > 0;
        if (currentStep === 2) return styleImages.length >= 5;
        if (currentStep === 3) return contentDescription.trim().length > 0;
        return false;
    };

    return (
        <>
            <Head title="Images Wizard" />
            
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
                            {currentStep === 1 && 'Name Your Project'}
                            {currentStep === 2 && 'Upload Brand References'}
                            {currentStep === 3 && 'Describe Your Content'}
                        </h1>
                        <p style={{
                            fontSize: '14px',
                            color: '#787774',
                            lineHeight: 1.5,
                            margin: 0
                        }}>
                            {currentStep === 1 && 'Give your project a descriptive name to help organize your work.'}
                            {currentStep === 2 && 'Upload 5-10 images that represent your brand style for AI analysis.'}
                            {currentStep === 3 && 'Describe what visual content you want to generate.'}
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
                            {[1, 2, 3].map((step) => (
                                <div 
                                    key={step}
                                    style={{
                                        flex: 1,
                                        height: '4px',
                                        background: step < currentStep ? '#1a1a1a' : step === currentStep ? '#e5e5e5' : '#e5e7eb',
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
                            color: '#9b9a97',
                            fontWeight: 500
                        }}>
                            <span style={{ color: currentStep === 1 ? '#373737' : '#9b9a97' }}>Project Name</span>
                            <span style={{ color: currentStep === 2 ? '#373737' : '#9b9a97' }}>References</span>
                            <span style={{ color: currentStep === 3 ? '#373737' : '#9b9a97' }}>Content</span>
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
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: '#373737', display: 'block', marginBottom: '8px' }}>
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
                                            border: '1px solid #e5e7eb',
                                            borderRadius: '8px',
                                            outline: 'none',
                                            transition: 'all 0.2s ease-out'
                                        }}
                                        onFocus={(e) => e.target.style.borderColor = '#1a1a1a'}
                                        onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                                    />
                                    <p style={{ fontSize: '13px', color: '#787774', marginTop: '6px' }}>
                                        Choose a name that helps you identify this project later
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Step 2: Reference Images */}
                        {currentStep === 2 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div 
                                    onClick={() => imageInputRef.current?.click()}
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
                                        borderColor: dragOver ? '#1a1a1a' : '#e5e7eb',
                                        marginBottom: '20px'
                                    }}
                                >
                                    <div style={{ marginBottom: '20px' }}>
                                        <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: '#9b9a97', margin: '0 auto' }} />
                                    </div>
                                    <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        Drag & drop your brand images here, or click to upload
                                    </div>
                                    <div style={{ fontSize: '14px', color: '#787774' }}>
                                        Upload 5-10 images (JPG, PNG, WebP)
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
                                        <div style={{ 
                                            fontSize: '14px', 
                                            fontWeight: 500, 
                                            color: styleImages.length < 5 ? '#dc2626' : '#373737', 
                                            marginBottom: '12px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '6px'
                                        }}>
                                            {styleImages.length} image{styleImages.length !== 1 ? 's' : ''} uploaded
                                            {styleImages.length < 5 && (
                                                <span style={{ fontSize: '13px', fontWeight: 400, color: '#dc2626' }}>
                                                    (minimum 5 required)
                                                </span>
                                            )}
                                        </div>
                                        <div style={{
                                            display: 'grid',
                                            gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
                                            gap: '12px'
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
                                    </>
                                )}

                                <div style={{
                                    background: '#F7F7F5',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '12px',
                                    padding: '20px',
                                    marginTop: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        What makes good reference images:
                                    </p>
                                    <ul style={{ fontSize: '13px', color: '#787774', lineHeight: 1.6, paddingLeft: '20px', margin: 0 }}>
                                        <li>Images that showcase your brand's visual identity</li>
                                        <li>High-quality files with consistent style</li>
                                        <li>Variety in layouts and compositions</li>
                                        <li>Examples of typography and color usage</li>
                                    </ul>
                                </div>
                            </div>
                        )}

                        {/* Step 3: Content Description */}
                        {currentStep === 3 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{ marginBottom: '8px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: '#373737', display: 'block', marginBottom: '8px' }}>
                                        What do you want to create? *
                                    </label>
                                    <textarea 
                                        value={contentDescription}
                                        onChange={(e) => setContentDescription(e.target.value)}
                                        placeholder="e.g., Social media posts for our summer sale campaign featuring our new product line..."
                                        rows={8}
                                        style={{
                                            width: '100%',
                                            padding: '12px 16px',
                                            fontSize: '14px',
                                            border: '1px solid #e5e7eb',
                                            borderRadius: '8px',
                                            outline: 'none',
                                            transition: 'all 0.2s ease-out',
                                            fontFamily: 'inherit',
                                            resize: 'vertical'
                                        }}
                                        onFocus={(e) => e.target.style.borderColor = '#1a1a1a'}
                                        onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                                    />
                                    <p style={{ fontSize: '13px', color: '#787774', marginTop: '6px' }}>
                                        Be specific about the type of content, purpose, and any key elements to include
                                    </p>
                                </div>

                                <div style={{
                                    background: '#F7F7F5',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '12px',
                                    padding: '20px',
                                    marginTop: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        Example descriptions:
                                    </p>
                                    <ul style={{ fontSize: '13px', color: '#787774', lineHeight: 1.6, paddingLeft: '20px', margin: 0 }}>
                                        <li>"Instagram posts promoting our new eco-friendly product line with nature themes"</li>
                                        <li>"LinkedIn banner showcasing our company's mission and team culture"</li>
                                        <li>"Facebook ads for our holiday sale with festive elements"</li>
                                    </ul>
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
                                background: '#1a1a1a',
                                color: 'white',
                                border: '1px solid #1a1a1a',
                                opacity: !canProceed() || isSubmitting ? 0.5 : 1
                            }}
                        >
                            {currentStep === 3 ? (
                                <>
                                    {isSubmitting ? 'Starting Generation...' : 'Start Generation'}
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
