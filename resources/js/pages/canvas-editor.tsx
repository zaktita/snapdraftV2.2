import {
    AlertModal,
    ConfirmModal,
    PromptModal,
    UpscaleModal,
} from '@/components/canvas-modals';
import { Skeleton } from '@/components/ui/skeleton';
import { debug } from '@/lib/debug';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronLeft,
    Crop,
    Download,
    Eraser,
    Lightbulb,
    Maximize2,
    MousePointer,
    PenTool,
    Redo2,
    RotateCcw,
    Save,
    Scissors,
    Type,
    Undo2,
    Wand2,
    ZoomIn,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

// Utility to parse query params
function getQueryParams() {
    if (typeof window === 'undefined') return {};
    const params = new URLSearchParams(window.location.search);
    return {
        projectId: params.get('projectId')
            ? Number(params.get('projectId'))
            : undefined,
        imageUrl: params.get('image') || undefined,
        projectTitle: params.get('title') || undefined,
    };
}

interface CanvasEditorProps {
    projectId?: number;
    imageUrl?: string;
    projectTitle?: string;
}

interface CanvasObject {
    id: number;
    image: HTMLImageElement;
    x: number;
    y: number;
    label?: string;
    isMainImage?: boolean;
}

interface BrushLine {
    id: number;
    points: number[];
    brushSize: number;
    brushOpacity: number;
    tool: string;
    objectId?: number | null;
}

// Resize handle type for objects
type ResizeHandle = 'nw' | 'ne' | 'sw' | 'se';

// API request body types
interface ApiBody {
    image?: string;
    image_url?: string;
    tool?: string;
    mask?: string;
    maskImage?: string;
    sourceImage?: string;
    prompt?: string;
    replacementText?: string;
    expansionRatio?: number;
    [key: string]: any; // Allow additional properties
}

// Constants
const CANVAS_DEFAULTS = {
    BRUSH_SIZE: 50,
    BRUSH_OPACITY: 100,
    ZOOM_FACTOR: 1.2,
    ZOOM_MIN: 0.1,
    ZOOM_MAX: 10,
    ZOOM_SCALE_FACTOR: 1.1,
    OBJECT_SPACING: 50,
    UNDO_LIMIT: 50,
    TOAST_DISMISS_MS: 3000,
    CANVAS_PADDING: 100,
    ERASE_GAP: 20,
};

// Object ID counter to prevent collisions
let objectIdCounter = 0;
const generateObjectId = () => ++objectIdCounter;

// Utility function to convert image to data URL
const imageToDataUrl = (img: HTMLImageElement): string => {
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Failed to create canvas context');
    ctx.drawImage(img, 0, 0);
    return canvas.toDataURL('image/png');
};

