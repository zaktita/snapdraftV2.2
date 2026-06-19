import { CANVAS_COLORS } from '@/lib/canvas-editor-tokens';
import { ArrowUp, Wand2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface CanvasInlineCommentProps {
    screenX: number;
    screenY: number;
    defaultValue?: string;
    markerNumber?: number;
    onSubmit: (text: string) => void;
    onCancel: () => void;
}

export function CanvasInlineComment({
    screenX,
    screenY,
    defaultValue = '',
    onSubmit,
    onCancel,
}: CanvasInlineCommentProps) {
    const [value, setValue] = useState(defaultValue);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setValue(defaultValue);
        setTimeout(() => {
            inputRef.current?.focus();
            inputRef.current?.select();
        }, 50);
    }, [defaultValue, screenX, screenY]);

    const submit = () => {
        const trimmed = value.trim();
        if (trimmed) onSubmit(trimmed);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            submit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            onCancel();
        }
    };

    const canSubmit = value.trim().length > 0;

    return (
        <>
            <style>{`
                .canvas-comment-input::placeholder {
                    color: ${CANVAS_COLORS.commentPlaceholder};
                }
            `}</style>
            <div
                style={{
                    position: 'absolute',
                    left: screenX,
                    top: screenY,
                    transform: 'translate(-8px, -50%)',
                    zIndex: 80,
                    pointerEvents: 'auto',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                }}
                onMouseDown={(e) => e.stopPropagation()}
            >
                <Wand2
                    size={22}
                    strokeWidth={1.75}
                    color={CANVAS_COLORS.toolbarActive}
                    aria-hidden
                />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    background: CANVAS_COLORS.commentInputBg,
                    borderRadius: '999px',
                    padding: '6px 6px 6px 16px',
                    minWidth: '280px',
                    maxWidth: '360px',
                    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.35)',
                    border: `1px solid ${CANVAS_COLORS.toolbarBorder}`,
                }}
            >
                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Add a comment"
                    className="canvas-comment-input"
                    style={{
                        flex: 1,
                        border: 'none',
                        background: 'transparent',
                        outline: 'none',
                        fontSize: '13px',
                        color: '#ffffff',
                        fontFamily: 'inherit',
                        minWidth: 0,
                    }}
                />
                <button
                    type="button"
                    title="Place marker"
                    disabled={!canSubmit}
                    onClick={submit}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        width: '28px',
                        height: '28px',
                        borderRadius: '50%',
                        border: 'none',
                        flexShrink: 0,
                        background: canSubmit ? CANVAS_COLORS.toolbarActive : CANVAS_COLORS.commentSendBg,
                        color: canSubmit ? '#fff' : CANVAS_COLORS.commentSendIcon,
                        cursor: canSubmit ? 'pointer' : 'default',
                        transition: 'background 0.15s ease',
                    }}
                >
                    <ArrowUp size={14} strokeWidth={2.5} />
                </button>
            </div>
            </div>
        </>
    );
}
