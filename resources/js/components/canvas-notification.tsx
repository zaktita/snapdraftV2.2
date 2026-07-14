import { CANVAS_COLORS } from '@/lib/canvas-editor-tokens';
import { Check } from 'lucide-react';
import { useEffect } from 'react';

interface CanvasNotificationProps {
    message: string;
    visible: boolean;
    onDismiss: () => void;
    durationMs?: number;
}

/** Minimal dark pill toast matching the canvas comment bar. */
export function CanvasNotification({
    message,
    visible,
    onDismiss,
    durationMs = 2800,
}: CanvasNotificationProps) {
    useEffect(() => {
        if (!visible) return;
        const timer = setTimeout(onDismiss, durationMs);
        return () => clearTimeout(timer);
    }, [visible, durationMs, onDismiss]);

    if (!visible) return null;

    return (
        <div
            style={{
                position: 'absolute',
                top: 16,
                left: '50%',
                transform: 'translateX(-50%)',
                zIndex: 90,
                pointerEvents: 'none',
            }}
        >
            <div
                role="status"
                aria-live="polite"
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    background: CANVAS_COLORS.commentInputBg,
                    borderRadius: '999px',
                    padding: '10px 18px',
                    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.35)',
                    border: `1px solid ${CANVAS_COLORS.toolbarBorder}`,
                    animation: 'canvasNotifyIn 0.2s ease forwards',
                }}
            >
                <Check
                    size={16}
                    strokeWidth={2.25}
                    color={CANVAS_COLORS.figmaBlue}
                    aria-hidden
                />
                <span
                    style={{
                        fontSize: '13px',
                        fontWeight: 500,
                        color: 'rgba(255, 255, 255, 0.85)',
                        whiteSpace: 'nowrap',
                    }}
                >
                    {message}
                </span>
            </div>
            <style>{`
                @keyframes canvasNotifyIn {
                    from {
                        opacity: 0;
                        transform: translateY(-6px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `}</style>
        </div>
    );
}
