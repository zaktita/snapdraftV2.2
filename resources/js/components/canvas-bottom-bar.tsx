import {
    ASPECT_RATIO_PRESETS,
    bottomBarStyle,
    CANVAS_COLORS,
    figmaPopoverItemStyle,
    figmaPopoverStyle,
    figmaToolSlotStyle,
    toolbarDividerStyle,
} from '@/lib/canvas-editor-tokens';
import type { CanvasTool } from '@/lib/canvas-editor-types';
import {
    ChevronDown,
    Crop,
    MousePointer2,
    Wand2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface CanvasBottomBarProps {
    currentTool: CanvasTool;
    onSelectTool: (tool: CanvasTool) => void;
    pinCount: number;
    onApply: () => void;
    onUpscale: (factor: number) => void;
    onResize: (aspectRatio: string) => void;
    isGenerating: boolean;
    hasSelection: boolean;
}

type OpenPopover = 'upscale' | 'resize' | null;

function HdRectangleIcon({ active }: { active?: boolean }) {
    const color = active ? CANVAS_COLORS.toolbarActiveText : CANVAS_COLORS.toolbarIcon;

    return (
        <svg
            width="18"
            height="18"
            viewBox="0 0 18 18"
            fill="none"
            aria-hidden
        >
            <rect
                x="1.5"
                y="3"
                width="15"
                height="12"
                rx="2"
                stroke={color}
                strokeWidth="1.5"
            />
            <text
                x="9"
                y="11.5"
                textAnchor="middle"
                fill={color}
                fontSize="6.5"
                fontWeight="700"
                fontFamily="system-ui, sans-serif"
                letterSpacing="-0.2"
            >
                HD
            </text>
        </svg>
    );
}

function ToolSlot({
    active,
    disabled,
    wide,
    title,
    onClick,
    children,
    showChevron,
}: {
    active: boolean;
    disabled?: boolean;
    wide?: boolean;
    title: string;
    onClick: () => void;
    children: React.ReactNode;
    showChevron?: boolean;
}) {
    return (
        <button
            type="button"
            title={title}
            style={figmaToolSlotStyle(active, disabled, wide ?? active)}
            disabled={disabled}
            onClick={onClick}
            onMouseEnter={(e) => {
                if (!active && !disabled) {
                    e.currentTarget.style.background = 'rgba(255, 255, 255, 0.08)';
                }
            }}
            onMouseLeave={(e) => {
                if (!active) {
                    e.currentTarget.style.background = 'transparent';
                }
            }}
        >
            {children}
            {showChevron && (
                <ChevronDown
                    size={12}
                    strokeWidth={2}
                    style={{
                        marginLeft: '1px',
                        opacity: active ? 0.9 : 0.5,
                    }}
                />
            )}
        </button>
    );
}

export function CanvasBottomBar({
    currentTool,
    onSelectTool,
    pinCount,
    onApply,
    onUpscale,
    onResize,
    isGenerating,
    hasSelection,
}: CanvasBottomBarProps) {
    const [openPopover, setOpenPopover] = useState<OpenPopover>(null);
    const barRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (barRef.current && !barRef.current.contains(e.target as Node)) {
                setOpenPopover(null);
            }
        };
        const handleEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpenPopover(null);
        };
        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEsc);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEsc);
        };
    }, []);

    const togglePopover = (name: OpenPopover) => {
        setOpenPopover((prev) => (prev === name ? null : name));
    };

    const disabled = isGenerating || !hasSelection;

    return (
        <div ref={barRef} style={bottomBarStyle()}>
            {/* Primary tools */}
            <ToolSlot
                active={currentTool === 'select'}
                disabled={isGenerating}
                wide
                title="Move / Select (V)"
                onClick={() => onSelectTool('select')}
            >
                <MousePointer2 size={18} strokeWidth={1.75} />
            </ToolSlot>

            <ToolSlot
                active={currentTool === 'edit'}
                disabled={isGenerating}
                wide
                title="Edit with prompt (E)"
                onClick={() => onSelectTool('edit')}
            >
                <Wand2 size={18} strokeWidth={1.75} />
            </ToolSlot>

            {pinCount > 0 && (
                <ToolSlot
                    active
                    disabled={isGenerating}
                    wide
                    title={`Apply ${pinCount} change${pinCount > 1 ? 's' : ''}`}
                    onClick={onApply}
                >
                    <span style={{ fontSize: '12px', fontWeight: 600, letterSpacing: '0.01em' }}>
                        Apply
                    </span>
                    <span
                        style={{
                            fontSize: '11px',
                            fontWeight: 700,
                            background: 'rgba(255,255,255,0.2)',
                            borderRadius: '4px',
                            padding: '1px 5px',
                            marginLeft: '2px',
                        }}
                    >
                        {pinCount}
                    </span>
                </ToolSlot>
            )}

            <div style={toolbarDividerStyle()} />

            {/* Enhancement tools */}
            <div style={{ position: 'relative' }}>
                <ToolSlot
                    active={openPopover === 'upscale'}
                    disabled={disabled}
                    wide={openPopover === 'upscale'}
                    title="Upscale"
                    showChevron
                    onClick={() => togglePopover('upscale')}
                >
                    <HdRectangleIcon active={openPopover === 'upscale'} />
                </ToolSlot>
                {openPopover === 'upscale' && (
                    <div style={figmaPopoverStyle()}>
                        {[2, 4].map((factor) => (
                            <button
                                key={factor}
                                type="button"
                                style={figmaPopoverItemStyle()}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.background = 'rgba(255, 255, 255, 0.08)';
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.background = 'transparent';
                                }}
                                onClick={() => {
                                    setOpenPopover(null);
                                    onUpscale(factor);
                                }}
                            >
                                <span>{factor}x Upscale</span>
                                <span style={{ color: CANVAS_COLORS.toolbarIconMuted, fontSize: '11px' }}>
                                    {factor} cr
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div style={{ position: 'relative' }}>
                <ToolSlot
                    active={openPopover === 'resize'}
                    disabled={disabled}
                    wide={openPopover === 'resize'}
                    title="Resize aspect ratio"
                    showChevron
                    onClick={() => togglePopover('resize')}
                >
                    <Crop size={18} strokeWidth={1.75} />
                </ToolSlot>
                {openPopover === 'resize' && (
                    <div
                        style={{
                            ...figmaPopoverStyle(),
                            minWidth: '220px',
                            maxHeight: '280px',
                            overflowY: 'auto',
                        }}
                    >
                        {ASPECT_RATIO_PRESETS.map((preset) => (
                            <button
                                key={preset.value}
                                type="button"
                                style={figmaPopoverItemStyle()}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.background = 'rgba(255, 255, 255, 0.08)';
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.background = 'transparent';
                                }}
                                onClick={() => {
                                    setOpenPopover(null);
                                    onResize(preset.value);
                                }}
                            >
                                <span>{preset.label}</span>
                                <span style={{ color: CANVAS_COLORS.toolbarIconMuted, fontSize: '11px' }}>
                                    {preset.value}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
