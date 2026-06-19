import { CANVAS_CHROME, chromeBar, panelRowStyle, sectionLabelStyle } from '@/lib/canvas-editor-tokens';
import type { AnnotationPoint, EditHistoryEntry } from '@/lib/canvas-editor-types';
import { ChevronLeft, ChevronRight, History, Pencil, Trash2 } from 'lucide-react';

interface CanvasLeftPanelProps {
    collapsed: boolean;
    onToggleCollapse: () => void;
    annotations: AnnotationPoint[];
    selectedAnnotationId: number | null;
    selectedObjectId: number | null;
    editHistory: EditHistoryEntry[];
    selectedHistoryObjectId: number | null;
    onSelectAnnotation: (id: number) => void;
    onEditAnnotation: (id: number) => void;
    onDeleteAnnotation: (id: number) => void;
    onSelectHistoryEntry: (objectId: number) => void;
    isGenerating: boolean;
}

function formatRelativeTime(timestamp: number): string {
    const diff = Math.floor((Date.now() - timestamp) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

export function CanvasLeftPanel({
    collapsed,
    onToggleCollapse,
    annotations,
    selectedAnnotationId,
    selectedObjectId,
    editHistory,
    selectedHistoryObjectId,
    onSelectAnnotation,
    onEditAnnotation,
    onDeleteAnnotation,
    onSelectHistoryEntry,
    isGenerating,
}: CanvasLeftPanelProps) {
    const objectAnnotations = annotations
        .filter((a) => a.objectId === selectedObjectId)
        .sort((a, b) => a.number - b.number);

    const width = collapsed ? CANVAS_CHROME.PANEL_RAIL_WIDTH : CANVAS_CHROME.PANEL_WIDTH;

    if (collapsed) {
        return (
            <aside
                style={{
                    ...chromeBar,
                    width,
                    borderRight: '1px solid var(--color-border)',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    paddingTop: '8px',
                    flexShrink: 0,
                }}
            >
                <button
                    type="button"
                    title="Expand panel"
                    onClick={onToggleCollapse}
                    style={{
                        border: 'none',
                        background: 'transparent',
                        cursor: 'pointer',
                        padding: '8px',
                        color: 'var(--color-muted-foreground)',
                    }}
                >
                    <ChevronRight size={18} />
                </button>
                <History size={18} style={{ marginTop: '12px', color: 'var(--color-muted-foreground)' }} />
            </aside>
        );
    }

    return (
        <aside
            style={{
                ...chromeBar,
                width,
                borderRight: '1px solid var(--color-border)',
                display: 'flex',
                flexDirection: 'column',
                flexShrink: 0,
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '8px 8px 8px 12px',
                    borderBottom: '1px solid var(--color-border)',
                }}
            >
                <span style={{ fontSize: '12px', fontWeight: 600 }}>Edit history</span>
                <button
                    type="button"
                    title="Collapse panel"
                    onClick={onToggleCollapse}
                    style={{
                        border: 'none',
                        background: 'transparent',
                        cursor: 'pointer',
                        padding: '4px',
                        color: 'var(--color-muted-foreground)',
                    }}
                >
                    <ChevronLeft size={16} />
                </button>
            </div>

            <div style={{ flex: 1, overflowY: 'auto' }}>
                <div style={sectionLabelStyle()}>Current edits</div>
                {objectAnnotations.length === 0 ? (
                    <p
                        style={{
                            fontSize: '12px',
                            color: 'var(--color-muted-foreground)',
                            padding: '4px 12px 12px',
                            lineHeight: 1.5,
                            margin: 0,
                        }}
                    >
                        Use Edit mode to add markers on the image.
                    </p>
                ) : (
                    objectAnnotations.map((ann) => (
                        <div
                            key={ann.id}
                            style={{
                                ...panelRowStyle(ann.id === selectedAnnotationId),
                                paddingRight: '4px',
                            }}
                            onClick={() => onSelectAnnotation(ann.id)}
                        >
                            <span
                                style={{
                                    flexShrink: 0,
                                    width: '20px',
                                    height: '20px',
                                    borderRadius: '50%',
                                    background: '#F24822',
                                    color: 'white',
                                    fontSize: '10px',
                                    fontWeight: 700,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                {ann.number}
                            </span>
                            <span
                                style={{
                                    flex: 1,
                                    fontSize: '12px',
                                    color: 'var(--color-muted-foreground)',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {ann.description}
                            </span>
                            <button
                                type="button"
                                title="Edit"
                                disabled={isGenerating}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onEditAnnotation(ann.id);
                                }}
                                style={{
                                    border: 'none',
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    padding: '2px',
                                    color: 'var(--color-muted-foreground)',
                                }}
                            >
                                <Pencil size={12} />
                            </button>
                            <button
                                type="button"
                                title="Delete"
                                disabled={isGenerating}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onDeleteAnnotation(ann.id);
                                }}
                                style={{
                                    border: 'none',
                                    background: 'transparent',
                                    cursor: 'pointer',
                                    padding: '2px',
                                    color: 'var(--color-muted-foreground)',
                                }}
                            >
                                <Trash2 size={12} />
                            </button>
                        </div>
                    ))
                )}

                <div style={sectionLabelStyle()}>AI results</div>
                {editHistory.length === 0 ? (
                    <p
                        style={{
                            fontSize: '12px',
                            color: 'var(--color-muted-foreground)',
                            padding: '4px 12px 12px',
                            lineHeight: 1.5,
                            margin: 0,
                        }}
                    >
                        Applied edits, upscales, and resizes appear here.
                    </p>
                ) : (
                    editHistory.map((entry) => (
                        <div
                            key={entry.id}
                            style={panelRowStyle(entry.objectId === selectedHistoryObjectId)}
                            onClick={() => onSelectHistoryEntry(entry.objectId)}
                        >
                            <img
                                src={entry.thumbnailSrc}
                                alt=""
                                style={{
                                    width: '32px',
                                    height: '32px',
                                    objectFit: 'cover',
                                    borderRadius: '4px',
                                    flexShrink: 0,
                                    background: 'var(--color-muted)',
                                }}
                            />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div
                                    style={{
                                        fontSize: '12px',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {entry.label}
                                </div>
                                <div style={{ fontSize: '10px', color: 'var(--color-muted-foreground)' }}>
                                    {formatRelativeTime(entry.timestamp)}
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </aside>
    );
}
