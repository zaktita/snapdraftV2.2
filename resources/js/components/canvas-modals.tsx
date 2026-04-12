import { useState, useEffect, useRef } from 'react';
import { AlertCircle, Sparkles, AlertTriangle, Maximize2, Zap } from 'lucide-react';

interface PromptModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (value: string) => void;
    title: string;
    description: string;
    placeholder: string;
    defaultValue?: string;
}

export function PromptModal({
    isOpen,
    onClose,
    onSubmit,
    title,
    description,
    placeholder,
    defaultValue = ''
}: PromptModalProps) {
    const [value, setValue] = useState(defaultValue);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (isOpen) {
            setValue(defaultValue);
            setTimeout(() => {
                textareaRef.current?.focus();
                textareaRef.current?.select();
            }, 100);
        }
    }, [isOpen, defaultValue]);

    const handleSubmit = () => {
        const trimmed = value.trim();
        if (trimmed) {
            onSubmit(trimmed);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            handleSubmit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
        }
    };

    if (!isOpen) return null;

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                background: 'color-mix(in oklab, var(--color-background), black 50%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 10000,
                opacity: 0,
                animation: 'fadeIn 0.2s ease forwards'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div style={{
                background: 'var(--color-card)',
                border: '1px solid var(--color-border)',
                borderRadius: '12px',
                padding: '24px',
                width: '90%',
                maxWidth: '520px',
                boxShadow: '0 8px 24px rgba(0, 0, 0, 0.1)',
                animation: 'modalSlideIn 0.3s ease forwards'
            }}>
                {/* Header */}
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '8px' }}>
                        <div style={{
                            fontSize: '24px',
                            lineHeight: 1,
                            color: 'var(--color-foreground)'
                        }}>
                            <Sparkles size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: 'var(--color-foreground)',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: 'var(--color-muted-foreground)',
                        margin: 0,
                        lineHeight: 1.5
                    }}>{description}</p>
                </div>

                {/* Body */}
                <div style={{ marginBottom: '20px' }}>
                    <textarea
                        ref={textareaRef}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={placeholder}
                        style={{
                            width: '100%',
                            minHeight: '120px',
                            padding: '12px',
                            background: 'var(--color-muted)',
                            border: '1px solid var(--color-border)',
                            borderRadius: '6px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            color: 'var(--color-foreground)',
                            resize: 'vertical',
                            transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
                            outline: 'none'
                        }}
                        onFocus={(e) => {
                            e.target.style.border = '1.5px solid var(--color-primary)';
                            e.target.style.boxShadow = '0 0 0 3px color-mix(in oklab, var(--color-primary), transparent 95%)';
                        }}
                        onBlur={(e) => {
                            e.target.style.border = '1px solid var(--color-border)';
                            e.target.style.boxShadow = 'none';
                        }}
                    />
                </div>

                {/* Footer */}
                <div style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    gap: '12px'
                }}>
                    <button
                        onClick={onClose}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: 'transparent',
                            border: '1px solid var(--color-border)',
                            color: 'var(--color-foreground)'
                        }}
                        onMouseEnter={(e) => e.currentTarget.style.background = 'var(--color-muted)'}
                        onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        style={{
                            padding: '10px 24px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '8px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease-out',
                            outline: 'none',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '8px',
                            background: 'var(--color-primary)',
                            color: 'var(--color-primary-foreground)',
                            border: '1px solid var(--color-primary)',
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.background = 'color-mix(in oklab, var(--color-primary), black 10%)';
                            e.currentTarget.style.borderColor = 'color-mix(in oklab, var(--color-primary), black 10%)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'var(--color-primary)';
                            e.currentTarget.style.borderColor = 'var(--color-primary)';
                        }}
                    >
                        Generate (1 credit)
                        <Zap size={16} />
                    </button>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn {
                    to { opacity: 1; }
                }
                @keyframes modalSlideIn {
                    from { 
                        opacity: 0;
                        transform: scale(0.95) translateY(10px);
                    }
                    to { 
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }
            `}</style>
        </div>
    );
}

interface AlertModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    message: string;
    type?: 'info' | 'warning' | 'error';
}

export function AlertModal({ isOpen, onClose, title, message, type = 'info' }: AlertModalProps) {
    useEffect(() => {
        if (isOpen) {
            const handleEsc = (e: KeyboardEvent) => {
                if (e.key === 'Escape') onClose();
            };
            window.addEventListener('keydown', handleEsc);
            return () => window.removeEventListener('keydown', handleEsc);
        }
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const colors = {
        info: { icon: 'var(--color-foreground)' },
        warning: { icon: 'var(--color-accent)' },
        error: { icon: 'var(--color-destructive)' }
    };

    const color = colors[type];

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                background: 'color-mix(in oklab, var(--color-background), black 50%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 10000,
                opacity: 0,
                animation: 'fadeIn 0.2s ease forwards'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div style={{
                background: 'var(--color-card)',
                border: '1px solid var(--color-border)',
                borderRadius: '12px',
                padding: '24px',
                width: '90%',
                maxWidth: '400px',
                boxShadow: '0 8px 24px rgba(0, 0, 0, 0.1)',
                animation: 'modalSlideIn 0.3s ease forwards'
            }}>
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '8px' }}>
                        <div style={{
                            fontSize: '24px',
                            lineHeight: 1,
                            color: color.icon
                        }}>
                            <AlertCircle size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: 'var(--color-foreground)',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: 'var(--color-muted-foreground)',
                        margin: 0,
                        lineHeight: 1.5
                    }}>{message}</p>
                </div>

                <div style={{
                    display: 'flex',
                    justifyContent: 'flex-end'
                }}>
                    <button
                        onClick={onClose}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: 'var(--color-primary)',
                            color: 'var(--color-primary-foreground)',
                            border: '1px solid var(--color-primary)'
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.background = 'color-mix(in oklab, var(--color-primary), black 10%)';
                            e.currentTarget.style.borderColor = 'color-mix(in oklab, var(--color-primary), black 10%)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'var(--color-primary)';
                            e.currentTarget.style.borderColor = 'var(--color-primary)';
                        }}
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>
    );
}

interface ConfirmModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    isDanger?: boolean;
}

export function ConfirmModal({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    isDanger = false
}: ConfirmModalProps) {
    useEffect(() => {
        if (isOpen) {
            const handleEsc = (e: KeyboardEvent) => {
                if (e.key === 'Escape') onClose();
            };
            window.addEventListener('keydown', handleEsc);
            return () => window.removeEventListener('keydown', handleEsc);
        }
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                background: 'color-mix(in oklab, var(--color-background), black 50%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 10000,
                opacity: 0,
                animation: 'fadeIn 0.2s ease forwards'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div style={{
                background: 'var(--color-card)',
                border: '1px solid var(--color-border)',
                borderRadius: '12px',
                padding: '24px',
                width: '90%',
                maxWidth: '400px',
                boxShadow: '0 8px 24px rgba(0, 0, 0, 0.1)',
                animation: 'modalSlideIn 0.3s ease forwards'
            }}>
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '8px' }}>
                        <div style={{
                            fontSize: '24px',
                            lineHeight: 1,
                            color: 'var(--color-foreground)'
                        }}>
                            <AlertTriangle size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: 'var(--color-foreground)',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: 'var(--color-muted-foreground)',
                        margin: 0,
                        lineHeight: 1.5
                    }}>{message}</p>
                </div>

                <div style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    gap: '12px'
                }}>
                    <button
                        onClick={onClose}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: 'transparent',
                            border: '1px solid var(--color-border)',
                            color: 'var(--color-foreground)'
                        }}
                        onMouseEnter={(e) => e.currentTarget.style.background = 'var(--color-muted)'}
                        onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
                    >
                        {cancelText}
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: isDanger ? 'var(--color-destructive)' : 'var(--color-primary)',
                            color: 'var(--color-primary-foreground)',
                            border: isDanger ? '1px solid var(--color-destructive)' : '1px solid var(--color-primary)'
                        }}
                        onMouseEnter={(e) => {
                            if (isDanger) {
                                e.currentTarget.style.background = 'color-mix(in oklab, var(--color-destructive), black 10%)';
                                e.currentTarget.style.borderColor = 'color-mix(in oklab, var(--color-destructive), black 10%)';
                            } else {
                                e.currentTarget.style.background = 'color-mix(in oklab, var(--color-primary), black 10%)';
                                e.currentTarget.style.borderColor = 'color-mix(in oklab, var(--color-primary), black 10%)';
                            }
                        }}
                        onMouseLeave={(e) => {
                            if (isDanger) {
                                e.currentTarget.style.background = 'var(--color-destructive)';
                                e.currentTarget.style.borderColor = 'var(--color-destructive)';
                            } else {
                                e.currentTarget.style.background = 'var(--color-primary)';
                                e.currentTarget.style.borderColor = 'var(--color-primary)';
                            }
                        }}
                    >
                        {confirmText}
                    </button>
                </div>
            </div>
        </div>
    );
}

interface UpscaleModalProps {
    isOpen: boolean;
    selectedFactor: number;
    onClose: () => void;
    onSelectFactor: (factor: number) => void;
    onConfirm: () => void;
    isLoading?: boolean;
}

export function UpscaleModal({
    isOpen,
    selectedFactor,
    onClose,
    onSelectFactor,
    onConfirm,
    isLoading = false
}: UpscaleModalProps) {
    if (!isOpen) return null;

    return (
        <div
            style={{
                position: 'fixed',
                inset: 0,
                background: 'color-mix(in oklab, var(--color-background), black 50%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 10000,
                opacity: 0,
                animation: 'fadeIn 0.2s ease forwards'
            }}
            onClick={(e) => e.target === e.currentTarget && !isLoading && onClose()}
        >
            <div style={{
                background: 'var(--color-card)',
                border: '1px solid var(--color-border)',
                borderRadius: '12px',
                padding: '24px',
                width: '90%',
                maxWidth: '520px',
                boxShadow: '0 8px 24px rgba(0, 0, 0, 0.1)',
                animation: 'modalSlideIn 0.3s ease forwards'
            }}>
                {/* Header */}
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '8px' }}>
                        <div style={{
                            fontSize: '24px',
                            lineHeight: 1,
                            color: 'var(--color-foreground)'
                        }}>
                            <Maximize2 size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: 'var(--color-foreground)',
                            margin: 0
                        }}>Upscale Image</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: 'var(--color-muted-foreground)',
                        margin: 0,
                        lineHeight: 1.5
                    }}>Select the upscale factor. Higher factors increase image quality but cost more credits.</p>
                </div>

                {/* Factor Buttons */}
                <div style={{
                    display: 'flex',
                    gap: '12px',
                    marginBottom: '24px'
                }}>
                    {[2, 4].map((factor) => {
                        const creditCost = factor === 2 ? 1 : 2;
                        return (
                            <button
                                key={factor}
                                onClick={() => onSelectFactor(factor)}
                                disabled={isLoading}
                                style={{
                                    flex: 1,
                                    padding: '14px 16px',
                                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                                    fontSize: '14px',
                                    fontWeight: selectedFactor === factor ? 600 : 500,
                                    borderRadius: '8px',
                                    cursor: isLoading ? 'not-allowed' : 'pointer',
                                    transition: 'all 0.2s ease',
                                    outline: 'none',
                                    border: '1.5px solid',
                                    background: selectedFactor === factor ? 'var(--color-primary)' : 'transparent',
                                    borderColor: selectedFactor === factor ? 'var(--color-primary)' : 'var(--color-border)',
                                    color: selectedFactor === factor ? 'var(--color-primary-foreground)' : 'var(--color-foreground)',
                                    opacity: isLoading ? 0.5 : 1,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: '6px',
                                    boxShadow: selectedFactor === factor ? '0 0 0 3px color-mix(in oklab, var(--color-primary), transparent 85%)' : 'none'
                                }}
                                onMouseEnter={(e) => {
                                    if (!isLoading && selectedFactor !== factor) {
                                        e.currentTarget.style.borderColor = 'var(--color-primary)';
                                        e.currentTarget.style.background = 'color-mix(in oklab, var(--color-primary), transparent 95%)';
                                    }
                                }}
                                onMouseLeave={(e) => {
                                    if (selectedFactor !== factor) {
                                        e.currentTarget.style.background = 'transparent';
                                        e.currentTarget.style.borderColor = 'var(--color-border)';
                                    }
                                }}
                            >
                                <span style={{ fontSize: '16px', fontWeight: 600 }}>{factor}x</span>
                                <span style={{
                                    fontSize: '12px',
                                    opacity: 0.8,
                                    fontWeight: 400
                                }}>
                                    {creditCost} {creditCost === 1 ? 'credit' : 'credits'}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {/* Action Buttons */}
                <div style={{
                    display: 'flex',
                    gap: '12px',
                    justifyContent: 'flex-end'
                }}>
                    <button
                        onClick={onClose}
                        disabled={isLoading}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: isLoading ? 'not-allowed' : 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: 'transparent',
                            border: '1px solid var(--color-border)',
                            color: 'var(--color-foreground)',
                            opacity: isLoading ? 0.5 : 1
                        }}
                        onMouseEnter={(e) => {
                            if (!isLoading) e.currentTarget.style.background = 'var(--color-muted)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'transparent';
                        }}
                    >
                        Cancel
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={isLoading}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: isLoading ? 'not-allowed' : 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: 'var(--color-primary)',
                            color: 'var(--color-primary-foreground)',
                            border: '1px solid var(--color-primary)',
                            opacity: isLoading ? 0.5 : 1
                        }}
                        onMouseEnter={(e) => {
                            if (!isLoading) {
                                e.currentTarget.style.background = 'color-mix(in oklab, var(--color-primary), black 10%)';
                                e.currentTarget.style.borderColor = 'color-mix(in oklab, var(--color-primary), black 10%)';
                            }
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'var(--color-primary)';
                            e.currentTarget.style.borderColor = 'var(--color-primary)';
                        }}
                    >
                        {isLoading ? 'Upscaling...' : `Upscale (${selectedFactor === 2 ? 1 : 2} ${selectedFactor === 2 ? 'credit' : 'credits'})`}
                    </button>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn {
                    to { opacity: 1; }
                }
                @keyframes modalSlideIn {
                    from { 
                        opacity: 0;
                        transform: scale(0.95) translateY(10px);
                    }
                    to { 
                        opacity: 1;
                        transform: scale(1) translateY(0);
                    }
                }
            `}</style>
        </div>
    );
}
