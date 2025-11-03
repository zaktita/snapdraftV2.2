import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Upload, X, Zap, ChevronDown } from 'lucide-react';
import { useState, useRef, ChangeEvent } from 'react';

const formatOptions = [
    { value: 'instagram-post', label: 'Instagram Post (1080x1080)' },
    { value: 'instagram-story', label: 'Instagram Story (1080x1920)' },
    { value: 'facebook-post', label: 'Facebook Post (1200x630)' },
    { value: 'facebook-ad', label: 'Facebook Ad (1200x628)' },
    { value: 'linkedin-post', label: 'LinkedIn Post (1200x627)' },
    { value: 'linkedin-banner', label: 'LinkedIn Banner (1584x396)' },
    { value: 'twitter-post', label: 'Twitter Post (1200x675)' },
    { value: 'youtube-thumbnail', label: 'YouTube Thumbnail (1280x720)' },
];

export default function TextWizard() {
    const [currentStep, setCurrentStep] = useState(1);
    const [projectName, setProjectName] = useState('');
    const [ideaDescription, setIdeaDescription] = useState('');
    const [selectedFormat, setSelectedFormat] = useState('');
    const [styleImages, setStyleImages] = useState<string[]>([]);
    
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
        });
    };

    const removeImage = (index: number) => {
        setStyleImages(prev => prev.filter((_, i) => i !== index));
    };

    // Navigation
    const nextStep = () => {
        if (currentStep < 3) {
            setCurrentStep(currentStep + 1);
        } else {
            // Generate
            router.visit('/projects');
        }
    };

    const previousStep = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const canProceed = () => {
        if (currentStep === 1) return projectName.trim().length > 0;
        if (currentStep === 2) return ideaDescription.trim().length > 0 && selectedFormat.length > 0;
        return true; // Step 3 is optional
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
                            {currentStep === 2 && 'Describe Your Idea'}
                            {currentStep === 3 && 'Add Style References (Optional)'}
                        </h1>
                        <p style={{
                            fontSize: '14px',
                            color: '#787774',
                            lineHeight: 1.5,
                            margin: 0
                        }}>
                            {currentStep === 1 && 'Give your project a descriptive name to help organize your work.'}
                            {currentStep === 2 && 'Tell us what you want to create and select the output format.'}
                            {currentStep === 3 && 'Upload reference images to guide the visual style (optional).'}
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
                            <span style={{ color: currentStep === 2 ? '#373737' : '#9b9a97' }}>Your Idea</span>
                            <span style={{ color: currentStep === 3 ? '#373737' : '#9b9a97' }}>References</span>
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

                        {/* Step 2: Describe Your Idea */}
                        {currentStep === 2 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{ marginBottom: '20px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: '#373737', display: 'block', marginBottom: '8px' }}>
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
                                        Be as detailed as possible - mention colors, style, mood, key elements
                                    </p>
                                </div>

                                <div style={{ marginBottom: '8px' }}>
                                    <label style={{ fontSize: '14px', fontWeight: 500, color: '#373737', display: 'block', marginBottom: '8px' }}>
                                        Output Format *
                                    </label>
                                    <div style={{ position: 'relative' }}>
                                        <select 
                                            value={selectedFormat}
                                            onChange={(e) => setSelectedFormat(e.target.value)}
                                            style={{
                                                width: '100%',
                                                padding: '12px 16px',
                                                fontSize: '14px',
                                                border: '1px solid #e5e7eb',
                                                borderRadius: '8px',
                                                outline: 'none',
                                                transition: 'all 0.2s ease-out',
                                                appearance: 'none',
                                                background: 'white',
                                                cursor: 'pointer',
                                                paddingRight: '40px'
                                            }}
                                            onFocus={(e) => e.target.style.borderColor = '#1a1a1a'}
                                            onBlur={(e) => e.target.style.borderColor = '#e5e7eb'}
                                        >
                                            <option value="">Select a format...</option>
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
                                                color: '#787774'
                                            }} 
                                        />
                                    </div>
                                </div>

                                <div style={{
                                    background: '#F7F7F5',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '12px',
                                    padding: '20px',
                                    marginTop: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        Tips for better results:
                                    </p>
                                    <ul style={{ fontSize: '13px', color: '#787774', lineHeight: 1.6, paddingLeft: '20px', margin: 0 }}>
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
                                        <Upload style={{ width: '64px', height: '64px', strokeWidth: 1.5, color: '#9b9a97', margin: '0 auto' }} />
                                    </div>
                                    <div style={{ fontSize: '16px', fontWeight: 500, color: '#373737', marginBottom: '8px' }}>
                                        Drag & drop reference images here, or click to upload
                                    </div>
                                    <div style={{ fontSize: '14px', color: '#787774' }}>
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
                                        <div style={{ fontSize: '14px', fontWeight: 500, color: '#373737', marginBottom: '12px' }}>
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
                                    background: '#e0f2fe',
                                    border: '1px solid #bae6fd',
                                    borderRadius: '12px',
                                    padding: '20px'
                                }}>
                                    <p style={{ fontSize: '13px', color: '#0c4a6e', margin: 0, lineHeight: 1.6 }}>
                                        Reference images help guide the visual style. If you have examples of the look and feel you want, upload them here. Otherwise, we'll generate based on your text description.
                                    </p>
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
                            disabled={!canProceed()}
                            style={{
                                padding: '10px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: 500,
                                cursor: !canProceed() ? 'not-allowed' : 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '8px',
                                background: '#1a1a1a',
                                color: 'white',
                                border: '1px solid #1a1a1a',
                                opacity: !canProceed() ? 0.5 : 1
                            }}
                        >
                            {currentStep === 3 ? (
                                <>
                                    Start Generation
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
