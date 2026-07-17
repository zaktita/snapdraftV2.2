import type { CSSProperties } from 'react';

export const CANVAS_CHROME = {
    TOP_BAR_HEIGHT: 40,
    PANEL_WIDTH: 240,
    PANEL_RAIL_WIDTH: 48,
    BOTTOM_BAR_HEIGHT: 44,
    BOTTOM_BAR_OFFSET: 24,
} as const;

export const CANVAS_COLORS = {
    selectionStroke: '#0D99FF',
    commentPin: '#F24822',
    figmaBlue: '#0D99FF',
    toolbarBg: '#1e1e1e',
    toolbarBorder: 'rgba(255, 255, 255, 0.08)',
    toolbarIcon: 'rgba(255, 255, 255, 0.85)',
    toolbarIconMuted: 'rgba(255, 255, 255, 0.45)',
    toolbarActive: '#f97316',
    toolbarActiveText: '#ffffff',
    toolbarDivider: 'rgba(255, 255, 255, 0.12)',
    commentInputBg: '#383838',
    commentBarBg: '#1e1e1e',
    commentPlaceholder: 'rgba(255, 255, 255, 0.4)',
    commentSendBg: '#525252',
    commentSendIcon: 'rgba(255, 255, 255, 0.55)',
} as const;

export const ASPECT_RATIO_PRESETS = [
    { value: '1:1', label: 'Square' },
    { value: '4:5', label: 'Portrait feed' },
    { value: '3:4', label: 'Portrait' },
    { value: '2:3', label: 'Pinterest pin' },
    { value: '9:16', label: 'Story / Reel' },
    { value: '3:2', label: 'Landscape' },
    { value: '4:3', label: 'Landscape' },
    { value: '5:4', label: 'Landscape' },
    { value: '16:9', label: 'Wide' },
] as const;

export const dotGridBackground: CSSProperties = {
    backgroundColor: 'color-mix(in oklab, var(--color-muted) 35%, var(--color-background))',
    backgroundImage:
        'radial-gradient(circle, color-mix(in oklab, var(--color-border) 80%, transparent) 1px, transparent 1px)',
    backgroundSize: '16px 16px',
};

export const chromeBar: CSSProperties = {
    background: 'var(--color-card)',
    borderColor: 'var(--color-border)',
};

export function sectionLabelStyle(): CSSProperties {
    return {
        fontSize: '11px',
        fontWeight: 600,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: 'var(--color-muted-foreground)',
        padding: '8px 12px 4px',
    };
}

export function panelRowStyle(selected = false): CSSProperties {
    return {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        minHeight: '36px',
        padding: '4px 12px',
        cursor: 'pointer',
        background: selected ? 'var(--color-muted)' : 'transparent',
        borderLeft: selected ? `2px solid var(--color-primary)` : '2px solid transparent',
    };
}

export function bottomBarStyle(): CSSProperties {
    return {
        position: 'absolute',
        bottom: CANVAS_CHROME.BOTTOM_BAR_OFFSET,
        left: '50%',
        transform: 'translateX(-50%)',
        display: 'flex',
        alignItems: 'center',
        gap: '4px',
        height: CANVAS_CHROME.BOTTOM_BAR_HEIGHT,
        padding: '6px 8px',
        background: CANVAS_COLORS.toolbarBg,
        border: `1px solid ${CANVAS_COLORS.toolbarBorder}`,
        borderRadius: '12px',
        boxShadow: '0 8px 32px rgba(0, 0, 0, 0.45), 0 2px 8px rgba(0, 0, 0, 0.3)',
        zIndex: 60,
    };
}

export function figmaToolSlotStyle(active: boolean, disabled = false, wide = false): CSSProperties {
    return {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '2px',
        minWidth: active && wide ? '56px' : '40px',
        height: '32px',
        padding: active && wide ? '0 14px' : '0 10px',
        border: 'none',
        borderRadius: '8px',
        background: active ? CANVAS_COLORS.toolbarActive : 'transparent',
        color: active ? CANVAS_COLORS.toolbarActiveText : CANVAS_COLORS.toolbarIcon,
        cursor: disabled ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.35 : 1,
        transition: 'background 0.15s ease, color 0.15s ease',
        flexShrink: 0,
    };
}

export function figmaPopoverStyle(): CSSProperties {
    return {
        position: 'absolute',
        bottom: 'calc(100% + 10px)',
        left: '50%',
        transform: 'translateX(-50%)',
        minWidth: '180px',
        background: CANVAS_COLORS.toolbarBg,
        border: `1px solid ${CANVAS_COLORS.toolbarBorder}`,
        borderRadius: '10px',
        boxShadow: '0 8px 32px rgba(0, 0, 0, 0.5)',
        padding: '6px',
        zIndex: 70,
    };
}

export function figmaPopoverItemStyle(): CSSProperties {
    return {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        width: '100%',
        padding: '9px 12px',
        border: 'none',
        borderRadius: '6px',
        background: 'transparent',
        cursor: 'pointer',
        fontSize: '13px',
        color: CANVAS_COLORS.toolbarIcon,
        textAlign: 'left',
    };
}

export function toolbarDividerStyle(): CSSProperties {
    return {
        width: '1px',
        height: '22px',
        background: CANVAS_COLORS.toolbarDivider,
        margin: '0 6px',
        flexShrink: 0,
    };
}

const wandCursorSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72" stroke="${CANVAS_COLORS.toolbarActive}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m14 7 3 3M5 6v4M19 14v4M10 2v2M7 8H3M21 16h-4M11 3H9" stroke="${CANVAS_COLORS.toolbarActive}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

/** Custom cursor for Edit mode - orange magic wand with white outline */
export const MAGIC_WAND_CURSOR = `url("data:image/svg+xml,${encodeURIComponent(wandCursorSvg)}") 3 21, pointer`;
