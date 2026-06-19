import { dotGridBackground } from '@/lib/canvas-editor-tokens';
import type { PendingComment } from '@/lib/canvas-editor-types';
import { CanvasInlineComment } from '@/components/canvas-inline-comment';
import type { RefObject } from 'react';

interface CanvasViewportProps {
    viewportRef: RefObject<HTMLDivElement | null>;
    canvasRef: RefObject<HTMLCanvasElement | null>;
    showUploadZone: boolean;
    cursorStyle: string;
    pendingComment: PendingComment | null;
    pendingMarkerNumber?: number;
    onCommentSubmit: (text: string) => void;
    onCommentCancel: () => void;
    onMouseDown: (e: React.MouseEvent<HTMLCanvasElement>) => void;
    onMouseMove: (e: React.MouseEvent<HTMLCanvasElement>) => void;
    onMouseUp: () => void;
    onContextMenu: (e: React.MouseEvent<HTMLCanvasElement>) => void;
    onWheel: (e: React.WheelEvent<HTMLCanvasElement>) => void;
    onTouchStart: (e: React.TouchEvent<HTMLCanvasElement>) => void;
    onTouchMove: (e: React.TouchEvent<HTMLCanvasElement>) => void;
    onTouchEnd: (e: React.TouchEvent<HTMLCanvasElement>) => void;
    uploadZone?: React.ReactNode;
    children?: React.ReactNode;
}

export function CanvasViewport({
    viewportRef,
    canvasRef,
    showUploadZone,
    cursorStyle,
    pendingComment,
    pendingMarkerNumber,
    onCommentSubmit,
    onCommentCancel,
    onMouseDown,
    onMouseMove,
    onMouseUp,
    onContextMenu,
    onWheel,
    onTouchStart,
    onTouchMove,
    onTouchEnd,
    uploadZone,
    children,
}: CanvasViewportProps) {
    return (
        <div
            ref={viewportRef}
            style={{
                flex: 1,
                position: 'relative',
                overflow: 'hidden',
                ...dotGridBackground,
            }}
        >
            <canvas
                ref={canvasRef}
                onMouseDown={onMouseDown}
                onMouseMove={onMouseMove}
                onMouseUp={onMouseUp}
                onContextMenu={onContextMenu}
                onWheel={onWheel}
                onTouchStart={onTouchStart}
                onTouchMove={onTouchMove}
                onTouchEnd={onTouchEnd}
                style={{
                    display: showUploadZone ? 'none' : 'block',
                    cursor: cursorStyle,
                    touchAction: 'none',
                    width: '100%',
                    height: '100%',
                }}
            />

            {pendingComment && (
                <CanvasInlineComment
                    screenX={pendingComment.screenX}
                    screenY={pendingComment.screenY}
                    defaultValue={pendingComment.defaultValue}
                    markerNumber={pendingMarkerNumber}
                    onSubmit={onCommentSubmit}
                    onCancel={onCommentCancel}
                />
            )}

            {showUploadZone && uploadZone}
            {children}
        </div>
    );
}