export default function CanvasEditor(props: CanvasEditorProps) {
    // Read query params if present
    const query = getQueryParams();
    const projectId = props.projectId ?? query.projectId;
    const imageUrl = props.imageUrl ?? query.imageUrl;
    const projectTitle = props.projectTitle ?? query.projectTitle ?? 'Untitled';
    // Canvas refs
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // State
    const [canvas, setCanvas] = useState<HTMLCanvasElement | null>(null);
    const [ctx, setCtx] = useState<CanvasRenderingContext2D | null>(null);
    const [image, setImage] = useState<HTMLImageElement | null>(null);
    const [currentTool, setCurrentTool] = useState('select');
    const [brushSize, setBrushSize] = useState(CANVAS_DEFAULTS.BRUSH_SIZE);
    const [brushOpacity, setBrushOpacity] = useState(CANVAS_DEFAULTS.BRUSH_OPACITY);
    const [isPainting, setIsPainting] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [isSpacePressed, setIsSpacePressed] = useState(false);
    const [lines, setLines] = useState<BrushLine[]>([]);
    const [scale, setScale] = useState(1);
    const [panX, setPanX] = useState(0);
    const [panY, setPanY] = useState(0);
    const [lastPanPoint, setLastPanPoint] = useState({ x: 0, y: 0 });
    const [undoStack, setUndoStack] = useState<BrushLine[][]>([]);
    const [redoStack, setRedoStack] = useState<BrushLine[][]>([]);
    const [canvasObjects, setCanvasObjects] = useState<CanvasObject[]>([]);
    const [selectedObject, setSelectedObject] = useState<CanvasObject | null>(
        null,
    );
    const [draggedObject, setDraggedObject] = useState<CanvasObject | null>(
        null,
    );
    const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [showUploadZone, setShowUploadZone] = useState(true);
    const [generatingType, setGeneratingType] = useState<string | null>(null);
    const [actionHistory, setActionHistory] = useState<Array<{ type: string; message: string; time: number }>>([]);
    const [cursorPosition, setCursorPosition] = useState({ x: 0, y: 0 });
    const [upscaleModal, setUpscaleModal] = useState({ isOpen: false, selectedFactor: 2 });
    const internalClipboardRef = useRef<HTMLImageElement | null>(null);

    // Crop rectangle state (in canvas coordinates)
    const [cropMode, setCropMode] = useState(false);
    const [cropRect, setCropRect] = useState<{ x: number; y: number; w: number; h: number } | null>(null);
    const [cropDragHandle, setCropDragHandle] = useState<string | null>(null); // 'move', 'nw', 'ne', 'sw', 'se', 'n', 's', 'e', 'w'
    const [cropDragStart, setCropDragStart] = useState<{ x: number; y: number; rectX: number; rectY: number; rectW: number; rectH: number } | null>(null);
    const [cropIntent, setCropIntent] = useState<'crop' | 'expand'>('crop');

    // Object resize state
    const [resizingObject, setResizingObject] = useState<CanvasObject | null>(null);
    const [resizeHandle, setResizeHandle] = useState<ResizeHandle | null>(null);
    const [resizeStart, setResizeStart] = useState<{ mouseX: number; mouseY: number; objX: number; objY: number; objW: number; objH: number } | null>(null);

    // Drag-and-drop state
    const [isDragOver, setIsDragOver] = useState(false);

    // Generation progress timer
    const [genElapsedSecs, setGenElapsedSecs] = useState(0);
    const genTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Modal state
    const [promptModal, setPromptModal] = useState<{
        isOpen: boolean;
        title: string;
        description: string;
        placeholder: string;
        defaultValue: string;
        resolve: ((value: string) => void) | null;
    }>({
        isOpen: false,
        title: '',
        description: '',
        placeholder: '',
        defaultValue: '',
        resolve: null,
    });

    const [alertModal, setAlertModal] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        type: 'info' | 'warning' | 'error';
    }>({
        isOpen: false,
        title: '',
        message: '',
        type: 'info',
    });

    const [confirmModal, setConfirmModal] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        confirmText: string;
        isDanger: boolean;
        resolve: ((value: boolean) => void) | null;
    }>({
        isOpen: false,
        title: '',
        message: '',
        confirmText: 'Confirm',
        isDanger: false,
        resolve: null,
    });

    // Debug confirmModal state changes
    useEffect(() => {
        debug.log('[State] confirmModal.isOpen:', confirmModal.isOpen, {
            title: confirmModal.title,
        });
    }, [confirmModal.isOpen]);

    const [isGenerating, setIsGenerating] = useState(false);

    // Initialize canvas
    useEffect(() => {
        if (canvasRef.current) {
            const canvasEl = canvasRef.current;
            const context = canvasEl.getContext('2d');
            setCanvas(canvasEl);
            setCtx(context);
        }

        // Keyboard shortcuts
        const handleKeyDown = (e: KeyboardEvent) => {
            // Prevent shortcuts when typing in inputs
            if (
                e.target instanceof HTMLInputElement ||
                e.target instanceof HTMLTextAreaElement
            ) {
                return;
            }

            // Space for pan
            if (e.code === 'Space' && !e.repeat) {
                e.preventDefault();
                setIsSpacePressed(true);
            }

            // Ctrl+0: Reset view
            if ((e.ctrlKey || e.metaKey) && e.code === 'Digit0') {
                e.preventDefault();
                fitToScreen();
            }

            // Ctrl+C: Copy selected object (internal clipboard)
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.code === 'KeyC') {
                if (selectedObject) {
                    internalClipboardRef.current = selectedObject.image;
                }
            }

            // Ctrl+Z: Undo
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.code === 'KeyZ') {
                e.preventDefault();
                if (undoStack.length > 0) {
                    const newUndoStack = [...undoStack];
                    const lastState = newUndoStack.pop() || [];
                    setRedoStack((prev) => [...prev, lines]);
                    setLines(lastState);
                    setUndoStack(newUndoStack);
                }
            }

            // Ctrl+V: if internal clipboard exists, allow quick duplicate (fallback if no OS image)
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.code === 'KeyV') {
                if (internalClipboardRef.current) {
                    // Do not prevent default here to still allow OS image paste; only use as quick duplicate if no paste event fires
                    setTimeout(() => {
                        // If nothing was just pasted (best-effort), duplicate from internal clipboard
                        // This is a simple fallback; paste event will have already consumed default if OS had an image
                        const src = internalClipboardRef.current?.src;
                        if (!src) return;
                        const img = new Image();
                        img.onload = () => addImageToCanvas(img, 'Pasted Copy');
                        img.crossOrigin = 'anonymous';
                        img.src = src;
                    }, 0);
                }
            }

            // Ctrl+Shift+Z: Redo
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.code === 'KeyZ') {
                e.preventDefault();
                if (redoStack.length > 0) {
                    const newRedoStack = [...redoStack];
                    const nextState = newRedoStack.pop()!;
                    setUndoStack((prev) => [...prev, lines]);
                    setLines(nextState);
                    setRedoStack(newRedoStack);
                }
            }

            // Delete selected (not main image)
            if (e.code === 'Delete') {
                if (selectedObject && !selectedObject.isMainImage) {
                    // Remove selected object
                    setCanvasObjects((prev) =>
                        prev.filter((obj) => obj.id !== selectedObject.id),
                    );
                    // Clear selection
                    setSelectedObject(null);
                }
            }

            // Tool shortcuts (without modifier keys)
            // Only trigger if no modifier keys are pressed
            if (!e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                if (e.code === 'KeyV') {
                    e.preventDefault();
                    selectTool('select');
                    return;
                }
                if (e.code === 'KeyR') {
                    e.preventDefault();
                    selectTool('retouch');
                    return;
                }
                if (e.code === 'KeyE') {
                    e.preventDefault();
                    selectTool('erase');
                    return;
                }
                if (e.code === 'KeyU') {
                    e.preventDefault();
                    fileInputRef.current?.click();
                    return;
                }
                // Enhancement tool shortcuts
                if (e.code === 'KeyX') {
                    e.preventDefault();
                    if (!isGenerating) {
                        handleExpandImage();
                    }
                    return;
                }
                if (e.code === 'KeyS') {
                    e.preventDefault();
                    if (!isGenerating) {
                        handleUpscaleImage();
                    }
                    return;
                }
                if (e.code === 'KeyB') {
                    e.preventDefault();
                    if (!isGenerating) {
                        handleRemoveBackground();
                    }
                    return;
                }
            }
        };

        const handleKeyUp = (e: KeyboardEvent) => {
            if (e.code === 'Space') {
                setIsSpacePressed(false);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('keyup', handleKeyUp);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('keyup', handleKeyUp);
        };
    }, [undoStack, redoStack, lines, selectedObject]);

    // Load image from URL when canvas and imageUrl are ready
    useEffect(() => {
        if (canvas && imageUrl) {
            loadImageFromUrl(imageUrl);
        }
    }, [canvas, imageUrl]);

    // Resize canvas
    useEffect(() => {
        if (canvas) {
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            return () => window.removeEventListener('resize', resizeCanvas);
        }
    }, [canvas, image]);

    // Paste handler: allow pasting images from clipboard or duplicate internal copy
    useEffect(() => {
        const handlePaste = (e: ClipboardEvent) => {
            debug.log('[paste] event received');
            const target = e.target as HTMLElement | null;
            if (
                target &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.getAttribute('contenteditable') === 'true')
            ) {
                return;
            }
            if (!canvas) return;
            let consumed = false;

            if (
                e.clipboardData &&
                e.clipboardData.files &&
                e.clipboardData.files.length > 0
            ) {
                const file = Array.from(e.clipboardData.files).find((f) =>
                    f.type.startsWith('image/'),
                );
                if (file) {
                    e.preventDefault();
                    debug.log('[paste] found image file in clipboard');
                    const url = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = () => {
                        URL.revokeObjectURL(url);
                        addImageToCanvas(img, 'Pasted Image');
                    };
                    img.crossOrigin = 'anonymous';
                    img.src = url;
                    consumed = true;
                }
            }

            if (!consumed && e.clipboardData) {
                const items = e.clipboardData.items
                    ? Array.from(e.clipboardData.items)
                    : [];
                const imageItem = items.find(
                    (it: DataTransferItem) =>
                        it.kind === 'file' && it.type.startsWith('image/'),
                ) as DataTransferItem | undefined;
                if (imageItem) {
                    const file = imageItem.getAsFile();
                    if (file) {
                        e.preventDefault();
                        debug.log(
                            '[paste] found image item in clipboard items',
                        );
                        const url = URL.createObjectURL(file);
                        const img = new Image();
                        img.onload = () => {
                            URL.revokeObjectURL(url);
                            addImageToCanvas(img, 'Pasted Image');
                        };
                        img.crossOrigin = 'anonymous';
                        img.src = url;
                        consumed = true;
                    }
                }
            }

            if (!consumed && e.clipboardData) {
                const text = e.clipboardData.getData('text');
                if (
                    text &&
                    /^https?:\/\//i.test(text) &&
                    /(\.png|\.jpe?g|\.webp|\.gif)(\?|#|$)/i.test(text)
                ) {
                    e.preventDefault();
                    debug.log('[paste] found image URL text');
                    const img = new Image();
                    img.onload = () => addImageToCanvas(img, 'Pasted Image');
                    img.crossOrigin = 'anonymous';
                    img.src = text;
                    consumed = true;
                }
            }

            if (!consumed && internalClipboardRef.current) {
                e.preventDefault();
                debug.log('[paste] using internal clipboard fallback');
                const src = internalClipboardRef.current.src;
                const img = new Image();
                img.onload = () => addImageToCanvas(img, 'Pasted Copy');
                img.crossOrigin = 'anonymous';
                img.src = src;
            }
        };

        // Type-safe wrapper for paste event
        const handlePasteTyped = (e: Event) => {
            if (e instanceof ClipboardEvent) {
                handlePaste(e);
            }
        };

        window.addEventListener('paste', handlePasteTyped);
        document.addEventListener('paste', handlePasteTyped);
        return () => {
            window.removeEventListener('paste', handlePasteTyped);
            document.removeEventListener('paste', handlePasteTyped);
        };
    }, [canvas, scale, panX, panY, cursorPosition, canvasObjects]);

    // Auto-dismiss alert modal for info type
    useEffect(() => {
        if (alertModal.isOpen && alertModal.type === 'info') {
            const timer = setTimeout(() => {
                setAlertModal((prev) => ({ ...prev, isOpen: false }));
            }, CANVAS_DEFAULTS.TOAST_DISMISS_MS);

            return () => clearTimeout(timer);
        }
    }, [alertModal.isOpen, alertModal.type]);

    // Auto-clear action history toasts
    useEffect(() => {
        if (actionHistory.length > 0) {
            const latestAction = actionHistory[0];
            const timer = setTimeout(() => {
                setActionHistory((prev) => prev.filter(item => item.time !== latestAction.time));
            }, CANVAS_DEFAULTS.TOAST_DISMISS_MS);

            return () => clearTimeout(timer);
        }
    }, [actionHistory.length > 0 ? actionHistory[0]?.time : null]);

    // Sync draggedObject and selectedObject when canvasObjects change during drag
    useEffect(() => {
        if (draggedObject) {
            const updatedObject = canvasObjects.find(obj => obj.id === draggedObject.id);
            if (updatedObject) {
                setSelectedObject(updatedObject);
                setDraggedObject(updatedObject);
            }
        }
    }, [canvasObjects, draggedObject?.id]);

    // Draw scene when state changes
    useEffect(() => {
        if (canvas && ctx) {
            drawScene();
        }
    }, [
        canvas,
        ctx,
        canvasObjects,
        lines,
        scale,
        panX,
        panY,
        selectedObject,
        brushSize,
        cursorPosition,
        currentTool,
        isSpacePressed,
        isDragging,
        isGenerating,
        resizingObject,
    ]);

    // Debug important state changes
    useEffect(() => {
        if (selectedObject) {
            debug.log('[State] selectedObject changed', {
                id: selectedObject.id,
                isMainImage: !!selectedObject.isMainImage,
                x: selectedObject.x,
                y: selectedObject.y,
            });
        } else {
            debug.log('[State] selectedObject changed: null');
        }
    }, [selectedObject]);

    useEffect(() => {
        debug.log('[State] isGenerating:', isGenerating);
    }, [isGenerating]);

    useEffect(() => {
        debug.log('[State] currentTool:', currentTool);
    }, [currentTool]);

    useEffect(() => {
        debug.log('[State] lines length:', lines.length);
    }, [lines.length]);

    useEffect(() => {
        const hasMask = !!(
            selectedObject &&
            lines.some((l) => l.objectId === selectedObject.id)
        );
        debug.log('[State] hasMaskForSelection:', hasMask);
    }, [selectedObject, lines]);

    const resizeCanvas = () => {
        if (!canvas) return;
        const container = canvas.parentElement;
        if (!container) return;
        canvas.width = container.clientWidth;
        canvas.height = container.clientHeight;
        if (image) drawScene();
    };

    const loadImageFromUrl = (src: string) => {
        // If src is a relative storage path (not a URL or data URI), prepend /storage/
        const resolvedSrc =
            src.startsWith('http') || src.startsWith('data:') || src.startsWith('/')
                ? src
                : `/storage/${src}`;
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            debug.log('[Canvas] Main image loaded', {
                width: img.width,
                height: img.height,
                src: resolvedSrc.slice(0, 60) + '...',
            });
            setImage(img);

            // Calculate center position before rendering
            requestAnimationFrame(() => {
                if (!canvas) return;

                const imageWidth = img.width;
                const imageHeight = img.height;
                const containerWidth = canvas.width - CANVAS_DEFAULTS.CANVAS_PADDING;
                const containerHeight = canvas.height - CANVAS_DEFAULTS.CANVAS_PADDING;

                const scaleX = containerWidth / imageWidth;
                const scaleY = containerHeight / imageHeight;
                const newScale = Math.min(scaleX, scaleY, 1);

                // Calculate centered position
                const centeredPanX = (canvas.width - imageWidth * newScale) / 2;
                const centeredPanY =
                    (canvas.height - imageHeight * newScale) / 2;

                // Set scale and pan before creating objects
                setScale(newScale);
                setPanX(centeredPanX);
                setPanY(centeredPanY);

                // Now create the object and render
                const mainImageObj: CanvasObject = {
                    id: generateObjectId(),
                    image: img,
                    x: 0,
                    y: 0,
                    label: 'Main Image',
                    isMainImage: true,
                };
                setCanvasObjects([mainImageObj]);
                setSelectedObject(mainImageObj);
                setShowUploadZone(false);
                setLines([]);
                debug.log('[Canvas] Main image object created and selected', {
                    id: mainImageObj.id,
                    scale: newScale,
                    panX: centeredPanX,
                    panY: centeredPanY,
                });
            });
        };
        img.src = resolvedSrc;
    };

    const loadImageFile = (file: File) => {
        if (!file.type.startsWith('image/')) {
            showAlert('Invalid File', 'Please select an image file.', 'warning');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            if (e.target?.result) {
                loadImageFromUrl(e.target.result as string);
            }
        };
        reader.readAsDataURL(file);
    };

    const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (file) {
            loadImageFile(file);
        }
    };

    const drawScene = () => {
        if (!ctx || !canvas) return;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw all canvas objects
        canvasObjects.forEach((obj) => drawCanvasObject(obj));

        // If in crop mode, draw crop overlay instead of normal UI
        if (cropMode && cropRect) {
            drawCropRectangle();
            return;
        }

        // Draw brush strokes
        canvasObjects.forEach((obj) => {
            const objectLines = lines.filter(
                (line) => !line.objectId || line.objectId === obj.id,
            );
            if (objectLines.length > 0) {
                drawBrushStrokes(obj);
            }
        });

        // Draw selection highlight
        const sel = draggedObject || selectedObject;
        if (sel) {
            drawSelectionHighlight(sel);
        }

        // Draw custom brush cursor when retouch or erase tool is active and hovering over image
        if (
            (currentTool === 'retouch' || currentTool === 'erase') &&
            selectedObject &&
            !isDragging &&
            !isPainting
        ) {
            const hoveredObject = getObjectAtPoint(
                cursorPosition.x,
                cursorPosition.y,
            );
            if (hoveredObject === selectedObject) {
                drawBrushCursor();
            }
        }
    };

    const getResizeHandleAtPoint = (screenX: number, screenY: number, obj: CanvasObject): ResizeHandle | null => {
        if (!obj || obj.isMainImage) return null;
        const sx = panX + obj.x * scale;
        const sy = panY + obj.y * scale;
        const sw = obj.image.width * scale;
        const sh = obj.image.height * scale;
        const hSize = 8;
        const tol = 6;
        const corners: [ResizeHandle, number, number][] = [
            ['nw', sx, sy],
            ['ne', sx + sw, sy],
            ['sw', sx, sy + sh],
            ['se', sx + sw, sy + sh],
        ];
        for (const [handle, hx, hy] of corners) {
            if (Math.abs(screenX - hx) <= hSize / 2 + tol && Math.abs(screenY - hy) <= hSize / 2 + tol) {
                return handle;
            }
        }
        return null;
    };

    // Start generation timer
    const startGenTimer = () => {
        setGenElapsedSecs(0);
        if (genTimerRef.current) clearInterval(genTimerRef.current);
        genTimerRef.current = setInterval(() => setGenElapsedSecs(s => s + 1), 1000);
    };
    const stopGenTimer = () => {
        if (genTimerRef.current) { clearInterval(genTimerRef.current); genTimerRef.current = null; }
    };

    const drawCanvasObject = (obj: CanvasObject) => {
        if (!ctx) return;

        ctx.save();
        ctx.translate(panX + obj.x * scale, panY + obj.y * scale);
        ctx.scale(scale, scale);
        ctx.drawImage(obj.image, 0, 0);
        ctx.restore();
    };

    const drawBrushStrokes = (imageObj: CanvasObject) => {
        if (!ctx) return;

        ctx.save();
        ctx.translate(panX + imageObj.x * scale, panY + imageObj.y * scale);
        ctx.scale(scale, scale);
        ctx.strokeStyle = 'rgba(76, 175, 80, 0.7)';
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        lines.forEach((line) => {
            if (
                line.points.length > 1 &&
                (!line.objectId || line.objectId === imageObj.id)
            ) {
                ctx.lineWidth = line.brushSize;
                ctx.globalAlpha = line.brushOpacity;
                ctx.beginPath();
                ctx.moveTo(line.points[0], line.points[1]);
                for (let i = 2; i < line.points.length; i += 2) {
                    ctx.lineTo(line.points[i], line.points[i + 1]);
                }
                ctx.stroke();
            }
        });

        ctx.restore();
    };

    const drawSelectionHighlight = (obj: CanvasObject) => {
        if (!ctx) return;

        const sx = panX + obj.x * scale;
        const sy = panY + obj.y * scale;
        const sw = obj.image.width * scale;
        const sh = obj.image.height * scale;

        ctx.save();
        ctx.strokeStyle = '#2196F3';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 5]);
        ctx.strokeRect(sx - 1, sy - 1, sw + 2, sh + 2);
        ctx.setLineDash([]);

        // Draw resize handles only for non-main images in select mode
        if (!obj.isMainImage && currentTool === 'select') {
            const hSize = 8;
            const corners: [number, number][] = [
                [sx - hSize / 2, sy - hSize / 2],           // nw
                [sx + sw - hSize / 2, sy - hSize / 2],      // ne
                [sx - hSize / 2, sy + sh - hSize / 2],      // sw
                [sx + sw - hSize / 2, sy + sh - hSize / 2], // se
            ];
            ctx.fillStyle = '#ffffff';
            corners.forEach(([cx, cy]) => {
                ctx.fillRect(cx, cy, hSize, hSize);
                ctx.strokeRect(cx, cy, hSize, hSize);
            });
        }

        ctx.restore();
    };

    const drawCropRectangle = () => {
        if (!ctx || !cropRect) return;

        const screenX = panX + cropRect.x * scale;
        const screenY = panY + cropRect.y * scale;
        const screenW = cropRect.w * scale;
        const screenH = cropRect.h * scale;

        ctx.save();

        // Darken area outside crop rect
        ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        ctx.fillRect(0, 0, canvas!.width, canvas!.height);
        ctx.clearRect(screenX, screenY, screenW, screenH);
        ctx.globalCompositeOperation = 'destination-over';
        canvasObjects.forEach((obj) => drawCanvasObject(obj));
        ctx.globalCompositeOperation = 'source-over';

        // Draw crop rectangle border
        ctx.strokeStyle = '#2196F3';
        ctx.lineWidth = 2;
        ctx.setLineDash([]);
        ctx.strokeRect(screenX, screenY, screenW, screenH);

        // Draw corner handles (8x8 squares)
        const handleSize = 8;
        ctx.fillStyle = '#2196F3';
        const corners = [
            { x: screenX - handleSize / 2, y: screenY - handleSize / 2 }, // nw
            { x: screenX + screenW - handleSize / 2, y: screenY - handleSize / 2 }, // ne
            { x: screenX - handleSize / 2, y: screenY + screenH - handleSize / 2 }, // sw
            { x: screenX + screenW - handleSize / 2, y: screenY + screenH - handleSize / 2 }, // se
        ];
        corners.forEach(corner => {
            ctx.fillRect(corner.x, corner.y, handleSize, handleSize);
        });

        // Draw edge handles (middle of each side)
        const edges = [
            { x: screenX + screenW / 2 - handleSize / 2, y: screenY - handleSize / 2 }, // n
            { x: screenX + screenW / 2 - handleSize / 2, y: screenY + screenH - handleSize / 2 }, // s
            { x: screenX - handleSize / 2, y: screenY + screenH / 2 - handleSize / 2 }, // w
            { x: screenX + screenW - handleSize / 2, y: screenY + screenH / 2 - handleSize / 2 }, // e
        ];
        edges.forEach(edge => {
            ctx.fillRect(edge.x, edge.y, handleSize, handleSize);
        });

        ctx.restore();
    };

    const drawBrushCursor = () => {
        if (!ctx) return;

        const scaledBrushSize = brushSize * scale;

        ctx.save();
        ctx.strokeStyle = '#4CAF50';
        ctx.fillStyle = 'rgba(76, 175, 80, 0.1)';
        ctx.lineWidth = 2;
        ctx.setLineDash([]);

        // Draw circle cursor
        ctx.beginPath();
        ctx.arc(
            cursorPosition.x,
            cursorPosition.y,
            scaledBrushSize / 2,
            0,
            Math.PI * 2,
        );
        ctx.fill();
        ctx.stroke();

        // Draw crosshair
        const crosshairSize = 8;
        ctx.beginPath();
        ctx.moveTo(cursorPosition.x - crosshairSize, cursorPosition.y);
        ctx.lineTo(cursorPosition.x + crosshairSize, cursorPosition.y);
        ctx.moveTo(cursorPosition.x, cursorPosition.y - crosshairSize);
        ctx.lineTo(cursorPosition.x, cursorPosition.y + crosshairSize);
        ctx.stroke();

        ctx.restore();
    };

    const screenToCanvasCoordinates = (screenX: number, screenY: number) => {
        return { x: (screenX - panX) / scale, y: (screenY - panY) / scale };
    };

    const screenToObjectCoordinates = (
        screenX: number,
        screenY: number,
        obj: CanvasObject,
    ) => {
        const canvasPos = screenToCanvasCoordinates(screenX, screenY);
        return { x: canvasPos.x - obj.x, y: canvasPos.y - obj.y };
    };

    const isPointInObject = (x: number, y: number, obj: CanvasObject) => {
        return (
            x >= 0 && x <= obj.image.width && y >= 0 && y <= obj.image.height
        );
    };

    const getCropHandleAtPoint = (screenX: number, screenY: number): string | null => {
        if (!cropRect) return null;

        const screenRectX = panX + cropRect.x * scale;
        const screenRectY = panY + cropRect.y * scale;
        const screenRectW = cropRect.w * scale;
        const screenRectH = cropRect.h * scale;
        const handleSize = 8;
        const tolerance = 5;

        // Check corners
        const corners = [
            { handle: 'nw', x: screenRectX, y: screenRectY },
            { handle: 'ne', x: screenRectX + screenRectW, y: screenRectY },
            { handle: 'sw', x: screenRectX, y: screenRectY + screenRectH },
            { handle: 'se', x: screenRectX + screenRectW, y: screenRectY + screenRectH },
        ];
        for (const corner of corners) {
            if (Math.abs(screenX - corner.x) < handleSize + tolerance && Math.abs(screenY - corner.y) < handleSize + tolerance) {
                return corner.handle;
            }
        }

        // Check edges
        const edges = [
            { handle: 'n', x: screenRectX + screenRectW / 2, y: screenRectY },
            { handle: 's', x: screenRectX + screenRectW / 2, y: screenRectY + screenRectH },
            { handle: 'w', x: screenRectX, y: screenRectY + screenRectH / 2 },
            { handle: 'e', x: screenRectX + screenRectW, y: screenRectY + screenRectH / 2 },
        ];
        for (const edge of edges) {
            if (Math.abs(screenX - edge.x) < handleSize + tolerance && Math.abs(screenY - edge.y) < handleSize + tolerance) {
                return edge.handle;
            }
        }

        // Check if inside rect (move)
        if (screenX >= screenRectX && screenX <= screenRectX + screenRectW &&
            screenY >= screenRectY && screenY <= screenRectY + screenRectH) {
            return 'move';
        }

        return null;
    };

    const getObjectAtPoint = (screenX: number, screenY: number) => {
        for (let i = canvasObjects.length - 1; i >= 0; i--) {
            const obj = canvasObjects[i];
            const objPos = screenToObjectCoordinates(screenX, screenY, obj);
            if (isPointInObject(objPos.x, objPos.y, obj)) {
                return obj;
            }
        }
        return null;
    };

    const handleMouseDown = (e: React.MouseEvent<HTMLCanvasElement>) => {
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Crop mode interactions
        if (cropMode && cropRect) {
            const handle = getCropHandleAtPoint(x, y);
            if (handle) {
                setCropDragHandle(handle);
                setCropDragStart({
                    x,
                    y,
                    rectX: cropRect.x,
                    rectY: cropRect.y,
                    rectW: cropRect.w,
                    rectH: cropRect.h,
                });
                return;
            }
        }

        // Pan with space+drag or middle mouse button
        if (e.button === 1 || (e.button === 0 && isSpacePressed)) {
            e.preventDefault();
            setIsDragging(true);
            setLastPanPoint({ x: e.clientX, y: e.clientY });
            return;
        }

        if (e.button === 2) return;

        // Check resize handles first (only in select mode on selected non-main object)
        if (currentTool === 'select' && selectedObject && !selectedObject.isMainImage) {
            const rHandle = getResizeHandleAtPoint(x, y, selectedObject);
            if (rHandle) {
                setResizeHandle(rHandle);
                setResizingObject(selectedObject);
                setResizeStart({
                    mouseX: x,
                    mouseY: y,
                    objX: selectedObject.x,
                    objY: selectedObject.y,
                    objW: selectedObject.image.width,
                    objH: selectedObject.image.height,
                });
                return;
            }
        }

        const clickedObject = getObjectAtPoint(x, y);
        if (clickedObject) {
            setSelectedObject(clickedObject);

            if ((currentTool === 'retouch' || currentTool === 'erase') && clickedObject === selectedObject) {
                const imagePos = screenToObjectCoordinates(x, y, clickedObject);
                if (isPointInObject(imagePos.x, imagePos.y, clickedObject)) {
                    setIsPainting(true);
                    startNewLine(imagePos.x, imagePos.y, clickedObject.id);
                }
            } else if (currentTool === 'select') {
                const objScreenX = panX + clickedObject.x * scale;
                const objScreenY = panY + clickedObject.y * scale;
                setDraggedObject(clickedObject);
                setDragOffset({ x: x - objScreenX, y: y - objScreenY });
            }
        } else {
            setSelectedObject(null);
            setDraggedObject(null);
        }
    };

    // Unified pointer event handler (used for both mouse and touch)
    const handlePointerMove = (x: number, y: number, clientX: number, clientY: number) => {
        setCursorPosition({ x, y });

        // Object resize
        if (resizingObject && resizeHandle && resizeStart) {
            const dx = (x - resizeStart.mouseX) / scale;
            const dy = (y - resizeStart.mouseY) / scale;
            const minSize = 20;

            let newX = resizeStart.objX;
            let newY = resizeStart.objY;
            let newW = resizeStart.objW;
            let newH = resizeStart.objH;

            if (resizeHandle === 'nw') {
                newX = resizeStart.objX + dx; newY = resizeStart.objY + dy;
                newW = Math.max(minSize, resizeStart.objW - dx);
                newH = Math.max(minSize, resizeStart.objH - dy);
            } else if (resizeHandle === 'ne') {
                newY = resizeStart.objY + dy;
                newW = Math.max(minSize, resizeStart.objW + dx);
                newH = Math.max(minSize, resizeStart.objH - dy);
            } else if (resizeHandle === 'sw') {
                newX = resizeStart.objX + dx;
                newW = Math.max(minSize, resizeStart.objW - dx);
                newH = Math.max(minSize, resizeStart.objH + dy);
            } else if (resizeHandle === 'se') {
                newW = Math.max(minSize, resizeStart.objW + dx);
                newH = Math.max(minSize, resizeStart.objH + dy);
            }

            // Create a scaled version of the image
            const offscreen = document.createElement('canvas');
            offscreen.width = Math.round(newW);
            offscreen.height = Math.round(newH);
            const offCtx = offscreen.getContext('2d');
            if (offCtx) {
                offCtx.drawImage(resizingObject.image, 0, 0, Math.round(newW), Math.round(newH));
                const resizedImg = new window.Image();
                resizedImg.src = offscreen.toDataURL('image/png');
                resizedImg.onload = () => {
                    setCanvasObjects(prev => prev.map(obj =>
                        obj.id === resizingObject.id ? { ...obj, image: resizedImg, x: newX, y: newY } : obj
                    ));
                    setSelectedObject(prev => prev?.id === resizingObject.id ? { ...prev, image: resizedImg, x: newX, y: newY } : prev);
                };
            }
            return;
        }

        // Crop mode dragging
        if (cropMode && cropDragHandle && cropDragStart && cropRect) {
            const dx = (x - cropDragStart.x) / scale;
            const dy = (y - cropDragStart.y) / scale;
            const newRect = { ...cropRect };
            switch (cropDragHandle) {
                case 'move': newRect.x = cropDragStart.rectX + dx; newRect.y = cropDragStart.rectY + dy; break;
                case 'nw': newRect.x = cropDragStart.rectX + dx; newRect.y = cropDragStart.rectY + dy; newRect.w = cropDragStart.rectW - dx; newRect.h = cropDragStart.rectH - dy; break;
                case 'ne': newRect.y = cropDragStart.rectY + dy; newRect.w = cropDragStart.rectW + dx; newRect.h = cropDragStart.rectH - dy; break;
                case 'sw': newRect.x = cropDragStart.rectX + dx; newRect.w = cropDragStart.rectW - dx; newRect.h = cropDragStart.rectH + dy; break;
                case 'se': newRect.w = cropDragStart.rectW + dx; newRect.h = cropDragStart.rectH + dy; break;
                case 'n': newRect.y = cropDragStart.rectY + dy; newRect.h = cropDragStart.rectH - dy; break;
                case 's': newRect.h = cropDragStart.rectH + dy; break;
                case 'w': newRect.x = cropDragStart.rectX + dx; newRect.w = cropDragStart.rectW - dx; break;
                case 'e': newRect.w = cropDragStart.rectW + dx; break;
            }
            if (newRect.w < 50) newRect.w = 50;
            if (newRect.h < 50) newRect.h = 50;
            setCropRect(newRect);
            return;
        }

        if (isDragging) {
            const dx = clientX - lastPanPoint.x;
            const dy = clientY - lastPanPoint.y;
            setPanX((prev) => prev + dx);
            setPanY((prev) => prev + dy);
            setLastPanPoint({ x: clientX, y: clientY });
            return;
        }

        if (draggedObject) {
            const newX = (x - dragOffset.x - panX) / scale;
            const newY = (y - dragOffset.y - panY) / scale;
            setCanvasObjects(prev =>
                prev.map(obj =>
                    obj.id === draggedObject.id ? { ...obj, x: newX, y: newY } : obj
                )
            );
            return;
        }

        if (isPainting && (currentTool === 'retouch' || currentTool === 'erase') && selectedObject) {
            const imagePos = screenToObjectCoordinates(x, y, selectedObject);
            if (isPointInObject(imagePos.x, imagePos.y, selectedObject)) {
                addPointToCurrentLine(imagePos.x, imagePos.y);
            }
        }
    };

    const handleMouseMove = (e: React.MouseEvent<HTMLCanvasElement>) => {
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        handlePointerMove(e.clientX - rect.left, e.clientY - rect.top, e.clientX, e.clientY);
    };

    const handleMouseUp = () => {
        if (cropDragHandle) { setCropDragHandle(null); setCropDragStart(null); }
        if (resizingObject) { setResizingObject(null); setResizeHandle(null); setResizeStart(null); }
        setIsDragging(false);
        if (isPainting) setIsPainting(false);
        if (draggedObject) setDraggedObject(null);
    };

    // Touch event handlers
    const handleTouchStart = (e: React.TouchEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();

        if (e.touches.length === 2) {
            // Two-finger pan start — store midpoint as last pan point
            const t0 = e.touches[0]; const t1 = e.touches[1];
            setIsDragging(true);
            setLastPanPoint({ x: (t0.clientX + t1.clientX) / 2, y: (t0.clientY + t1.clientY) / 2 });
            return;
        }

        const t = e.touches[0];
        const x = t.clientX - rect.left;
        const y = t.clientY - rect.top;

        // Resize handle?
        if (currentTool === 'select' && selectedObject && !selectedObject.isMainImage) {
            const rHandle = getResizeHandleAtPoint(x, y, selectedObject);
            if (rHandle) {
                setResizeHandle(rHandle);
                setResizingObject(selectedObject);
                setResizeStart({ mouseX: x, mouseY: y, objX: selectedObject.x, objY: selectedObject.y, objW: selectedObject.image.width, objH: selectedObject.image.height });
                return;
            }
        }

        const clickedObject = getObjectAtPoint(x, y);
        if (clickedObject) {
            setSelectedObject(clickedObject);
            if ((currentTool === 'retouch' || currentTool === 'erase') && clickedObject === selectedObject) {
                const imagePos = screenToObjectCoordinates(x, y, clickedObject);
                if (isPointInObject(imagePos.x, imagePos.y, clickedObject)) {
                    setIsPainting(true);
                    startNewLine(imagePos.x, imagePos.y, clickedObject.id);
                }
            } else if (currentTool === 'select') {
                setDraggedObject(clickedObject);
                setDragOffset({ x: x - (panX + clickedObject.x * scale), y: y - (panY + clickedObject.y * scale) });
            }
        } else {
            setSelectedObject(null);
        }
    };

    const handleTouchMove = (e: React.TouchEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();

        if (e.touches.length === 2) {
            const t0 = e.touches[0]; const t1 = e.touches[1];
            const midX = (t0.clientX + t1.clientX) / 2;
            const midY = (t0.clientY + t1.clientY) / 2;
            const dx = midX - lastPanPoint.x;
            const dy = midY - lastPanPoint.y;
            setPanX(prev => prev + dx);
            setPanY(prev => prev + dy);
            setLastPanPoint({ x: midX, y: midY });
            return;
        }

        const t = e.touches[0];
        handlePointerMove(t.clientX - rect.left, t.clientY - rect.top, t.clientX, t.clientY);
    };

    const handleTouchEnd = (e: React.TouchEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        handleMouseUp();
    };

    // Add an image element to canvas, position at cursor if available, else center
    const addImageToCanvas = (img: HTMLImageElement, label = 'Image') => {
        if (!canvas) return;
        let targetX: number;
        let targetY: number;
        if (cursorPosition.x !== 0 || cursorPosition.y !== 0) {
            const { x, y } = screenToCanvasCoordinates(
                cursorPosition.x,
                cursorPosition.y,
            );
            targetX = x - img.width / 2;
            targetY = y - img.height / 2;
        } else {
            const centerCanvasX = (canvas.width / 2 - panX) / scale;
            const centerCanvasY = (canvas.height / 2 - panY) / scale;
            targetX = centerCanvasX - img.width / 2;
            targetY = centerCanvasY - img.height / 2;
        }

        const newObj: CanvasObject = {
            id: generateObjectId(),
            image: img,
            x: targetX,
            y: targetY,
            label,
            isMainImage: false,
        };

        setCanvasObjects((prev) => [...prev, newObj]);
        setSelectedObject(newObj);
        setShowUploadZone(false);
        debug.log('[Canvas] Added image to canvas', {
            id: newObj.id,
            label,
            x: targetX,
            y: targetY,
            w: img.width,
            h: img.height,
        });
    };

    const handleWheel = (e: React.WheelEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        if (!canvas) return;

        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const scaleBy = CANVAS_DEFAULTS.ZOOM_SCALE_FACTOR;
        const direction = e.deltaY > 0 ? -1 : 1;
        const oldScale = scale;
        const newScale =
            direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;
        const clampedScale = Math.max(CANVAS_DEFAULTS.ZOOM_MIN, Math.min(CANVAS_DEFAULTS.ZOOM_MAX, newScale));

        setScale(clampedScale);
        const scaleChange = clampedScale / oldScale;
        setPanX(mouseX - (mouseX - panX) * scaleChange);
        setPanY(mouseY - (mouseY - panY) * scaleChange);
    };

    const startNewLine = (x: number, y: number, objectId?: number) => {
        // Save current state to undo stack before adding new line
        setUndoStack((prev) => {
            const newStack = [...prev, lines];
            // Limit undo stack to prevent memory issues
            if (newStack.length > CANVAS_DEFAULTS.UNDO_LIMIT) {
                newStack.shift();
            }
            return newStack;
        });
        // Clear redo stack when new action is performed
        setRedoStack([]);

        const newLine: BrushLine = {
            id: generateObjectId(),
            points: [x, y],
            brushSize: brushSize,
            brushOpacity: brushOpacity / 100,
            tool: 'brush',
            objectId: objectId || null,
        };
        debug.log('[Paint] New line created', {
            id: newLine.id,
            objectId: newLine.objectId,
            brushSize: newLine.brushSize,
            opacity: newLine.brushOpacity,
        });
        setLines((prev) => [...prev, newLine]);
    };

    const addPointToCurrentLine = (x: number, y: number) => {
        setLines((prev) => {
            if (prev.length === 0) return prev;
            const updated = [...prev];
            const lastLine = updated[updated.length - 1];
            lastLine.points.push(x, y);
            return updated;
        });
    };

    const fitToScreen = () => {
        if (canvasObjects.length === 0 || !canvas) return;

        let minX = Infinity,
            minY = Infinity,
            maxX = -Infinity,
            maxY = -Infinity;
        canvasObjects.forEach((obj) => {
            minX = Math.min(minX, obj.x);
            minY = Math.min(minY, obj.y);
            maxX = Math.max(maxX, obj.x + obj.image.width);
            maxY = Math.max(maxY, obj.y + obj.image.height);
        });

        const totalWidth = maxX - minX;
        const totalHeight = maxY - minY;
        const containerWidth = canvas.width - CANVAS_DEFAULTS.CANVAS_PADDING;
        const containerHeight = canvas.height - CANVAS_DEFAULTS.CANVAS_PADDING;

        const scaleX = containerWidth / totalWidth;
        const scaleY = containerHeight / totalHeight;
        const newScale = Math.min(scaleX, scaleY, 1);

        setScale(newScale);
        setPanX((canvas.width - totalWidth * newScale) / 2 - minX * newScale);
        setPanY((canvas.height - totalHeight * newScale) / 2 - minY * newScale);
    };

    const zoomIn = () => setScale((prev) => Math.min(CANVAS_DEFAULTS.ZOOM_MAX, prev * CANVAS_DEFAULTS.ZOOM_FACTOR));
    const zoomOut = () => setScale((prev) => Math.max(CANVAS_DEFAULTS.ZOOM_MIN, prev / CANVAS_DEFAULTS.ZOOM_FACTOR));
    const resetZoom = () => setScale(1);

    const undo = () => {
        if (undoStack.length > 0) {
            const newUndoStack = [...undoStack];
            const lastState = newUndoStack.pop()!;
            setRedoStack((prev) => [...prev, lines]);
            setLines(lastState);
            setUndoStack(newUndoStack);
        }
    };

    const redo = () => {
        if (redoStack.length > 0) {
            const newRedoStack = [...redoStack];
            const nextState = newRedoStack.pop()!;
            setUndoStack((prev) => [...prev, lines]);
            setLines(nextState);
            setRedoStack(newRedoStack);
        }
    };

    const resetDrawing = async () => {
        if (lines.length > 0) {
            const confirmed = await showConfirm(
                'Clear Brush Strokes',
                'Are you sure you want to clear all brush strokes? This action cannot be undone.',
                'Clear',
                true
            );
            if (confirmed) {
                setUndoStack((prev) => [...prev, lines]);
                setLines([]);
                setRedoStack([]);
            }
        }
    };

    // Create a composite image with green highlights where the user brushed
    const createGreenHighlightedImage = (
        target: CanvasObject | null,
    ): HTMLCanvasElement | null => {
        if (!target) return null;
        const compositeCanvas = document.createElement('canvas');
        compositeCanvas.width = target.image.width;
        compositeCanvas.height = target.image.height;
        const compositeCtx = compositeCanvas.getContext('2d');
        if (!compositeCtx) return null;

        // Draw the original image first
        compositeCtx.drawImage(target.image, 0, 0);

        // Draw green highlights over the brushed areas
        compositeCtx.strokeStyle = 'rgb(0, 255, 0)'; // Bright green for AI to easily detect
        compositeCtx.lineCap = 'round';
        compositeCtx.lineJoin = 'round';

        const relevant = lines.filter((l) => l.objectId === target.id);
        relevant.forEach((line) => {
            if (line.points.length > 1) {
                compositeCtx.lineWidth = line.brushSize;
                compositeCtx.globalAlpha = 1; // Full opacity green
                compositeCtx.beginPath();
                compositeCtx.moveTo(line.points[0], line.points[1]);
                for (let i = 2; i < line.points.length; i += 2) {
                    compositeCtx.lineTo(line.points[i], line.points[i + 1]);
                }
                compositeCtx.stroke();
            }
        });

        // Reset alpha
        compositeCtx.globalAlpha = 1;
        debug.log('[Green Highlight] Created composite image', {
            width: compositeCanvas.width,
            height: compositeCanvas.height,
            strokes: relevant.length,
        });
        return compositeCanvas;
    };

    // Create a mask canvas (white where brushed, black elsewhere) for the selected object
    // Used by Replace Text and AI Generate functions
    const createMaskCanvas = (
        target: CanvasObject | null,
    ): HTMLCanvasElement | null => {
        if (!target) return null;
        const maskCanvas = document.createElement('canvas');
        maskCanvas.width = target.image.width;
        maskCanvas.height = target.image.height;
        const maskCtx = maskCanvas.getContext('2d');
        if (!maskCtx) return null;

        // Background black
        maskCtx.fillStyle = 'black';
        maskCtx.fillRect(0, 0, maskCanvas.width, maskCanvas.height);

        // Draw strokes in white
        maskCtx.strokeStyle = 'white';
        maskCtx.lineCap = 'round';
        maskCtx.lineJoin = 'round';

        const relevant = lines.filter((l) => l.objectId === target.id);
        relevant.forEach((line) => {
            if (line.points.length > 1) {
                maskCtx.lineWidth = line.brushSize;
                maskCtx.globalAlpha = line.brushOpacity ?? 1;
                maskCtx.beginPath();
                maskCtx.moveTo(line.points[0], line.points[1]);
                for (let i = 2; i < line.points.length; i += 2) {
                    maskCtx.lineTo(line.points[i], line.points[i + 1]);
                }
                maskCtx.stroke();
            }
        });

        // Reset alpha
        maskCtx.globalAlpha = 1;
        debug.log('[Mask] Created mask canvas', {
            width: maskCanvas.width,
            height: maskCanvas.height,
            strokes: relevant.length,
        });
        return maskCanvas;
    };

    const computeMaskStats = (maskCanvas: HTMLCanvasElement) => {
        const maskCtx = maskCanvas.getContext('2d');
        if (!maskCtx) return null;
        const { width, height } = maskCanvas;
        const imgData = maskCtx.getImageData(0, 0, width, height);
        const data = imgData.data;
        let count = 0;
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i],
                g = data[i + 1],
                b = data[i + 2];
            if (r + g + b > 30) count++;
        }
        const total = width * height;
        const pct = total > 0 ? Math.round((count / total) * 10000) / 100 : 0;
        return { whitePixels: count, totalPixels: total, coveragePct: pct };
    };

    // Download the mask for the currently selected object
    const downloadMask = () => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            debug.log('[Mask] Download attempted without selection');
            return;
        }
        const relevant = lines.filter((l) => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert('No Mask', 'No brush strokes found for the selected image.', 'warning');
            debug.log('[Mask] Download attempted without mask');
            return;
        }
        const maskCanvas = createMaskCanvas(selectedObject);
        if (!maskCanvas) return;
        const stats = computeMaskStats(maskCanvas);
        debug.log('[Mask] Downloading mask', stats);
        const url = maskCanvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = `mask-${Date.now()}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // Modal helpers
    const showPrompt = (
        title: string,
        description: string,
        placeholder: string = '',
        defaultValue: string = '',
    ): Promise<string> => {
        return new Promise((resolve) => {
            debug.log('[Prompt] Open', { title, description });
            setPromptModal({
                isOpen: true,
                title,
                description,
                placeholder,
                defaultValue,
                resolve,
            });
        });
    };

    const showAlert = (
        title: string,
        message: string,
        type: 'info' | 'warning' | 'error' = 'info',
        actionType?: string,
    ) => {
        setAlertModal({
            isOpen: true,
            title,
            message,
            type,
        });

        // Add to action history for toast notifications
        if (actionType) {
            const timestamp = Date.now();
            setActionHistory((prev) => [
                { type: actionType, message: `${title}: ${message}`, time: timestamp },
                ...prev.slice(0, 4), // Keep only last 5 items
            ]);
        }
    };

    const showConfirm = (
        title: string,
        message: string,
        confirmText: string = 'Confirm',
        isDanger: boolean = false,
    ): Promise<boolean> => {
        return new Promise((resolve) => {
            debug.log('[Confirm] Open', { title, message });
            setConfirmModal({
                isOpen: true,
                title,
                message,
                confirmText,
                isDanger,
                resolve,
            });
        });
    };

    // Action handlers
    const handleErase = async () => {
        if (!selectedObject) {
            showAlert(
                'No Selection',
                'Please select an image first.',
                'warning',
            );
            debug.log('[Erase] No selection');
            return;
        }

        const relevant = lines.filter((l) => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert(
                'No Mask',
                'Please paint over the areas you want to erase first.',
                'warning',
            );
            debug.log('[Erase] No mask for selected object');
            return;
        }

        // Start erasing immediately without confirmation
        setIsGenerating(true);
        setGeneratingType('erase');
        startGenTimer();
        debug.log('[Erase] Starting erase with strokes', {
            strokeCount: relevant.length,
            points: relevant.reduce((a, l) => a + l.points.length / 2, 0),
        });

        try {
            // Create composite image with green highlights
            const compositeCanvas = createGreenHighlightedImage(selectedObject);
            if (!compositeCanvas) throw new Error('Failed to create composite image');

            // Convert composite image to base64
            let compositeImage = '';
            try {
                compositeImage = compositeCanvas.toDataURL('image/png');
            } catch (e) {
                debug.error(
                    '[Erase] toDataURL failed for composite image, likely CORS taint',
                    e,
                );
                showAlert(
                    'Image Security Error',
                    'Cannot read the image due to browser security (CORS). Try uploading a local image or ensure the image URL allows cross-origin access.',
                    'error',
                );
                setIsGenerating(false);
                return;
            }

            debug.log('[Erase] Composite image created', {
                length: compositeImage.length,
            });

            // Make API call with only the composite image
            const response = await fetch('/api/erase-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    image: compositeImage,
                }),
            });

            if (!response.ok) {
                let details = response.statusText;
                try {
                    const text = await response.text();
                    try {
                        const json = JSON.parse(text);
                        details = json?.error || json?.message || text;
                    } catch {
                        details = text || details;
                    }
                } catch {
                    // ignore
                }
                throw new Error(`API error (${response.status}): ${String(details).slice(0, 400)}`);
            }

            const result = await response.json();
            debug.log('[Erase] API result', result);

            // Display generated result
            if (result.generatedImage) {
                const newImage = new window.Image();
                newImage.onload = () => {
                    // Add generated image as a new object next to the original
                    const sourceObject = selectedObject;
                    if (sourceObject) {
                        const generatedObj = {
                            id: generateObjectId(),
                            image: newImage,
                            x: sourceObject.x + sourceObject.image.width + CANVAS_DEFAULTS.ERASE_GAP,
                            y: sourceObject.y,
                            label: `${sourceObject.label || 'Image'} (Edited)`,
                            isMainImage: false,
                        };
                        setCanvasObjects((prev) => [...prev, generatedObj]);
                        setSelectedObject(generatedObj);
                        // Clear brush strokes for this object
                        setLines(
                            lines.filter((l) => l.objectId !== sourceObject.id),
                        );
                    }
                };
                newImage.src = result.generatedImage;
            }
        } catch (error) {
            debug.error('Erase error:', error);
            showAlert(
                'Error',
                error instanceof Error
                    ? error.message
                    : 'Failed to erase areas',
                'error',
            );
        } finally {
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
            debug.log('[Erase] Finished');
        }
    };

    const handleAiEdit = async () => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            debug.log('[AI Edit] No selection');
            return;
        }

        const userPrompt = await showPrompt(
            'AI Edit',
            'Describe how you want to edit the selected image:',
            'E.g. make it look like a watercolor illustration',
            '',
        );

        if (!userPrompt) {
            debug.log('[AI Edit] Prompt cancelled');
            return;
        }

        setIsGenerating(true);
        setGeneratingType('ai-edit');
        startGenTimer();
        debug.log('[AI Edit] Starting', { prompt: userPrompt });

        try {
            // If the user painted strokes on this object, use green-highlight composite
            // for area-specific editing; otherwise send the plain image for full editing.
            const hasStrokes = lines.some((l) => l.objectId === selectedObject.id);
            let imageToSend = '';

            if (hasStrokes) {
                debug.log('[AI Edit] Strokes detected — using green-highlighted composite for area edit');
                const compositeCanvas = createGreenHighlightedImage(selectedObject);
                if (!compositeCanvas) throw new Error('Failed to create green-highlighted composite');
                try {
                    imageToSend = compositeCanvas.toDataURL('image/png');
                } catch (e) {
                    debug.error('[AI Edit] toDataURL failed for composite, likely CORS taint', e);
                    showAlert(
                        'Image Security Error',
                        'Cannot read the image due to browser security (CORS). Try uploading a local image.',
                        'error',
                    );
                    return;
                }
            } else {
                debug.log('[AI Edit] No strokes — sending full image for global edit');
                try {
                    imageToSend = imageToDataUrl(selectedObject.image);
                } catch (e) {
                    debug.error('[AI Edit] toDataURL failed for original image, likely CORS taint', e);
                    showAlert(
                        'Image Security Error',
                        'Cannot read the original image due to browser security (CORS). Try uploading a local image or ensure the image URL allows cross-origin access.',
                        'error',
                    );
                    return;
                }
            }

            const prompt = hasStrokes
                ? `The bright green (RGB 0,255,0) brush strokes in this image mark the exact area to edit. Apply the following change ONLY inside the green zone: ${userPrompt}. Rules: (1) remove all green from the output, (2) leave every pixel outside the green zone completely unchanged, (3) output must be the same pixel dimensions as the input.`
                : userPrompt;

            const response = await fetch('/api/ai-edit-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    image: imageToSend,
                    prompt,
                }),
            });

            if (!response.ok) {
                let details = response.statusText;
                try {
                    const text = await response.text();
                    try {
                        const json = JSON.parse(text);
                        details = json?.error || json?.message || text;
                    } catch {
                        details = text || details;
                    }
                } catch {
                    // ignore
                }
                throw new Error(
                    `API error (${response.status}): ${String(details).slice(0, 400)}`,
                );
            }

            const result = await response.json();
            debug.log('[AI Edit] API result', result);

            if (result.generatedImage) {
                const newImage = new window.Image();
                newImage.onload = () => {
                    const sourceObject = selectedObject;
                    if (!sourceObject) return;

                    const generatedObj = {
                        id: generateObjectId(),
                        image: newImage,
                        x:
                            sourceObject.x +
                            sourceObject.image.width +
                            CANVAS_DEFAULTS.ERASE_GAP,
                        y: sourceObject.y,
                        label: `${sourceObject.label || 'Image'} (AI Edit)`,
                        isMainImage: false,
                    };

                    setCanvasObjects((prev) => [...prev, generatedObj]);
                    setSelectedObject(generatedObj);
                    // Clear brush strokes if area-specific edit
                    if (hasStrokes) {
                        setLines((prev) => prev.filter((l) => l.objectId !== sourceObject.id));
                    }
                };
                newImage.src = result.generatedImage;
            } else {
                throw new Error('No generated image in API response');
            }
        } catch (error) {
            debug.error('[AI Edit] Error:', error);
            showAlert(
                'Error',
                error instanceof Error ? error.message : 'AI edit failed',
                'error',
            );
        } finally {
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
            debug.log('[AI Edit] Finished');
        }
    };

    const handleReplaceText = async () => {
        if (!selectedObject) {
            showAlert(
                'No Selection',
                'Please select an image first.',
                'warning',
            );
            debug.log('[ReplaceText] No selection');
            return;
        }

        const relevant = lines.filter((l) => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert(
                'No Mask',
                'Please paint over the text you want to replace.',
                'warning',
            );
            debug.log('[ReplaceText] No mask for selected object');
            return;
        }

        try {
            const textToReplace = await showPrompt(
                'Replace Text',
                'Type the new text to place in the painted area (e.g. if the image says "Hello", type "Goodbye"):',
                'e.g. New York, Sale 50%, Grand Opening...',
                '',
            );

            if (!textToReplace) {
                debug.log('[ReplaceText] Prompt cancelled by user');
                return;
            }
            debug.log('[ReplaceText] Prompt value', textToReplace);

            // Start generation immediately without confirmation
            setIsGenerating(true);
            setGeneratingType('replace-text');
            startGenTimer();
            debug.log('[ReplaceText] Starting with strokes', {
                strokeCount: relevant.length,
            });

            // Build green-highlighted composite (same as Erase)
            const compositeCanvas = createGreenHighlightedImage(selectedObject);
            if (!compositeCanvas) throw new Error('Failed to create green-highlighted composite');

            let compositeImage = '';
            try {
                compositeImage = compositeCanvas.toDataURL('image/png');
            } catch (e) {
                debug.error('[ReplaceText] toDataURL failed, likely CORS taint', e);
                showAlert(
                    'Image Security Error',
                    'Cannot read the image due to browser security (CORS). Try uploading a local image.',
                    'error',
                );
                setIsGenerating(false);
                return;
            }

            const prompt = `The bright green (RGB 0,255,0) brush strokes in this image mark the exact area to edit. Replace ONLY the green-highlighted area with the text "${textToReplace}". Rules: (1) remove all green colour from the output, (2) match the surrounding font style, size, color, and spacing exactly, (3) leave every pixel outside the green zone completely unchanged, (4) output must be the same pixel dimensions as the input.`;

            // Make API call
            debug.log('[ReplaceText] Sending API request...');
            let response;
            try {
                response = await fetch('/api/generate-with-mask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        image: compositeImage,
                        prompt,
                    }),
                });
            } catch (fetchError) {
                debug.error('[ReplaceText] Fetch error:', fetchError);
                throw new Error(
                    `Network error: ${fetchError instanceof Error ? fetchError.message : 'Unknown'}`,
                );
            }

            debug.log('[ReplaceText] Response received:', response);

            debug.log(
                '[ReplaceText] Response status:',
                response.status,
                response.statusText,
            );

            if (!response.ok) {
                let errorText = 'Unknown error';
                try {
                    errorText = await response.text();
                    debug.error(
                        '[ReplaceText] Error response body:',
                        errorText,
                    );
                } catch (e) {
                    debug.error(
                        '[ReplaceText] Could not read error response',
                    );
                }
                throw new Error(
                    `API error (${response.status}): ${errorText.substring(0, 200)}`,
                );
            }

            const result = await response.json();
            debug.log('[ReplaceText] API result', result);

            // Display generated result
            if (result.generatedImage) {
                debug.log('[ReplaceText] Loading generated image...');
                const newImage = new Image();
                newImage.onload = () => {
                    debug.log(
                        '[ReplaceText] Image loaded, dimensions:',
                        newImage.width,
                        'x',
                        newImage.height,
                    );
                    // Add generated image as a new object next to the original
                    const sourceObject = selectedObject;
                    if (sourceObject) {
                        const generatedObj = {
                            id: generateObjectId(),
                            image: newImage,
                            x: sourceObject.x + sourceObject.image.width + CANVAS_DEFAULTS.ERASE_GAP,
                            y: sourceObject.y,
                            label: `${sourceObject.label || 'Image'} (Edited)`,
                            isMainImage: false,
                        } as CanvasObject;
                        debug.log(
                            '[ReplaceText] Adding generated object next to source',
                        );
                        setCanvasObjects((prev) => [...prev, generatedObj]);
                        setSelectedObject(generatedObj);

                        // Clear brush strokes for the source object
                        const oldLineCount = lines.length;
                        const filteredLines = lines.filter(
                            (l) => l.objectId !== sourceObject.id,
                        );
                        debug.log(
                            '[ReplaceText] Clearing lines:',
                            oldLineCount,
                            '→',
                            filteredLines.length,
                        );
                        setLines(filteredLines);

                        // Force redraw
                        debug.log('[ReplaceText] Triggering canvas redraw');
                        if (ctx) drawScene();
                    }
                };
                newImage.onerror = (e) => {
                    debug.error('[ReplaceText] Image load error:', e);
                };
                debug.log('[ReplaceText] Setting image src...');
                newImage.src = result.generatedImage;
            }
        } catch (error) {
            if (error === null) return; // User cancelled
            debug.error('Replace text error:', error);
            showAlert(
                'Error',
                error instanceof Error
                    ? error.message
                    : 'Failed to replace text',
                'error',
            );
        } finally {
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
            debug.log('[ReplaceText] Finished');
        }
    };

    // Centralized API call utility
    const callApi = async (url: string, body: ApiBody) => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            let errorData: any = {};
            try {
                errorData = await response.json();
            } catch (e) {
                // Ignore parse errors
            }
            throw new Error(errorData.message || response.statusText || 'API request failed');
        }

        return await response.json();
    };

    // Crop/Resize Canvas (crop if shrinking, outpaint if expanding)
    const handleCropResize = async () => {
        if (isGenerating) return;
        const target = selectedObject || canvasObjects.find(o => o.isMainImage) || null;
        if (!target) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            return;
        }

        // Enter interactive crop mode
        // Initialize crop rectangle around the selected object
        const initialRect = {
            x: target.x,
            y: target.y,
            w: target.image.width,
            h: target.image.height,
        };
        setCropRect(initialRect);
        setCropIntent('crop');
        setCropMode(true);
    };

    const handleConfirmCrop = async () => {
        if (!cropRect || isGenerating) return;
        const target = selectedObject || canvasObjects.find(o => o.isMainImage) || null;
        if (!target) return;

        const srcW = target.image.width;
        const srcH = target.image.height;
        const newW = Math.round(cropRect.w);
        const newH = Math.round(cropRect.h);

        // offsetX/Y = where original image's (0,0) will be placed in the new canvas
        // If crop rect starts at (10, 20), original (0,0) needs to be at (-10, -20) in new canvas
        const offsetX = Math.round(-cropRect.x);
        const offsetY = Math.round(-cropRect.y);

        setCropMode(false);
        setCropRect(null);
        setGeneratingType('crop');
        setIsGenerating(true);
        startGenTimer();

        try {
            const imageDataUrl = imageToDataUrl(target.image);
            const result = await callApi('/api/resize-canvas', {
                image: imageDataUrl,
                targetWidth: newW,
                targetHeight: newH,
                offsetX,
                offsetY,
            });

            if (result.resultImage) {
                const outImg = new Image();
                outImg.crossOrigin = 'anonymous';
                outImg.onload = () => {
                    const newObject: CanvasObject = {
                        id: generateObjectId(),
                        image: outImg,
                        x: target.x + target.image.width + CANVAS_DEFAULTS.OBJECT_SPACING,
                        y: target.y,
                        label: result.mode === 'crop' ? 'Cropped Image' : 'Resized (AI) Image',
                        isMainImage: false,
                    };
                    setCanvasObjects((prev) => [...prev, newObject]);
                    setSelectedObject(newObject);
                    showAlert('Success', result.mode === 'crop' ? 'Image cropped.' : 'Canvas expanded with AI.', 'info', 'crop');
                    setIsGenerating(false);
                    setGeneratingType(null);
                    stopGenTimer();
                };
                outImg.onerror = () => {
                    showAlert('Error', 'Failed to load processed image', 'error', 'crop');
                    setIsGenerating(false);
                    setGeneratingType(null);
                    stopGenTimer();
                };
                outImg.src = result.resultImage;
            }
        } catch (error) {
            showAlert('Error', error instanceof Error ? error.message : 'Failed to crop/resize', 'error', 'crop');
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
        }
    };

    const handleConfirmExpand = async () => {
        if (!cropRect || isGenerating) return;
        const target = selectedObject || canvasObjects.find(o => o.isMainImage) || null;
        if (!target) return;

        const newW = Math.max(1, Math.round(cropRect.w));
        const newH = Math.max(1, Math.round(cropRect.h));

        // Place the original image inside the new canvas at the same relative position
        // (cropRect is in canvas coords; target is positioned at target.x/y)
        const offsetX = Math.round(target.x - cropRect.x);
        const offsetY = Math.round(target.y - cropRect.y);

        setCropMode(false);
        setCropRect(null);
        setGeneratingType('expand');
        setIsGenerating(true);
        startGenTimer();

        try {
            // Build a black-filled expanded canvas image
            const expandedCanvas = document.createElement('canvas');
            expandedCanvas.width = newW;
            expandedCanvas.height = newH;
            const expandedCtx = expandedCanvas.getContext('2d');
            if (!expandedCtx) throw new Error('Failed to create canvas context');

            expandedCtx.fillStyle = 'black';
            expandedCtx.fillRect(0, 0, newW, newH);
            expandedCtx.drawImage(target.image, offsetX, offsetY);

            let expandedDataUrl = '';
            try {
                expandedDataUrl = expandedCanvas.toDataURL('image/png');
            } catch (e) {
                debug.error('[Expand] toDataURL failed, likely CORS taint', e);
                showAlert(
                    'Image Security Error',
                    'Cannot read the original image due to browser security (CORS). Try uploading a local image or ensure the image URL allows cross-origin access.',
                    'error',
                    'expand',
                );
                return;
            }

            const prompt =
                'Fill in (outpaint) all pure black areas seamlessly to expand the image. Keep the existing non-black content unchanged. Match the original style, lighting, perspective, and textures.';

            const response = await fetch('/api/ai-edit-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    image: expandedDataUrl,
                    prompt,
                }),
            });

            if (!response.ok) {
                let details = response.statusText;
                try {
                    const text = await response.text();
                    try {
                        const json = JSON.parse(text);
                        details = json?.error || json?.message || text;
                    } catch {
                        details = text || details;
                    }
                } catch {
                    // ignore
                }
                throw new Error(
                    `API error (${response.status}): ${String(details).slice(0, 400)}`,
                );
            }

            const result = await response.json();
            debug.log('[Expand] API result', result);

            if (!result.generatedImage) {
                throw new Error('No generated image in API response');
            }

            const expandedImg = new Image();
            expandedImg.crossOrigin = 'anonymous';
            expandedImg.onload = () => {
                const newObject: CanvasObject = {
                    id: generateObjectId(),
                    image: expandedImg,
                    x: target.x + target.image.width + CANVAS_DEFAULTS.ERASE_GAP,
                    y: target.y,
                    label: 'Expanded Image',
                    isMainImage: false,
                };
                setCanvasObjects((prev) => [...prev, newObject]);
                setSelectedObject(newObject);
                showAlert('Success', 'Image expanded successfully!', 'info', 'expand');
            };
            expandedImg.onerror = () => {
                showAlert('Error', 'Failed to load expanded image', 'error', 'expand');
            };
            expandedImg.src = result.generatedImage;
        } catch (error) {
            showAlert(
                'Error',
                error instanceof Error ? error.message : 'Failed to expand image',
                'error',
                'expand',
            );
        } finally {
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
        }
    };

    const handleCancelCrop = () => {
        setCropMode(false);
        setCropRect(null);
        setCropDragHandle(null);
        setCropDragStart(null);
    };

    // Expand Image (crop-like mode: user enlarges the rectangle; black padding gets filled by AI)
    const handleExpandImage = async () => {
        if (isGenerating) return;
        const target = selectedObject || canvasObjects.find(o => o.isMainImage) || null;
        if (!target) {
            showAlert('No Selection', 'Please select an image first.', 'warning', 'expand');
            return;
        }

        const initialRect = {
            x: target.x,
            y: target.y,
            w: target.image.width,
            h: target.image.height,
        };
        setCropRect(initialRect);
        setCropIntent('expand');
        setCropMode(true);
    };

    // Enhancement: Upscale Image using FAL AI
    const handleUpscaleImage = async () => {
        if (isGenerating) return;

        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning', 'upscale');
            return;
        }

        setUpscaleModal({ isOpen: true, selectedFactor: 2 });
    };

    const handleConfirmUpscale = async (factor: number) => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            return;
        }

        const creditCost = factor;

        setUpscaleModal({ isOpen: false, selectedFactor: factor });
        setGeneratingType('upscale');
        setIsGenerating(true);
        startGenTimer();
        debug.log('[Upscale] Starting FAL AI upscale', { factor, creditCost });

        try {
            let originalImage = '';
            try {
                originalImage = imageToDataUrl(selectedObject.image);
            } catch (e) {
                debug.error('[Upscale] toDataURL failed', e);
                showAlert('Image Security Error', 'Cannot read image (CORS issue). Try uploading a local image.', 'error', 'upscale');
                setIsGenerating(false);
                setGeneratingType(null);
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // Submit upscale job via server-side proxy (FAL API key stays on the server)
            const submitRes = await fetch('/api/fal/upscale/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ image_url: originalImage, upscale_factor: factor }),
            });

            if (!submitRes.ok) {
                const err = await submitRes.json().catch(() => ({}));
                throw new Error(err.message || 'Failed to submit upscale request');
            }

            const submitData = await submitRes.json();
            const { request_id } = submitData;

            if (!request_id) throw new Error('No request ID returned from upscale service');

            debug.log('[Upscale] Request submitted', { request_id });

            let result = null;
            let attempts = 0;
            while (attempts < 120) {
                const statusRes = await fetch(`/api/fal/upscale/status/${encodeURIComponent(request_id)}`, {
                    headers: { 'X-CSRF-TOKEN': csrf },
                });
                const statusData = await statusRes.json();
                debug.log('[Upscale] Status check', { attempts, statusObj: statusData });

                if (statusData.status === 'COMPLETED') {
                    const responseUrl = statusData.response_url;
                    const fullResponse = await fetch(responseUrl);
                    result = await fullResponse.json();
                    debug.log('[Upscale] Processing completed', { result });
                    break;
                }
                if (attempts % 10 === 0) {
                    debug.log('[Upscale] Processing...', { attempts, status: statusData.status });
                }
                await new Promise(r => setTimeout(r, 1000));
                attempts++;
            }

            if (!result) {
                debug.log('[Upscale] Result is null after timeout');
                throw new Error('Upscale timed out after 2 minutes');
            }

            debug.log('[Upscale] Result object:', result);
            debug.log('[Upscale] Credits deducted successfully');

            // Extract image from result - handle different response formats
            let imageUrl: string | null = null;

            if (result && typeof result === 'object') {
                // FAL AI returns { image: { url: "..." }, seed: ... }
                imageUrl = (result as any).image?.url ||
                    (result as any).image ||
                    (result as any).url ||
                    (result as any).output_url ||
                    (result as any).upscaled_image;

            } else if (typeof result === 'string') {
                // Result might be the URL directly
                imageUrl = result;
            }

            if (!imageUrl) {
                debug.log('[Upscale] Could not extract image URL from result', { result, resultType: typeof result });
                throw new Error('No image URL in FAL AI response. Result: ' + JSON.stringify(result).substring(0, 200));
            }

            debug.log('[Upscale] Extracted image URL', { urlStart: imageUrl.substring(0, 50) });

            const newImage = new window.Image();
            newImage.crossOrigin = 'anonymous';

            let imageLoadTimeout: NodeJS.Timeout;

            newImage.onload = () => {
                clearTimeout(imageLoadTimeout);
                debug.log('[Upscale] Image loaded successfully');

                const upscaledObj = {
                    id: generateObjectId(),
                    image: newImage,
                    x: selectedObject.x + selectedObject.image.width + 20,
                    y: selectedObject.y,
                    label: `${selectedObject.label || 'Image'} (Upscaled ${factor}x)`,
                    isMainImage: false,
                };
                setCanvasObjects(prev => [...prev, upscaledObj]);
                setSelectedObject(upscaledObj);

                showAlert('Success', `Upscaled ${factor}x! ${creditCost} ${creditCost === 1 ? 'credit' : 'credits'} deducted.`, 'info', 'upscale');
                setIsGenerating(false);
                setGeneratingType(null);
                stopGenTimer();
            };

            newImage.onerror = () => {
                clearTimeout(imageLoadTimeout);
                debug.log('[Upscale] Image load error');
                showAlert('Error', 'Failed to load upscaled image', 'error', 'upscale');
                setIsGenerating(false);
                setGeneratingType(null);
                stopGenTimer();
            };

            // Set timeout for image loading (10 seconds)
            imageLoadTimeout = setTimeout(() => {
                debug.log('[Upscale] Image load timeout');
                showAlert('Error', 'Image loading timed out', 'error', 'upscale');
                setIsGenerating(false);
                setGeneratingType(null);
                stopGenTimer();
            }, 10000);

            debug.log('[Upscale] Setting image source', { imageUrl: imageUrl.substring(0, 50) + '...' });
            newImage.src = imageUrl;
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Upscale failed';
            debug.log('[Upscale] Error:', errorMessage);
            showAlert('Error', errorMessage, 'error', 'upscale');
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
        }
    };

    const handleConfirmUpscaleModal = () => {
        handleConfirmUpscale(upscaleModal.selectedFactor);
    };

    // Enhancement: Remove Background
    const handleRemoveBackground = async () => {
        if (isGenerating) return;

        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning', 'remove-bg');
            return;
        }

        setGeneratingType('remove-bg');
        setIsGenerating(true);
        startGenTimer();
        setUndoStack((prev) => {
            const newStack = [...prev, lines];
            return newStack.length > CANVAS_DEFAULTS.UNDO_LIMIT ? newStack.slice(1) : newStack;
        });

        try {
            debug.log('[Remove Background] Starting background removal...');

            const imageDataUrl = imageToDataUrl(selectedObject.image);

            const result = await callApi('/api/remove-background', {
                image: imageDataUrl,
            });

            if (result.processedImage) {
                const processedImg = new Image();
                processedImg.crossOrigin = 'anonymous';
                processedImg.onload = () => {
                    debug.log('[Remove Background] Processed image loaded, updating canvas...');
                    // Add as new object beside the original instead of replacing
                    const newObject: CanvasObject = {
                        id: generateObjectId(),
                        image: processedImg,
                        x: selectedObject.x + selectedObject.image.width + CANVAS_DEFAULTS.OBJECT_SPACING,
                        y: selectedObject.y,
                        label: 'No Background',
                        isMainImage: false,
                    };
                    setCanvasObjects((prev) => [...prev, newObject]);
                    setSelectedObject(newObject);

                    const dimensionsMatch = result.originalWidth === processedImg.width &&
                        result.originalHeight === processedImg.height;
                    const method = result.method === 'improved-algorithm' ? 'advanced algorithm' : 'simple algorithm';
                    const message = dimensionsMatch
                        ? `Background removed (${processedImg.width}x${processedImg.height}, PNG with transparency)`
                        : `Background removed using ${method}`;

                    showAlert('Success', message, 'info', 'remove-bg');

                    // Clear loading states after image loads
                    setIsGenerating(false);
                    setGeneratingType(null);
                    stopGenTimer();
                };
                processedImg.onerror = (e) => {
                    debug.error('[Remove Background] Error loading processed image:', e);
                    showAlert('Error', 'Failed to load processed image', 'error', 'remove-bg');
                    setIsGenerating(false);
                    setGeneratingType(null);
                    stopGenTimer();
                };
                processedImg.src = result.processedImage;
            } else {
                throw new Error('No processed image in API result');
            }
        } catch (error) {
            debug.error('[Remove Background] Error:', error);
            showAlert('Error', error instanceof Error ? error.message : 'Failed to remove background', 'error', 'remove-bg');
            setIsGenerating(false);
            setGeneratingType(null);
            stopGenTimer();
        }
    };

    const applyChanges = async () => {
        const target = selectedObject || canvasObjects.find(o => o.isMainImage);
        if (!target) {
            showAlert('No Image', 'No image to apply. Please select an image first.', 'warning');
            return;
        }
        if (!projectId) {
            showAlert('No Project', 'This editor was opened without a project. Use Download instead.', 'warning');
            return;
        }
        // Find the imageId from the URL param (passed as prop / query param)
        const imageId = new URLSearchParams(window.location.search).get('imageId');
        if (!imageId) {
            showAlert('No Image ID', 'Cannot save — no image ID found in the URL. Use Download instead.', 'warning');
            return;
        }

        const confirmed = await showConfirm(
            'Replace Original Image?',
            'This will permanently replace the original image in your project with the currently selected version. This action cannot be undone.',
            'Replace Image',
            true,
        );
        if (!confirmed) return;

        let canvasData: string;
        try {
            const offscreen = document.createElement('canvas');
            offscreen.width = target.image.width;
            offscreen.height = target.image.height;
            const offCtx = offscreen.getContext('2d');
            if (!offCtx) throw new Error('Could not create canvas context');
            offCtx.drawImage(target.image, 0, 0);
            canvasData = offscreen.toDataURL('image/png');
        } catch {
            showAlert('Error', 'Could not read the image (CORS). Try downloading instead.', 'error');
            return;
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            const res = await fetch(`/projects/${projectId}/images/${imageId}/save-edit`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ canvas_data: canvasData, create_new: false }),
            });
            if (!res.ok) throw new Error(`Server error ${res.status}`);
            showAlert('Saved', 'Image replaced successfully! Returning to project…', 'info');
            setTimeout(() => { if (projectId) router.visit(`/projects/${projectId}`); }, 1500);
        } catch (err) {
            showAlert('Error', err instanceof Error ? err.message : 'Failed to save', 'error');
        }
    };

    const downloadImage = () => {
        const target = selectedObject || canvasObjects.find((obj) => obj.isMainImage);
        if (!target) return;
        try {
            const exportCanvas = document.createElement('canvas');
            exportCanvas.width = target.image.width;
            exportCanvas.height = target.image.height;
            const exportCtx = exportCanvas.getContext('2d');
            if (!exportCtx) return;
            exportCtx.drawImage(target.image, 0, 0);
            const link = document.createElement('a');
            link.download = `${target.label || 'image'}-${Date.now()}.png`;
            link.href = exportCanvas.toDataURL();
            link.click();
        } catch {
            showAlert('Error', 'Could not download — CORS restriction. Try uploading the image locally first.', 'error');
        }
    };

    const closeEditor = () => {
        const hasEdits = undoStack.length > 0 || lines.length > 0;
        if (hasEdits) {
            const confirmed = window.confirm('You have unsaved edits. Leave without saving?');
            if (!confirmed) return;
        }
        if (projectId) {
            router.visit(`/projects/${projectId}`);
        } else {
            router.visit('/projects');
        }
    };

    const getCursorStyle = () => {
        if (isDragging || isSpacePressed || draggedObject) {
            return 'grabbing';
        }
        if (currentTool === 'select') {
            // Show grab when hovering a movable object
            if (canvas) {
                const hover = getObjectAtPoint(
                    cursorPosition.x,
                    cursorPosition.y,
                );
                if (hover && !hover.isMainImage) return 'grab';
            }
        }
        if ((currentTool === 'retouch' || currentTool === 'erase') && selectedObject) {
            return 'none'; // Hide cursor, we'll draw custom brush cursor
        }
        return 'default';
    };

    const selectTool = (toolName: string) => {
        setCurrentTool(toolName);
    };

    return (
        <>
            <Head title={`Editing: ${projectTitle}`} />

            <style>{`
                input[type="range"]::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                     width: 14px;
                    height: 14px;
                    border-radius: 50%;
                    background: var(--color-foreground);
                    cursor: pointer;
                    border: none;
                }

                input[type="range"]::-moz-range-thumb {
                    width: 14px;
                    height: 14px;
                    border-radius: 50%;
                    background: var(--color-foreground);
                    cursor: pointer;
                    border: none;
                }
            `}</style>

            <div
                style={{
                    height: '100vh',
                    display: 'flex',
                    flexDirection: 'column',
                    fontFamily:
                        '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                    backgroundColor: 'var(--color-background)',
                    overflow: 'hidden',
                    color: 'var(--color-foreground)',
                    position: 'relative',
                }}
            >
                {/* Top Header */}
                <div
                    style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: '10px 20px',
                        background: 'var(--color-card)',
                        borderBottom: '1px solid var(--color-border)',
                        zIndex: 100,
                        gap: '24px',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '16px',
                        }}
                    >
                        <button
                            onClick={closeEditor}
                            style={{
                                padding: '6px 10px',
                                border: 'none',
                                borderRadius: '6px',
                                background: 'transparent',
                                cursor: 'pointer',
                                fontSize: '14px',
                                color: 'var(--color-muted-foreground)',
                                transition: 'all 0.15s ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px',
                            }}
                        >
                            <ArrowLeft size={16} />
                            <span>{projectId ? 'Back to Project' : 'Back to Projects'}</span>
                        </button>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                            }}
                        >
                            <h1
                                style={{
                                    fontSize: '14px',
                                    fontWeight: 600,
                                    color: 'var(--color-foreground)',
                                    margin: 0,
                                }}
                            >
                                {projectTitle}
                            </h1>
                        </div>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            flex: '0 0 auto',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: '4px',
                            }}
                        >
                            <button onClick={zoomOut} style={zoomButtonStyle}>
                                −
                            </button>
                            <span
                                style={{
                                    fontSize: '13px',
                                    fontWeight: 400,
                                    color: 'var(--color-muted-foreground)',
                                    minWidth: '50px',
                                    textAlign: 'center',
                                    cursor: 'pointer',
                                    padding: '4px 8px',
                                    borderRadius: '4px',
                                    transition: 'all 0.15s ease',
                                }}
                                onClick={resetZoom}
                            >
                                {Math.round(scale * 100)}%
                            </span>
                            <button onClick={zoomIn} style={zoomButtonStyle}>
                                +
                            </button>
                        </div>
                        <div
                            style={{
                                width: '1px',
                                height: '20px',
                                background: 'var(--color-border)',
                                margin: '0 8px',
                            }}
                        />
                        <button
                            onClick={fitToScreen}
                            style={headerButtonStyle}
                            title="Fit canvas to screen"
                        >
                            <Maximize2 size={14} />
                            <span>Fit</span>
                        </button>
                    </div>

                    <div style={{ display: 'flex', gap: '6px' }}>
                        <button
                            onClick={undo}
                            disabled={undoStack.length === 0}
                            style={{
                                ...headerButtonStyle,
                                opacity: undoStack.length === 0 ? 0.4 : 1,
                                cursor:
                                    undoStack.length === 0
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                            title="Undo (Ctrl+Z)"
                        >
                            <Undo2 size={14} />
                            <span>Undo</span>
                        </button>
                        <button
                            onClick={redo}
                            disabled={redoStack.length === 0}
                            style={{
                                ...headerButtonStyle,
                                opacity: redoStack.length === 0 ? 0.4 : 1,
                                cursor:
                                    redoStack.length === 0
                                        ? 'not-allowed'
                                        : 'pointer',
                            }}
                            title="Redo (Ctrl+Shift+Z)"
                        >
                            <Redo2 size={14} />
                            <span>Redo</span>
                        </button>
                        <button
                            onClick={resetDrawing}
                            style={{
                                ...headerButtonStyle,
                                color: 'var(--color-destructive)',
                                borderColor: 'transparent',
                            }}
                            title="Clear all brush strokes"
                        >
                            <RotateCcw size={14} />
                            <span>Reset</span>
                        </button>
                        <div
                            style={{
                                width: '1px',
                                height: '20px',
                                background: 'var(--color-border)',
                                margin: '0 8px',
                            }}
                        />
                        <button
                            onClick={applyChanges}
                            style={headerButtonStyle}
                            title="Apply changes to selected image"
                        >
                            <Save size={14} />
                            <span>Apply</span>
                        </button>
                        <button
                            onClick={downloadImage}
                            style={{
                                ...headerButtonStyle,
                                background: 'var(--color-primary)',
                                color: 'var(--color-primary-foreground)',
                                borderColor: 'var(--color-primary)',
                            }}
                        >
                            <Download size={14} />
                            <span>Download</span>
                        </button>
                    </div>
                </div>

                {/* Main Container */}
                <div
                    style={{
                        flex: 1,
                        display: 'flex',
                        position: 'relative',
                        overflow: 'hidden',
                    }}
                >
                    {/* Skeleton beside original image when generating enhancements */}
                    {isGenerating && generatingType && selectedObject && !['erase', 'ai-edit', 'replace-text'].includes(generatingType) && (
                        <div
                            style={{
                                position: 'absolute',
                                left: panX + selectedObject.x * scale + selectedObject.image.width * scale + 30,
                                top: panY + selectedObject.y * scale,
                                width: selectedObject.image.width * scale,
                                height: selectedObject.image.height * scale,
                                zIndex: 1000,
                                pointerEvents: 'none',
                            }}
                        >
                            <Skeleton
                                className="w-full h-full rounded-2xl"
                                style={{
                                    boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08)',
                                }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    bottom: '16px',
                                    left: '50%',
                                    transform: 'translateX(-50%)',
                                    fontSize: '13px',
                                    color: 'var(--color-foreground)',
                                    fontWeight: 600,
                                    textAlign: 'center',
                                    padding: '8px 16px',
                                    background: 'var(--color-background)',
                                    borderRadius: '8px',
                                    boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
                                    letterSpacing: '0.3px',
                                }}
                            >
                                {generatingType === 'expand' && 'Expanding...'}
                                {generatingType === 'upscale' && 'Upscaling...'}
                                {generatingType === 'remove-bg' && 'Removing background...'}
                                {generatingType === 'crop' && 'Resizing canvas...'}
                                {genElapsedSecs > 0 && (
                                    <span style={{ display: 'block', fontSize: '11px', opacity: 0.65, marginTop: '2px' }}>
                                        {genElapsedSecs}s
                                    </span>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Full overlay skeleton for other operations */}
                    {isGenerating && !generatingType && (
                        <div
                            style={{
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                width: '100%',
                                height: '100%',
                                zIndex: 1000,
                                background: 'color-mix(in oklab, var(--color-background) 70%, transparent)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: '16px',
                                    background: 'var(--color-card)',
                                    padding: '32px 40px',
                                    borderRadius: '16px',
                                    boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
                                }}
                            >
                                <Skeleton className="w-24 h-24 rounded-xl" />
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-48" />
                                    <Skeleton className="h-3 w-36" />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Overlay for AI brush-based operations (erase / ai-edit / replace-text) */}
                    {isGenerating && generatingType && ['erase', 'ai-edit', 'replace-text'].includes(generatingType) && (
                        <div
                            style={{
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                width: '100%',
                                height: '100%',
                                zIndex: 1000,
                                background: 'color-mix(in oklab, var(--color-background) 65%, transparent)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: '16px',
                                    background: 'var(--color-card)',
                                    padding: '32px 40px',
                                    borderRadius: '16px',
                                    boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
                                    minWidth: '220px',
                                }}
                            >
                                <Skeleton className="w-24 h-24 rounded-xl" />
                                <div style={{ textAlign: 'center' }}>
                                    <div style={{ fontWeight: 600, fontSize: '14px', color: 'var(--color-foreground)' }}>
                                        {generatingType === 'erase' && 'Erasing...'}
                                        {generatingType === 'ai-edit' && 'Applying AI Edit...'}
                                        {generatingType === 'replace-text' && 'Replacing Text...'}
                                    </div>
                                    {genElapsedSecs > 0 && (
                                        <div style={{ fontSize: '12px', opacity: 0.6, marginTop: '4px' }}>
                                            {genElapsedSecs}s elapsed
                                        </div>
                                    )}
                                    <div style={{ fontSize: '12px', opacity: 0.5, marginTop: '4px' }}>
                                        This may take up to a minute
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    {/* Sidebar */}
                    <div
                        style={{
                            position: 'absolute',
                            left: '16px',
                            top: '16px',
                            width: sidebarCollapsed ? '60px' : '260px',
                            background: 'var(--color-card)',
                            border: '1px solid var(--color-border)',
                            borderRadius: '12px',
                            boxShadow: 'var(--shadow-lg)',
                            display: 'flex',
                            flexDirection: 'column',
                            transition: 'all 0.15s ease',
                            zIndex: 50,
                            maxHeight: 'calc(100% - 32px)',
                        }}
                    >
                        {!sidebarCollapsed && (
                            <div
                                style={{
                                    padding: '20px 16px 16px',
                                    borderBottom: '1px solid var(--color-border)',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: '12px',
                                        fontWeight: 600,
                                        color: 'var(--color-foreground)',
                                        marginBottom: 0,
                                    }}
                                >
                                    Canvas Editor
                                </div>
                                <div
                                    style={{
                                        fontSize: '11px',
                                        color: 'var(--color-muted-foreground)',
                                        marginTop: '4px',
                                    }}
                                >
                                    Edit and enhance your image
                                </div>
                            </div>
                        )}

                        <div
                            style={{
                                overflowY: 'auto',
                                padding: '16px',
                                flex: 1,
                            }}
                        >
                            {/* Tools Section */}
                            <div style={{ marginBottom: '24px' }}>
                                {!sidebarCollapsed && (
                                    <div
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: 600,
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            padding: '0 4px',
                                        }}
                                    >
                                        Tools
                                    </div>
                                )}
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: '2px',
                                        alignItems: sidebarCollapsed
                                            ? 'center'
                                            : 'stretch',
                                    }}
                                >
                                    <ToolButton
                                        icon={<Crop size={18} />}
                                        label="Crop / Resize"
                                        active={generatingType === 'crop'}
                                        onClick={handleCropResize}
                                        collapsed={sidebarCollapsed}
                                        disabled={isGenerating}
                                        shortcut="C"
                                    />
                                    {/* AI Generate Quick Action */}
                                    <ToolButton
                                        icon={<MousePointer size={18} />}
                                        label="Select"
                                        active={currentTool === 'select'}
                                        onClick={() => selectTool('select')}
                                        collapsed={sidebarCollapsed}
                                        shortcut="V"
                                    />
                                    <ToolButton
                                        icon={<PenTool size={18} />}
                                        label="Retouch"
                                        active={currentTool === 'retouch'}
                                        onClick={() => selectTool('retouch')}
                                        collapsed={sidebarCollapsed}
                                        shortcut="R"
                                    />
                                </div>
                            </div>

                            {/* Brush Controls */}
                            {!sidebarCollapsed && (currentTool === 'retouch' || currentTool === 'erase') && (
                                <div style={{ marginBottom: '24px' }}>
                                    <div
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: 600,
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            padding: '0 4px',
                                        }}
                                    >
                                        Brush Settings
                                    </div>

                                    <div style={{ marginBottom: '20px' }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                fontSize: '14px',
                                                fontWeight: 400,
                                                color: 'var(--color-foreground)',
                                                marginBottom: '10px',
                                            }}
                                        >
                                            <span>Size</span>
                                            <span
                                                style={{
                                                    fontSize: '13px',
                                                    color: 'var(--color-muted-foreground)',
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {brushSize}px
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="10"
                                            max="100"
                                            value={brushSize}
                                            onChange={(e) =>
                                                setBrushSize(
                                                    parseInt(e.target.value),
                                                )
                                            }
                                            style={sliderStyle}
                                        />
                                    </div>

                                    <div>
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                fontSize: '14px',
                                                fontWeight: 400,
                                                color: 'var(--color-foreground)',
                                                marginBottom: '10px',
                                            }}
                                        >
                                            <span>Opacity</span>
                                            <span
                                                style={{
                                                    fontSize: '13px',
                                                    color: 'var(--color-muted-foreground)',
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {brushOpacity}%
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="10"
                                            max="100"
                                            value={brushOpacity}
                                            onChange={(e) =>
                                                setBrushOpacity(
                                                    parseInt(e.target.value),
                                                )
                                            }
                                            style={sliderStyle}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* AI Tools Section — always visible */}
                            <div style={{ marginBottom: '24px' }}>
                                {!sidebarCollapsed && (
                                    <div
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: 600,
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            padding: '0 4px',
                                        }}
                                    >
                                        AI Tools
                                    </div>
                                )}
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: '2px',
                                        alignItems: sidebarCollapsed ? 'center' : 'stretch',
                                    }}
                                >
                                    <ToolButton
                                        icon={<Eraser size={18} />}
                                        label="Erase"
                                        active={false}
                                        onClick={handleErase}
                                        collapsed={sidebarCollapsed}
                                        disabled={
                                            isGenerating ||
                                            !(selectedObject && lines.some((l) => l.objectId === selectedObject.id))
                                        }
                                        shortcut="E"
                                        creditCost={1}
                                    />
                                    <ToolButton
                                        icon={<Wand2 size={18} />}
                                        label="AI Edit"
                                        active={false}
                                        onClick={handleAiEdit}
                                        collapsed={sidebarCollapsed}
                                        disabled={isGenerating || !selectedObject}
                                        shortcut="A"
                                        creditCost={1}
                                    />
                                    <ToolButton
                                        icon={<Type size={18} />}
                                        label="Replace Text"
                                        active={false}
                                        onClick={handleReplaceText}
                                        collapsed={sidebarCollapsed}
                                        disabled={
                                            isGenerating ||
                                            !(selectedObject && lines.some((l) => l.objectId === selectedObject.id))
                                        }
                                        shortcut="T"
                                        creditCost={1}
                                    />
                                </div>
                            </div>

                            {/* Tips Section */}
                            {!sidebarCollapsed && (
                                <div
                                    style={{
                                        background: 'var(--color-muted)',
                                        border: '1px solid var(--color-border)',
                                        borderRadius: '6px',
                                        padding: '12px 14px',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: '14px',
                                            fontWeight: 500,
                                            color: 'var(--color-foreground)',
                                            marginBottom: '8px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '8px',
                                        }}
                                    >
                                        <Lightbulb size={16} />
                                        <span>Navigation Tips</span>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '13px',
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            lineHeight: 1.6,
                                            paddingLeft: '16px',
                                            position: 'relative',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                left: '4px',
                                                color: 'var(--color-muted-foreground)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            •
                                        </span>
                                        Pan canvas with{' '}
                                        <strong>Space + Drag</strong> or{' '}
                                        <strong>Middle Mouse Button</strong>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '13px',
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            lineHeight: 1.6,
                                            paddingLeft: '16px',
                                            position: 'relative',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                left: '4px',
                                                color: 'var(--color-muted-foreground)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            •
                                        </span>
                                        Zoom with <strong>Mouse Wheel</strong>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '13px',
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            lineHeight: 1.6,
                                            paddingLeft: '16px',
                                            position: 'relative',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                left: '4px',
                                                color: 'var(--color-muted-foreground)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            •
                                        </span>
                                        Reset view with <strong>Ctrl+0</strong>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '13px',
                                            color: 'var(--color-muted-foreground)',
                                            marginBottom: '6px',
                                            lineHeight: 1.6,
                                            paddingLeft: '16px',
                                            position: 'relative',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                left: '4px',
                                                color: 'var(--color-muted-foreground)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            •
                                        </span>
                                        Undo with <strong>Ctrl+Z</strong>, Redo
                                        with <strong>Ctrl+Shift+Z</strong>
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '13px',
                                            color: 'var(--color-muted-foreground)',
                                            lineHeight: 1.6,
                                            paddingLeft: '16px',
                                            position: 'relative',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                left: '4px',
                                                color: 'var(--color-muted-foreground)',
                                                fontWeight: 600,
                                            }}
                                        >
                                            •
                                        </span>
                                        Press <strong>V</strong>,{' '}
                                        <strong>R</strong>, <strong>B</strong>,{' '}
                                        <strong>E</strong>, or{' '}
                                        <strong>U</strong> to switch tools
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar Toggle */}
                    <button
                        onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                        style={{
                            position: 'absolute',
                            left: sidebarCollapsed
                                ? 'calc(60px + 16px)'
                                : 'calc(260px + 16px)',
                            top: 'calc(16px + 20px)',
                            width: '28px',
                            height: '28px',
                            background: 'var(--color-card)',
                            border: '1px solid var(--color-border)',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.15s ease',
                            zIndex: 60,
                            boxShadow: 'var(--shadow-xs)',
                        }}
                    >
                        <ChevronLeft
                            size={16}
                            style={{
                                transition: 'all 0.15s ease',
                                transform: sidebarCollapsed
                                    ? 'rotate(180deg)'
                                    : 'rotate(0deg)',
                            }}
                        />
                    </button>

                    {/* Canvas Wrapper */}
                    <div
                        style={{
                            flex: 1,
                            position: 'relative',
                            overflow: 'hidden',
                            background: 'var(--color-muted)',
                            border: isDragOver ? '2px dashed var(--color-primary)' : '2px solid transparent',
                            borderRadius: isDragOver ? '8px' : undefined,
                            transition: 'border 0.15s ease',
                        }}
                        onDragOver={(e) => { e.preventDefault(); setIsDragOver(true); }}
                        onDragLeave={() => setIsDragOver(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setIsDragOver(false);
                            const file = e.dataTransfer.files[0];
                            if (file && file.type.startsWith('image/')) {
                                const url = URL.createObjectURL(file);
                                const img = new Image();
                                img.onload = () => {
                                    const id = Date.now();
                                    const newObj: CanvasObject = {
                                        id,
                                        image: img,
                                        x: 50,
                                        y: 50,
                                        isMainImage: canvasObjects.length === 0,
                                        label: file.name.replace(/\.[^/.]+$/, ''),
                                    };
                                    setCanvasObjects(prev => [...prev, newObj]);
                                    setSelectedObject(newObj);
                                };
                                img.src = url;
                            }
                        }}
                    >
                        {isDragOver && (
                            <div style={{
                                position: 'absolute', inset: 0, zIndex: 2000, pointerEvents: 'none',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                background: 'color-mix(in oklab, var(--color-primary) 10%, transparent)',
                                borderRadius: '6px',
                            }}>
                                <div style={{ background: 'var(--color-card)', padding: '24px 40px', borderRadius: '12px', fontWeight: 600, fontSize: '15px', color: 'var(--color-primary)', boxShadow: '0 4px 16px rgba(0,0,0,0.12)' }}>
                                    Drop image here
                                </div>
                            </div>
                        )}
                        <div
                            style={{
                                width: '100%',
                                height: '100%',
                                position: 'relative',
                                overflow: 'hidden',
                            }}
                        >
                            <canvas
                                ref={canvasRef}
                                onMouseDown={handleMouseDown}
                                onMouseMove={handleMouseMove}
                                onMouseUp={handleMouseUp}
                                onWheel={handleWheel}
                                onTouchStart={handleTouchStart}
                                onTouchMove={handleTouchMove}
                                onTouchEnd={handleTouchEnd}
                                style={{
                                    display: showUploadZone ? 'none' : 'block',
                                    cursor: getCursorStyle(),
                                    touchAction: 'none',
                                }}
                            />

                            {/* Crop Mode Overlay Buttons */}
                            {cropMode && !showUploadZone && (
                                <div
                                    style={{
                                        position: 'absolute',
                                        bottom: '24px',
                                        left: '50%',
                                        transform: 'translateX(-50%)',
                                        display: 'flex',
                                        gap: '12px',
                                        zIndex: 1000,
                                    }}
                                >
                                    <button
                                        onClick={handleCancelCrop}
                                        disabled={isGenerating}
                                        style={{
                                            padding: '10px 24px',
                                            background: 'var(--color-card)',
                                            border: '1px solid var(--color-border)',
                                            borderRadius: '8px',
                                            color: 'var(--color-foreground)',
                                            cursor: isGenerating ? 'not-allowed' : 'pointer',
                                            fontWeight: 500,
                                            fontSize: '14px',
                                            opacity: isGenerating ? 0.5 : 1,
                                            transition: 'all 0.15s ease',
                                        }}
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={cropIntent === 'expand' ? handleConfirmExpand : handleConfirmCrop}
                                        disabled={isGenerating}
                                        style={{
                                            padding: '10px 24px',
                                            background: '#2196F3',
                                            border: 'none',
                                            borderRadius: '8px',
                                            color: 'white',
                                            cursor: isGenerating ? 'not-allowed' : 'pointer',
                                            fontWeight: 500,
                                            fontSize: '14px',
                                            opacity: isGenerating ? 0.5 : 1,
                                            transition: 'all 0.15s ease',
                                        }}
                                    >
                                        {cropIntent === 'expand' ? 'Confirm Expand' : 'Confirm Crop'}
                                    </button>
                                </div>
                            )}

                            {/* Upload Zone */}
                            {showUploadZone && (
                                <div
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                    style={{
                                        position: 'absolute',
                                        top: '50%',
                                        left: '50%',
                                        transform: 'translate(-50%, -50%)',
                                        width: '480px',
                                        maxWidth: '90%',
                                        padding: '60px 40px',
                                        background: 'var(--color-card)',
                                        border: '2px dashed var(--color-border)',
                                        borderRadius: '12px',
                                        cursor: 'pointer',
                                        transition: 'all 0.15s ease',
                                        textAlign: 'center',
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: '48px',
                                            marginBottom: '16px',
                                            opacity: 0.8,
                                        }}
                                    >
                                        📁
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '18px',
                                            fontWeight: 600,
                                            color: 'var(--color-foreground)',
                                            marginBottom: '8px',
                                        }}
                                    >
                                        Upload an image
                                    </div>
                                    <div
                                        style={{
                                            fontSize: '14px',
                                            color: 'var(--color-muted-foreground)',
                                        }}
                                    >
                                        Click to browse or drag and drop
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Hidden File Input */}
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        onChange={handleFileUpload}
                        style={{ display: 'none' }}
                    />
                </div>
            </div>

            {/* Modals */}
            <PromptModal
                isOpen={promptModal.isOpen}
                onClose={() => {
                    if (promptModal.resolve) {
                        promptModal.resolve(null as any);
                    }
                    debug.log('[Prompt] Close (cancel)');
                    setPromptModal((prev) => ({
                        ...prev,
                        isOpen: false,
                        resolve: null,
                    }));
                }}
                onSubmit={(value) => {
                    if (promptModal.resolve) {
                        promptModal.resolve(value);
                    }
                    debug.log('[Prompt] Submit', value);
                    setPromptModal((prev) => ({
                        ...prev,
                        isOpen: false,
                        resolve: null,
                    }));
                }}
                title={promptModal.title}
                description={promptModal.description}
                placeholder={promptModal.placeholder}
                defaultValue={promptModal.defaultValue}
            />

            <AlertModal
                isOpen={alertModal.isOpen}
                onClose={() =>
                    setAlertModal((prev) => ({ ...prev, isOpen: false }))
                }
                title={alertModal.title}
                message={alertModal.message}
                type={alertModal.type}
            />

            <ConfirmModal
                isOpen={confirmModal.isOpen}
                onClose={() => {
                    if (confirmModal.resolve) {
                        confirmModal.resolve(false);
                    }
                    debug.log('[Confirm] Close (cancel)');
                    setConfirmModal((prev) => ({
                        ...prev,
                        isOpen: false,
                        resolve: null,
                    }));
                }}
                onConfirm={() => {
                    if (confirmModal.resolve) {
                        confirmModal.resolve(true);
                    }
                    debug.log('[Confirm] Confirm (yes)');
                    setConfirmModal((prev) => ({
                        ...prev,
                        isOpen: false,
                        resolve: null,
                    }));
                }}
                title={confirmModal.title}
                message={confirmModal.message}
                confirmText={confirmModal.confirmText}
                isDanger={confirmModal.isDanger}
            />

            <UpscaleModal
                isOpen={upscaleModal.isOpen}
                selectedFactor={upscaleModal.selectedFactor}
                onClose={() => setUpscaleModal({ isOpen: false, selectedFactor: 2 })}
                onSelectFactor={(factor) => setUpscaleModal({ isOpen: true, selectedFactor: factor })}
                onConfirm={handleConfirmUpscaleModal}
                isLoading={isGenerating && generatingType === 'upscale'}
            />

            {/* Toast notifications for action history */}
            {actionHistory.length > 0 && (
                <div
                    style={{
                        position: 'fixed',
                        bottom: '24px',
                        right: '24px',
                        zIndex: 9999,
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '8px',
                        pointerEvents: 'none',
                    }}
                >
                    {actionHistory.map((item, idx) => (
                        <div
                            key={`${item.time}-${idx}`}
                            style={{
                                background: 'var(--color-card)',
                                color: 'var(--color-foreground)',
                                borderRadius: '8px',
                                boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
                                padding: '12px 16px',
                                fontSize: '14px',
                                opacity: 0.95,
                                minWidth: '280px',
                                maxWidth: '400px',
                                border: '1px solid var(--color-border)',
                                animation: 'slideInRight 0.3s ease-out',
                            }}
                        >
                            <div style={{ fontWeight: 600, marginBottom: '4px', textTransform: 'capitalize' }}>
                                {item.type.replace('-', ' ')}
                            </div>
                            <div style={{ fontSize: '13px', color: 'var(--color-muted-foreground)' }}>
                                {item.message}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

// Component for tool buttons
function ToolButton({
    icon,
    label,
    active,
    onClick,
    collapsed,
    disabled = false,
    shortcut,
    creditCost,
}: {
    icon: React.ReactNode;
    label: string;
    active: boolean;
    onClick: () => void;
    collapsed: boolean;
    disabled?: boolean;
    shortcut?: string;
    creditCost?: number;
}) {
    return (
        <button
            onClick={disabled ? undefined : onClick}
            disabled={disabled}
            data-tool={label.toLowerCase()}
            title={shortcut ? `${label} (${shortcut}) — ${creditCost ?? 0} credit` : label}
            aria-label={shortcut ? `${label} (${shortcut})` : label}
            style={{
                padding: collapsed ? '10px' : '8px 12px',
                border: 'none',
                borderRadius: '4px',
                background: active ? 'var(--color-muted)' : 'transparent',
                cursor: disabled ? 'not-allowed' : 'pointer',
                transition: 'all 0.15s ease',
                textAlign: 'left',
                display: 'flex',
                alignItems: 'center',
                justifyContent: collapsed ? 'center' : 'space-between',
                gap: '10px',
                color: 'var(--color-foreground)',
                opacity: disabled ? 0.5 : 1,
            }}
            className="tool-card"
        >
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    flex: 1,
                }}
            >
                <div
                    style={{
                        fontSize: '18px',
                        width: '20px',
                        height: '20px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                        color: 'var(--color-muted-foreground)',
                    }}
                >
                    {icon}
                </div>
                {!collapsed && (
                    <span
                        style={{
                            fontSize: '14px',
                            fontWeight: active ? 500 : 400,
                            color: 'var(--color-foreground)',
                        }}
                    >
                        {label}
                    </span>
                )}
            </div>
            {!collapsed && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexShrink: 0 }}>
                    {creditCost !== undefined && (
                        <span
                            style={{
                                fontSize: '10px',
                                fontWeight: 600,
                                color: 'var(--color-primary)',
                                opacity: 0.75,
                                whiteSpace: 'nowrap',
                            }}
                        >
                            ⚡{creditCost}
                        </span>
                    )}
                    {shortcut && (
                        <span
                            style={{
                                fontSize: '12px',
                                fontWeight: 500,
                                color: 'var(--color-muted-foreground)',
                                opacity: 0.7,
                                textTransform: 'uppercase',
                                letterSpacing: '0.5px',
                                minWidth: '16px',
                                textAlign: 'right',
                            }}
                        >
                            {shortcut}
                        </span>
                    )}
                </div>
            )}
        </button>
    );
}

// Styles
const headerButtonStyle: React.CSSProperties = {
    padding: '6px 12px',
    border: '1px solid var(--color-border)',
    borderRadius: '6px',
    background: 'transparent',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: 500,
    color: 'var(--color-muted-foreground)',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    gap: '6px',
};

const zoomButtonStyle: React.CSSProperties = {
    width: '28px',
    height: '28px',
    border: 'none',
    borderRadius: '4px',
    background: 'transparent',
    cursor: 'pointer',
    fontSize: '16px',
    fontWeight: 400,
    color: 'var(--color-muted-foreground)',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
};

const sliderStyle: React.CSSProperties = {
    width: '100%',
    height: '2px',
    borderRadius: '2px',
    background: 'var(--color-border)',
    outline: 'none',
    WebkitAppearance: 'none',
    appearance: 'none',
    cursor: 'pointer',
};

const floatingButtonStyle: React.CSSProperties = {
    padding: '10px 14px',
    background: 'transparent',
    color: 'var(--color-foreground)',
    border: 'none',
    borderRadius: '4px',
    cursor: 'pointer',
    fontWeight: 400,
    fontSize: '14px',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    gap: '10px',
    textAlign: 'left',
};

// Component for floating action buttons with hover/disabled styling
function FloatingActionButton({
    icon,
    label,
    onClick,
    disabled = false,
}: {
    icon: React.ReactNode;
    label: string;
    onClick?: () => void;
    disabled?: boolean;
}) {
    const [hover, setHover] = useState(false);
    return (
        <button
            onClick={disabled ? undefined : onClick}
            disabled={disabled}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            style={{
                ...floatingButtonStyle,
                background: hover && !disabled ? 'var(--color-muted)' : 'transparent',
                opacity: disabled ? 0.5 : 1,
                cursor: disabled ? 'not-allowed' : 'pointer',
            }}
        >
            {icon}
            <span>{label}</span>
        </button>
    );
}
