import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, ArrowRight, Upload, X, Zap, Palette, Plus, Trash2 } from 'lucide-react';
import { useState, useRef, ChangeEvent, useEffect } from 'react';

const feelingSuggestions = [
    'Luxury', 'Modern', 'Minimalist', 'Bold', 'Playful', 'Organic',
    'Techy', 'Elegant', 'Youthful', 'Professional', 'Artisan', 'Futuristic',
];

export default function BrandKitWizard() {
    const page = usePage<{ error?: string }>();

    const [currentStep, setCurrentStep] = useState(1);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showError, setShowError] = useState(false);

    // Step 1 fields
    const [brandName, setBrandName] = useState('');
    const [industry, setIndustry] = useState('');
    const [description, setDescription] = useState('');
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    // Step 2 fields
    const [brandFeeling, setBrandFeeling] = useState('');
    const [colors, setColors] = useState<string[]>(['', '', '']);

    const logoInputRef  = useRef<HTMLInputElement>(null);
    const colorRef0     = useRef<HTMLInputElement>(null);
    const colorRef1     = useRef<HTMLInputElement>(null);
    const colorRef2     = useRef<HTMLInputElement>(null);
    const colorInputRefs = [colorRef0, colorRef1, colorRef2];

    useEffect(() => {
        if (page.props.error) {
            setShowError(true);
            const timer = setTimeout(() => setShowError(false), 7000);
            return () => clearTimeout(timer);
        }
    }, [page.props.error]);

    // ── Handlers ──────────────────────────────────────────────────────────────

    const handleLogoUpload = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setLogoFile(file);
        const reader = new FileReader();
        reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
        reader.readAsDataURL(file);
    };

    const removeLogo = () => {
        setLogoFile(null);
        setLogoPreview(null);
        if (logoInputRef.current) logoInputRef.current.value = '';
    };

    const handleColorChange = (index: number, value: string) => {
        const updated = [...colors];
        updated[index] = value;
        setColors(updated);
    };

    const toggleFeeling = (feeling: string) => {
        const lower = feeling.toLowerCase();
        if (brandFeeling.toLowerCase().includes(lower)) {
            setBrandFeeling(brandFeeling.replace(new RegExp(feeling, 'gi'), '').replace(/,\s*,/g, ',').replace(/^,\s*|,\s*$/g, '').trim());
        } else {
            setBrandFeeling(prev => prev ? `${prev}, ${feeling}` : feeling);
        }
    };

    // ── Navigation ────────────────────────────────────────────────────────────

    const canProceed = () => {
        if (currentStep === 1) {
            return brandName.trim().length > 0 && industry.trim().length > 0 && description.trim().length > 0;
        }
        if (currentStep === 2) {
            return brandFeeling.trim().length > 0;
        }
        return true;
    };

    const nextStep = () => {
        if (currentStep < 2) {
            setCurrentStep(currentStep + 1);
            return;
        }

        // Submit
        if (!canProceed() || isSubmitting) return;
        setIsSubmitting(true);

        const fd = new FormData();
        fd.append('brand_name', brandName.trim());
        fd.append('industry', industry.trim());
        fd.append('description', description.trim());
        fd.append('brand_feeling', brandFeeling.trim());
        colors.forEach((c, i) => {
            if (c && c.trim()) fd.append(`colors[${i}]`, c.trim());
        });
        if (logoFile) fd.append('logo', logoFile);

        router.post('/projects/wizards/brand-kit', fd, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setIsSubmitting(false),
        });
    };

    const previousStep = () => {
        if (currentStep > 1) setCurrentStep(currentStep - 1);
    };

    // ── Render ────────────────────────────────────────────────────────────────

    const stepLabels = ['Brand Basics', 'Style & Colors'];

    return (
        <>
            <Head title="Brand Kit Wizard" />

            {/* Loading overlay */}
            {isSubmitting && (
                <div style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    background: 'rgba(0,0,0,0.8)', zIndex: 9999,
                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                    backdropFilter: 'blur(4px)',
                }}>
                    <div style={{
                        background: 'var(--color-card)', borderRadius: '16px',
                        padding: '40px 48px', maxWidth: '420px', textAlign: 'center',
                        boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
                    }}>
                        <div style={{
                            width: '64px', height: '64px', margin: '0 auto 24px',
                            border: '4px solid var(--color-muted)',
                            borderTopColor: 'hsl(var(--primary))',
                            borderRadius: '50%', animation: 'spin 0.8s linear infinite',
                        }} />
                        <h3 style={{ fontSize: '20px', fontWeight: 600, color: 'var(--color-foreground)', marginBottom: '12px' }}>
                            Building Your Brand Kit…
                        </h3>
                        <p style={{ fontSize: '14px', color: 'var(--color-muted-foreground)', lineHeight: 1.6, margin: 0 }}>
                            AI is crafting your brand board. This may take 30–60 seconds. You'll be redirected once it's ready.
                        </p>
                    </div>
                </div>
            )}

            <div style={{
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif',
                background: 'var(--color-muted)', color: 'var(--color-foreground)',
                minHeight: '100vh', display: 'flex', alignItems: 'flex-start',
                justifyContent: 'center', padding: '40px 20px',
            }}>
                <div style={{
                    background: 'var(--color-card)', borderRadius: '12px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
                    maxWidth: '900px', width: '100%', overflow: 'hidden',
                }}>
                    {/* ── Header ── */}
                    <div style={{ padding: '32px 40px', borderBottom: '1px solid var(--color-border)' }}>
                        <Link href="/projects" style={{
                            display: 'inline-flex', alignItems: 'center', gap: '6px',
                            fontSize: '13px', color: 'var(--color-muted-foreground)',
                            textDecoration: 'none', marginBottom: '16px',
                        }}>
                            <ArrowLeft size={14} />
                            Back to Projects
                        </Link>

                        {/* Error alert */}
                        {showError && page.props.error && (
                            <div style={{
                                padding: '12px 16px', marginBottom: '16px',
                                backgroundColor: 'hsl(var(--destructive) / 0.1)',
                                border: '1px solid hsl(var(--destructive) / 0.3)',
                                borderRadius: '8px', display: 'flex', alignItems: 'center', gap: '12px',
                            }}>
                                <AlertCircle size={18} style={{ color: 'hsl(var(--destructive))', flexShrink: 0 }} />
                                <p style={{ margin: 0, fontSize: '14px', color: 'hsl(var(--destructive))', lineHeight: 1.5 }}>
                                    {page.props.error}
                                </p>
                                <button onClick={() => setShowError(false)} style={{
                                    marginLeft: 'auto', background: 'none', border: 'none',
                                    cursor: 'pointer', color: 'hsl(var(--destructive))', padding: '4px',
                                    display: 'flex', alignItems: 'center',
                                }}>
                                    <X size={16} />
                                </button>
                            </div>
                        )}

                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '6px' }}>
                            <div style={{
                                width: '36px', height: '36px', borderRadius: '8px',
                                background: 'hsl(var(--primary) / 0.1)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                            }}>
                                <Palette size={18} style={{ color: 'hsl(var(--primary))' }} />
                            </div>
                            <h1 style={{ fontSize: '24px', fontWeight: 600, color: 'var(--color-foreground)', margin: 0 }}>
                                Brand Kit Generator
                            </h1>
                        </div>
                        <p style={{ fontSize: '14px', color: 'var(--color-muted-foreground)', lineHeight: 1.5, margin: 0 }}>
                            {currentStep === 1 && 'Tell us about your brand. We\'ll use this to craft a professional brand board.'}
                            {currentStep === 2 && 'Define your brand\'s visual personality and color palette.'}
                        </p>
                    </div>

                    {/* ── Progress ── */}
                    <div style={{ padding: '20px 40px 0', borderBottom: '1px solid var(--color-border)' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '10px' }}>
                            {[1, 2].map((step) => (
                                <div key={step} style={{
                                    flex: 1, height: '4px',
                                    background: step < currentStep
                                        ? 'hsl(var(--primary))'
                                        : step === currentStep
                                            ? 'hsl(var(--primary) / 0.4)'
                                            : 'var(--color-border)',
                                    borderRadius: '2px', transition: 'all 0.25s ease-out',
                                }} />
                            ))}
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', paddingBottom: '16px' }}>
                            {stepLabels.map((label, i) => (
                                <span key={label} style={{
                                    fontSize: '12px', fontWeight: 500,
                                    color: i + 1 === currentStep ? 'var(--color-foreground)' : 'var(--color-muted-foreground)',
                                }}>
                                    {i + 1}. {label}
                                </span>
                            ))}
                        </div>
                    </div>

                    {/* ── Body ── */}
                    <div style={{ padding: '40px', minHeight: '440px' }}>

                        {/* ── Step 1: Brand Basics ── */}
                        {currentStep === 1 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px', marginBottom: '24px' }}>
                                    {/* Brand Name */}
                                    <div>
                                        <label style={labelStyle}>Brand Name *</label>
                                        <input
                                            type="text"
                                            value={brandName}
                                            onChange={e => setBrandName(e.target.value)}
                                            placeholder="e.g., Nova Coffee"
                                            style={inputStyle}
                                            onFocus={e => (e.target.style.borderColor = 'hsl(var(--primary))')}
                                            onBlur={e => (e.target.style.borderColor = 'var(--color-border)')}
                                        />
                                    </div>

                                    {/* Industry */}
                                    <div>
                                        <label style={labelStyle}>Industry / Niche *</label>
                                        <input
                                            type="text"
                                            value={industry}
                                            onChange={e => setIndustry(e.target.value)}
                                            placeholder="e.g., Specialty Coffee, Fintech, Apparel"
                                            style={inputStyle}
                                            onFocus={e => (e.target.style.borderColor = 'hsl(var(--primary))')}
                                            onBlur={e => (e.target.style.borderColor = 'var(--color-border)')}
                                        />
                                    </div>
                                </div>

                                {/* Description */}
                                <div style={{ marginBottom: '24px' }}>
                                    <label style={labelStyle}>Brand Description *</label>
                                    <textarea
                                        value={description}
                                        onChange={e => setDescription(e.target.value)}
                                        placeholder="Describe what your brand does, who it's for, and what makes it unique…"
                                        rows={5}
                                        style={{ ...inputStyle, resize: 'vertical', fontFamily: 'inherit' }}
                                        onFocus={e => (e.target.style.borderColor = 'hsl(var(--primary))')}
                                        onBlur={e => (e.target.style.borderColor = 'var(--color-border)')}
                                    />
                                    <p style={hintStyle}>The more detail you give, the better the brand kit will reflect your identity.</p>
                                </div>

                                {/* Logo Upload */}
                                <div>
                                    <label style={labelStyle}>Logo <span style={{ fontWeight: 400, color: 'var(--color-muted-foreground)' }}>(Optional)</span></label>
                                    <p style={{ ...hintStyle, marginBottom: '10px' }}>
                                        Upload your existing logo to include it in the brand board. If not provided, a text-based wordmark will be used instead.
                                    </p>

                                    {logoPreview ? (
                                        <div style={{
                                            display: 'flex', alignItems: 'center', gap: '16px',
                                            padding: '16px', border: '1px solid var(--color-border)',
                                            borderRadius: '10px', background: 'var(--color-muted)',
                                        }}>
                                            <img
                                                src={logoPreview}
                                                alt="Logo preview"
                                                style={{ width: '80px', height: '80px', objectFit: 'contain', borderRadius: '6px' }}
                                            />
                                            <div style={{ flex: 1 }}>
                                                <p style={{ margin: 0, fontSize: '14px', fontWeight: 500, color: 'var(--color-foreground)' }}>
                                                    {logoFile?.name}
                                                </p>
                                                <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-muted-foreground)' }}>
                                                    {logoFile ? (logoFile.size / 1024).toFixed(1) + ' KB' : ''}
                                                </p>
                                            </div>
                                            <button
                                                onClick={removeLogo}
                                                style={{
                                                    background: 'none', border: '1px solid var(--color-border)',
                                                    borderRadius: '6px', padding: '8px', cursor: 'pointer',
                                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    color: 'var(--color-muted-foreground)',
                                                }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            onClick={() => logoInputRef.current?.click()}
                                            style={{
                                                width: '100%', padding: '24px',
                                                border: '2px dashed var(--color-border)',
                                                borderRadius: '10px', cursor: 'pointer',
                                                background: 'transparent',
                                                display: 'flex', flexDirection: 'column',
                                                alignItems: 'center', gap: '10px',
                                                transition: 'all 0.2s ease',
                                                color: 'var(--color-muted-foreground)',
                                            }}
                                            onMouseEnter={e => {
                                                (e.currentTarget as HTMLButtonElement).style.borderColor = 'hsl(var(--primary))';
                                                (e.currentTarget as HTMLButtonElement).style.background = 'hsl(var(--primary) / 0.04)';
                                            }}
                                            onMouseLeave={e => {
                                                (e.currentTarget as HTMLButtonElement).style.borderColor = 'var(--color-border)';
                                                (e.currentTarget as HTMLButtonElement).style.background = 'transparent';
                                            }}
                                        >
                                            <Upload size={24} />
                                            <span style={{ fontSize: '14px', fontWeight: 500 }}>Click to upload logo</span>
                                            <span style={{ fontSize: '13px' }}>PNG, JPG, WebP - max 10 MB</span>
                                        </button>
                                    )}

                                    <input
                                        ref={logoInputRef}
                                        type="file"
                                        accept="image/jpeg,image/jpg,image/png,image/webp"
                                        onChange={handleLogoUpload}
                                        style={{ display: 'none' }}
                                    />
                                </div>
                            </div>
                        )}

                        {/* ── Step 2: Style & Colors ── */}
                        {currentStep === 2 && (
                            <div style={{ animation: 'fadeIn 0.3s ease' }}>

                                {/* Brand Feeling */}
                                <div style={{ marginBottom: '32px' }}>
                                    <label style={labelStyle}>Brand Feeling / Vibe *</label>
                                    <p style={{ ...hintStyle, marginBottom: '10px' }}>
                                        Describe the personality of your brand. You can type freely or click the suggestions below.
                                    </p>
                                    <input
                                        type="text"
                                        value={brandFeeling}
                                        onChange={e => setBrandFeeling(e.target.value)}
                                        placeholder="e.g., Luxury, modern, minimalist with a warm touch"
                                        style={inputStyle}
                                        onFocus={e => (e.target.style.borderColor = 'hsl(var(--primary))')}
                                        onBlur={e => (e.target.style.borderColor = 'var(--color-border)')}
                                    />

                                    {/* Suggestion chips */}
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '12px' }}>
                                        {feelingSuggestions.map(tag => {
                                            const active = brandFeeling.toLowerCase().includes(tag.toLowerCase());
                                            return (
                                                <button
                                                    key={tag}
                                                    onClick={() => toggleFeeling(tag)}
                                                    style={{
                                                        padding: '5px 14px',
                                                        borderRadius: '100px',
                                                        fontSize: '13px',
                                                        fontWeight: 500,
                                                        cursor: 'pointer',
                                                        border: active
                                                            ? '1.5px solid hsl(var(--primary))'
                                                            : '1.5px solid var(--color-border)',
                                                        background: active
                                                            ? 'hsl(var(--primary) / 0.1)'
                                                            : 'transparent',
                                                        color: active
                                                            ? 'hsl(var(--primary))'
                                                            : 'var(--color-muted-foreground)',
                                                        transition: 'all 0.15s ease',
                                                    }}
                                                >
                                                    {tag}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Brand Colors */}
                                <div>
                                    <label style={labelStyle}>
                                        Brand Colors <span style={{ fontWeight: 400, color: 'var(--color-muted-foreground)' }}>(Optional - up to 3)</span>
                                    </label>
                                    <p style={{ ...hintStyle, marginBottom: '16px' }}>
                                        Click a color swatch to open the picker, or type a hex code directly. Leave empty and AI will choose colors based on your brand.
                                    </p>

                                    <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
                                        {colors.map((color, index) => (
                                            <div key={index} style={{ display: 'flex', flexDirection: 'column', gap: '8px', alignItems: 'center' }}>
                                                <div style={{ position: 'relative' }}>
                                                    {/* Color swatch / picker trigger */}
                                                    <button
                                                        onClick={() => colorInputRefs[index].current?.click()}
                                                        style={{
                                                            width: '64px', height: '64px', borderRadius: '10px',
                                                            border: '2px solid var(--color-border)',
                                                            cursor: 'pointer',
                                                            background: color || 'var(--color-muted)',
                                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                            transition: 'border-color 0.15s ease',
                                                            position: 'relative', overflow: 'hidden',
                                                        }}
                                                        onMouseEnter={e => (e.currentTarget.style.borderColor = 'hsl(var(--primary))')}
                                                        onMouseLeave={e => (e.currentTarget.style.borderColor = 'var(--color-border)')}
                                                        title={`Pick color ${index + 1}`}
                                                    >
                                                        {!color && (
                                                            <Plus size={20} style={{ color: 'var(--color-muted-foreground)' }} />
                                                        )}
                                                    </button>

                                                    {/* Hidden native color input */}
                                                    <input
                                                        ref={colorInputRefs[index]}
                                                        type="color"
                                                        value={color || '#000000'}
                                                        onChange={e => handleColorChange(index, e.target.value)}
                                                        style={{ position: 'absolute', opacity: 0, width: 0, height: 0, top: 0, left: 0 }}
                                                    />

                                                    {/* Clear button */}
                                                    {color && (
                                                        <button
                                                            onClick={() => handleColorChange(index, '')}
                                                            style={{
                                                                position: 'absolute', top: '-6px', right: '-6px',
                                                                width: '18px', height: '18px', borderRadius: '50%',
                                                                background: 'var(--color-card)',
                                                                border: '1px solid var(--color-border)',
                                                                cursor: 'pointer', display: 'flex',
                                                                alignItems: 'center', justifyContent: 'center',
                                                                padding: 0,
                                                            }}
                                                        >
                                                            <X size={10} style={{ color: 'var(--color-muted-foreground)' }} />
                                                        </button>
                                                    )}
                                                </div>

                                                {/* Hex input */}
                                                <input
                                                    type="text"
                                                    value={color}
                                                    onChange={e => {
                                                        let v = e.target.value;
                                                        if (v && !v.startsWith('#')) v = '#' + v;
                                                        handleColorChange(index, v);
                                                    }}
                                                    placeholder="#000000"
                                                    maxLength={7}
                                                    style={{
                                                        width: '80px', padding: '6px 8px',
                                                        fontSize: '12px', textAlign: 'center',
                                                        border: '1px solid var(--color-border)',
                                                        borderRadius: '6px', outline: 'none',
                                                        background: 'var(--color-card)',
                                                        color: 'var(--color-foreground)',
                                                        fontFamily: 'monospace',
                                                    }}
                                                    onFocus={e => (e.target.style.borderColor = 'hsl(var(--primary))')}
                                                    onBlur={e => (e.target.style.borderColor = 'var(--color-border)')}
                                                />

                                                <span style={{ fontSize: '11px', color: 'var(--color-muted-foreground)', fontWeight: 500 }}>
                                                    Color {index + 1}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Summary card */}
                                <div style={{
                                    marginTop: '32px',
                                    padding: '20px 24px',
                                    background: 'var(--color-muted)',
                                    border: '1px solid var(--color-border)',
                                    borderRadius: '12px',
                                }}>
                                    <p style={{ fontSize: '13px', fontWeight: 600, color: 'var(--color-foreground)', margin: '0 0 12px' }}>
                                        What will be generated:
                                    </p>
                                    <ul style={{ fontSize: '13px', color: 'var(--color-muted-foreground)', lineHeight: 1.7, paddingLeft: '18px', margin: 0 }}>
                                        <li>Top module - brand name / logo on a solid background</li>
                                        <li>Typography panel - headline, body, alphabet preview</li>
                                        <li>Color palette swatches with labels and hex codes</li>
                                        <li>Lifestyle photography strip (4 images)</li>
                                        <li>Logo adaptability panel across 3 background tones</li>
                                    </ul>
                                    <p style={{ fontSize: '12px', color: 'var(--color-muted-foreground)', margin: '12px 0 0', fontStyle: 'italic' }}>
                                        All remaining style variables (typography, photography subjects, textures, inspirations) will be inferred by AI from your brand details.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* ── Footer ── */}
                    <div style={{
                        padding: '24px 40px',
                        borderTop: '1px solid var(--color-border)',
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    }}>
                        <button
                            onClick={previousStep}
                            style={{
                                padding: '10px 24px', borderRadius: '8px', fontSize: '14px', fontWeight: 500,
                                cursor: 'pointer', transition: 'all 0.2s ease-out',
                                display: 'inline-flex', alignItems: 'center', gap: '8px',
                                background: 'transparent', color: 'var(--color-muted-foreground)',
                                border: '1px solid var(--color-border)',
                                visibility: currentStep === 1 ? 'hidden' : 'visible',
                            }}
                        >
                            <ArrowLeft size={16} />
                            Previous
                        </button>

                        <button
                            onClick={nextStep}
                            disabled={!canProceed() || isSubmitting}
                            style={{
                                padding: '10px 28px', borderRadius: '8px', fontSize: '14px', fontWeight: 500,
                                cursor: !canProceed() || isSubmitting ? 'not-allowed' : 'pointer',
                                transition: 'all 0.2s ease-out',
                                display: 'inline-flex', alignItems: 'center', gap: '8px',
                                background: 'hsl(var(--primary))',
                                color: 'hsl(var(--primary-foreground))',
                                border: '1px solid hsl(var(--primary))',
                                opacity: !canProceed() || isSubmitting ? 0.5 : 1,
                            }}
                        >
                            {currentStep === 2 ? (
                                <>
                                    {isSubmitting ? 'Generating…' : 'Generate Brand Kit'}
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
                    from { opacity: 0; transform: translateY(10px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            `}</style>
        </>
    );
}

// ── Shared micro-styles ───────────────────────────────────────────────────────

const labelStyle: React.CSSProperties = {
    fontSize: '14px', fontWeight: 500,
    color: 'var(--color-foreground)',
    display: 'block', marginBottom: '8px',
};

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '11px 14px', fontSize: '14px',
    border: '1px solid var(--color-border)', borderRadius: '8px',
    outline: 'none', transition: 'border-color 0.15s ease',
    background: 'var(--color-card)', color: 'var(--color-foreground)',
    boxSizing: 'border-box',
};

const hintStyle: React.CSSProperties = {
    fontSize: '13px', color: 'var(--color-muted-foreground)',
    margin: '6px 0 0', lineHeight: 1.5,
};
