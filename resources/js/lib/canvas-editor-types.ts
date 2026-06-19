export interface AnnotationPoint {
    id: number;
    objectId: number;
    x: number;
    y: number;
    description: string;
    number: number;
}

export interface EditHistoryEntry {
    id: number;
    type: 'edit' | 'upscale' | 'resize';
    label: string;
    objectId: number;
    thumbnailSrc: string;
    timestamp: number;
    meta?: { aspectRatio?: string; upscaleFactor?: number };
}

export interface PendingComment {
    screenX: number;
    screenY: number;
    imageX: number;
    imageY: number;
    objectId: number;
    editingAnnotationId?: number;
    defaultValue?: string;
}

export type CanvasTool = 'select' | 'edit';
