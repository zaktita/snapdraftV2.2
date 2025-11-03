import { useState, useEffect, useRef } from 'react';
import { AlertCircle, Sparkles, AlertTriangle } from 'lucide-react';

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
                background: 'rgba(0, 0, 0, 0.5)',
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
                background: '#ffffff',
                border: '1px solid #ebebeb',
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
                            color: '#373737'
                        }}>
                            <Sparkles size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: '#373737',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: '#8a8a8a',
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
                            background: '#f7f7f7',
                            border: '1px solid #ebebeb',
                            borderRadius: '6px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            color: '#373737',
                            resize: 'vertical',
                            transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
                            outline: 'none'
                        }}
                        onFocus={(e) => {
                            e.target.style.border = '1.5px solid #1a1a1a';
                            e.target.style.boxShadow = '0 0 0 3px rgba(26, 26, 26, 0.05)';
                        }}
                        onBlur={(e) => {
                            e.target.style.border = '1px solid #ebebeb';
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
                            border: '1px solid #ebebeb',
                            color: '#373737'
                        }}
                        onMouseEnter={(e) => e.currentTarget.style.background = '#f7f7f7'}
                        onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        style={{
                            padding: '10px 20px',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '14px',
                            fontWeight: 500,
                            borderRadius: '6px',
                            cursor: 'pointer',
                            transition: 'all 0.2s ease',
                            outline: 'none',
                            background: '#1a1a1a',
                            color: '#ffffff',
                            border: '1px solid #1a1a1a'
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.background = '#000000';
                            e.currentTarget.style.borderColor = '#000000';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = '#1a1a1a';
                            e.currentTarget.style.borderColor = '#1a1a1a';
                        }}
                    >
                        Generate
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
        info: { icon: '#373737' },
        warning: { icon: '#f59e0b' },
        error: { icon: '#dc2626' }
    };

    const color = colors[type];

    return (
        <div 
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(0, 0, 0, 0.5)',
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
                background: '#ffffff',
                border: '1px solid #ebebeb',
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
                            color: '#373737',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: '#8a8a8a',
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
                            background: '#1a1a1a',
                            color: '#ffffff',
                            border: '1px solid #1a1a1a'
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.background = '#000000';
                            e.currentTarget.style.borderColor = '#000000';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = '#1a1a1a';
                            e.currentTarget.style.borderColor = '#1a1a1a';
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
                background: 'rgba(0, 0, 0, 0.5)',
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
                background: '#ffffff',
                border: '1px solid #ebebeb',
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
                            color: '#373737'
                        }}>
                            <AlertTriangle size={24} strokeWidth={2} />
                        </div>
                        <h3 style={{
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                            fontSize: '18px',
                            fontWeight: 600,
                            color: '#373737',
                            margin: 0
                        }}>{title}</h3>
                    </div>
                    <p style={{
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: '#8a8a8a',
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
                            border: '1px solid #ebebeb',
                            color: '#373737'
                        }}
                        onMouseEnter={(e) => e.currentTarget.style.background = '#f7f7f7'}
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
                            background: isDanger ? '#dc2626' : '#1a1a1a',
                            color: '#ffffff',
                            border: isDanger ? '1px solid #dc2626' : '1px solid #1a1a1a'
                        }}
                        onMouseEnter={(e) => {
                            if (isDanger) {
                                e.currentTarget.style.background = '#b91c1c';
                                e.currentTarget.style.borderColor = '#b91c1c';
                            } else {
                                e.currentTarget.style.background = '#000000';
                                e.currentTarget.style.borderColor = '#000000';
                            }
                        }}
                        onMouseLeave={(e) => {
                            if (isDanger) {
                                e.currentTarget.style.background = '#dc2626';
                                e.currentTarget.style.borderColor = '#dc2626';
                            } else {
                                e.currentTarget.style.background = '#1a1a1a';
                                e.currentTarget.style.borderColor = '#1a1a1a';
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
