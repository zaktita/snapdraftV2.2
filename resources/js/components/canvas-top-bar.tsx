import { CANVAS_CHROME, chromeBar } from '@/lib/canvas-editor-tokens';
import { ArrowLeft, Download, Maximize2, Minus, Plus, Save } from 'lucide-react';

interface CanvasTopBarProps {
    projectTitle: string;
    projectId?: number;
    scale: number;
    onBack: () => void;
    onZoomIn: () => void;
    onZoomOut: () => void;
    onResetZoom: () => void;
    onFitToScreen: () => void;
    onSave: () => void;
    onDownload: () => void;
    isGenerating: boolean;
}

export function CanvasTopBar({
    projectTitle,
    projectId,
    scale,
    onBack,
    onZoomIn,
    onZoomOut,
    onResetZoom,
    onFitToScreen,
    onSave,
    onDownload,
    isGenerating,
}: CanvasTopBarProps) {
    const iconBtn: React.CSSProperties = {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '28px',
        height: '28px',
        border: 'none',
        borderRadius: '4px',
        background: 'transparent',
        cursor: 'pointer',
        color: 'var(--color-muted-foreground)',
    };

    const ghostBtn: React.CSSProperties = {
        display: 'flex',
        alignItems: 'center',
        gap: '6px',
        height: '28px',
        padding: '0 10px',
        border: '1px solid var(--color-border)',
        borderRadius: '6px',
        background: 'transparent',
        cursor: 'pointer',
        fontSize: '12px',
        color: 'var(--color-foreground)',
    };

    return (
        <header
            style={{
                ...chromeBar,
                height: CANVAS_CHROME.TOP_BAR_HEIGHT,
                borderBottom: '1px solid var(--color-border)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                padding: '0 12px',
                flexShrink: 0,
                zIndex: 100,
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', minWidth: 0 }}>
                <button
                    type="button"
                    title={projectId ? 'Back to project' : 'Back to projects'}
                    style={iconBtn}
                    onClick={onBack}
                >
                    <ArrowLeft size={16} />
                </button>
                <span
                    style={{
                        fontSize: '13px',
                        fontWeight: 600,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        maxWidth: '200px',
                    }}
                >
                    {projectTitle}
                </span>
            </div>

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '2px',
                    padding: '2px 4px',
                    border: '1px solid var(--color-border)',
                    borderRadius: '6px',
                    background: 'var(--color-background)',
                }}
            >
                <button type="button" style={iconBtn} onClick={onZoomOut} title="Zoom out">
                    <Minus size={14} />
                </button>
                <button
                    type="button"
                    onClick={onResetZoom}
                    title="Reset zoom"
                    style={{
                        border: 'none',
                        background: 'transparent',
                        cursor: 'pointer',
                        fontSize: '12px',
                        color: 'var(--color-muted-foreground)',
                        minWidth: '44px',
                        padding: '0 4px',
                    }}
                >
                    {Math.round(scale * 100)}%
                </button>
                <button type="button" style={iconBtn} onClick={onZoomIn} title="Zoom in">
                    <Plus size={14} />
                </button>
                <button type="button" style={iconBtn} onClick={onFitToScreen} title="Fit to screen">
                    <Maximize2 size={14} />
                </button>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <button
                    type="button"
                    style={ghostBtn}
                    disabled={isGenerating}
                    onClick={onDownload}
                >
                    <Download size={14} />
                    <span>Download</span>
                </button>
                <button
                    type="button"
                    style={{
                        ...ghostBtn,
                        background: 'var(--color-primary)',
                        color: 'var(--color-primary-foreground)',
                        borderColor: 'var(--color-primary)',
                    }}
                    disabled={isGenerating}
                    onClick={onSave}
                >
                    <Save size={14} />
                    <span>Save</span>
                </button>
            </div>
        </header>
    );
}
