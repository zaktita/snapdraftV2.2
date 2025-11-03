import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { 
    ArrowLeft, Download, RotateCcw, Undo2, Redo2, Save, 
    ZoomIn, ZoomOut, Maximize2, MousePointer, PenTool, 
    Eraser, Upload, Lightbulb, ChevronLeft, Wand2, Type, Trash2
} from 'lucide-react';
import { PromptModal, AlertModal, ConfirmModal } from '@/components/canvas-modals';

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

export default function CanvasEditor({ projectId, imageUrl, projectTitle = 'Untitled' }: CanvasEditorProps) {
    // Canvas refs
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    
    // State
    const [canvas, setCanvas] = useState<HTMLCanvasElement | null>(null);
    const [ctx, setCtx] = useState<CanvasRenderingContext2D | null>(null);
    const [image, setImage] = useState<HTMLImageElement | null>(null);
    const [currentTool, setCurrentTool] = useState('select');
    const [brushSize, setBrushSize] = useState(50);
    const [brushOpacity, setBrushOpacity] = useState(100);
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
    const [selectedObject, setSelectedObject] = useState<CanvasObject | null>(null);
    const [draggedObject, setDraggedObject] = useState<CanvasObject | null>(null);
    const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [showUploadZone, setShowUploadZone] = useState(true);
    const [cursorPosition, setCursorPosition] = useState({ x: 0, y: 0 });
    const [isImageReady, setIsImageReady] = useState(false);
    const internalClipboardRef = useRef<HTMLImageElement | null>(null);
    const liveDragPositionRef = useRef<{ x: number; y: number } | null>(null);

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
        resolve: null
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
        type: 'info'
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
        resolve: null
    });

    const [isGenerating, setIsGenerating] = useState(false);

    // Initialize canvas
    useEffect(() => {
        if (canvasRef.current) {
            const canvasEl = canvasRef.current;
            const context = canvasEl.getContext('2d');
            setCanvas(canvasEl);
            setCtx(context);
            
            // Load image if URL provided
            if (imageUrl) {
                loadImageFromUrl(imageUrl);
            }
        }

        // Keyboard shortcuts
        const handleKeyDown = (e: KeyboardEvent) => {
            // Prevent shortcuts when typing in inputs
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
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
                    setRedoStack(prev => [...prev, lines]);
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
                    setUndoStack(prev => [...prev, lines]);
                    setLines(nextState);
                    setRedoStack(newRedoStack);
                }
            }

            // Delete selected (not main image)
            if (e.code === 'Delete') {
                if (selectedObject && !selectedObject.isMainImage) {
                    // Remove selected object
                    setCanvasObjects(prev => prev.filter(obj => obj.id !== selectedObject.id));
                    // Clear selection
                    setSelectedObject(null);
                }
            }

            // Tool shortcuts
            if (e.code === 'KeyV') {
                e.preventDefault();
                selectTool('select');
            }
            if (e.code === 'KeyR') {
                e.preventDefault();
                selectTool('retouch');
            }
            if (e.code === 'KeyB') {
                e.preventDefault();
                selectTool('brush');
            }
            if (e.code === 'KeyE') {
                e.preventDefault();
                selectTool('erase');
            }
            if (e.code === 'KeyU') {
                e.preventDefault();
                fileInputRef.current?.click();
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
            console.log('[paste] event received');
            const target = e.target as HTMLElement | null;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.getAttribute('contenteditable') === 'true')) {
                return;
            }
            if (!canvas) return;
            let consumed = false;

            if (e.clipboardData && e.clipboardData.files && e.clipboardData.files.length > 0) {
                const file = Array.from(e.clipboardData.files).find(f => f.type.startsWith('image/'));
                if (file) {
                    e.preventDefault();
                    console.log('[paste] found image file in clipboard');
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
                const items = e.clipboardData.items ? Array.from(e.clipboardData.items) : [];
                const imageItem = items.find((it: DataTransferItem) => it.kind === 'file' && it.type.startsWith('image/')) as (DataTransferItem | undefined);
                if (imageItem) {
                    const file = imageItem.getAsFile();
                    if (file) {
                        e.preventDefault();
                        console.log('[paste] found image item in clipboard items');
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
                if (text && /^https?:\/\//i.test(text) && /(\.png|\.jpe?g|\.webp|\.gif)(\?|#|$)/i.test(text)) {
                    e.preventDefault();
                    console.log('[paste] found image URL text');
                    const img = new Image();
                    img.onload = () => addImageToCanvas(img, 'Pasted Image');
                    img.crossOrigin = 'anonymous';
                    img.src = text;
                    consumed = true;
                }
            }

            if (!consumed && internalClipboardRef.current) {
                e.preventDefault();
                console.log('[paste] using internal clipboard fallback');
                const src = internalClipboardRef.current.src;
                const img = new Image();
                img.onload = () => addImageToCanvas(img, 'Pasted Copy');
                img.crossOrigin = 'anonymous';
                img.src = src;
            }
        };

        window.addEventListener('paste', handlePaste as any);
        document.addEventListener('paste', handlePaste as any);
        return () => {
            window.removeEventListener('paste', handlePaste as any);
            document.removeEventListener('paste', handlePaste as any);
        }
    }, [canvas, scale, panX, panY, cursorPosition, canvasObjects]);

    // Draw scene when state changes
    useEffect(() => {
        if (canvas && ctx) {
            drawScene();
        }
    }, [canvas, ctx, canvasObjects, lines, scale, panX, panY, selectedObject, brushSize, cursorPosition, currentTool, isSpacePressed, isDragging]);

    const resizeCanvas = () => {
        if (!canvas) return;
        const container = canvas.parentElement;
        if (!container) return;
        canvas.width = container.clientWidth;
        canvas.height = container.clientHeight;
        if (image) drawScene();
    };

    const loadImageFromUrl = (src: string) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            setImage(img);
            setIsImageReady(false);
            
            // Calculate center position before rendering
            requestAnimationFrame(() => {
                if (!canvas) return;
                
                const imageWidth = img.width;
                const imageHeight = img.height;
                const containerWidth = canvas.width - 100;
                const containerHeight = canvas.height - 100;
                
                const scaleX = containerWidth / imageWidth;
                const scaleY = containerHeight / imageHeight;
                const newScale = Math.min(scaleX, scaleY, 1);
                
                // Calculate centered position
                const centeredPanX = (canvas.width - imageWidth * newScale) / 2;
                const centeredPanY = (canvas.height - imageHeight * newScale) / 2;
                
                // Set scale and pan before creating objects
                setScale(newScale);
                setPanX(centeredPanX);
                setPanY(centeredPanY);
                
                // Now create the object and render
                const mainImageObj: CanvasObject = {
                    id: Date.now(),
                    image: img,
                    x: 0,
                    y: 0,
                    label: 'Main Image',
                    isMainImage: true
                };
                setCanvasObjects([mainImageObj]);
                setSelectedObject(mainImageObj);
                setShowUploadZone(false);
                setLines([]);
                setIsImageReady(true);
            });
        };
        img.src = src;
    };

    const loadImageFile = (file: File) => {
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
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
        canvasObjects.forEach(obj => drawCanvasObject(obj));
        
        // Draw brush strokes
        canvasObjects.forEach(obj => {
            const objectLines = lines.filter(line => !line.objectId || line.objectId === obj.id);
            if (objectLines.length > 0) {
                drawBrushStrokes(obj);
            }
        });
        
        // Draw selection highlight
        const sel = draggedObject || selectedObject;
        if (sel) {
            drawSelectionHighlight(sel);
        }

        // Draw custom brush cursor when retouch tool is active and hovering over image
        if (currentTool === 'retouch' && selectedObject && !isDragging && !isPainting) {
            const hoveredObject = getObjectAtPoint(cursorPosition.x, cursorPosition.y);
            if (hoveredObject === selectedObject) {
                drawBrushCursor();
            }
        }
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
        
        lines.forEach(line => {
            if (line.points.length > 1 && (!line.objectId || line.objectId === imageObj.id)) {
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
        
        ctx.save();
        ctx.translate(panX + obj.x * scale - 2, panY + obj.y * scale - 2);
        ctx.scale(scale, scale);
        ctx.strokeStyle = '#2196F3';
        ctx.lineWidth = 3 / scale;
        ctx.setLineDash([5 / scale, 5 / scale]);
        ctx.strokeRect(0, 0, obj.image.width + 4 / scale, obj.image.height + 4 / scale);
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
        ctx.arc(cursorPosition.x, cursorPosition.y, scaledBrushSize / 2, 0, Math.PI * 2);
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

    const screenToObjectCoordinates = (screenX: number, screenY: number, obj: CanvasObject) => {
        const canvasPos = screenToCanvasCoordinates(screenX, screenY);
        return { x: canvasPos.x - obj.x, y: canvasPos.y - obj.y };
    };

    const isPointInObject = (x: number, y: number, obj: CanvasObject) => {
        return x >= 0 && x <= obj.image.width && y >= 0 && y <= obj.image.height;
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

        // Pan with space+drag or middle mouse button
        if (e.button === 1 || (e.button === 0 && isSpacePressed)) {
            e.preventDefault();
            setIsDragging(true);
            setLastPanPoint({ x: e.clientX, y: e.clientY });
            return;
        }

        // Don't handle right click
        if (e.button === 2) return;

        const clickedObject = getObjectAtPoint(x, y);
        if (clickedObject) {
            setSelectedObject(clickedObject);

            if (currentTool === 'retouch' && clickedObject === selectedObject) {
                const imagePos = screenToObjectCoordinates(x, y, clickedObject);
                if (isPointInObject(imagePos.x, imagePos.y, clickedObject)) {
                    setIsPainting(true);
                    startNewLine(imagePos.x, imagePos.y, clickedObject.id);
                }
            } else if (currentTool === 'select') {
                // Begin dragging this object
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

    const handleMouseMove = (e: React.MouseEvent<HTMLCanvasElement>) => {
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Update cursor position for custom cursor
        setCursorPosition({ x, y });

        if (isDragging) {
            const dx = e.clientX - lastPanPoint.x;
            const dy = e.clientY - lastPanPoint.y;
            setPanX(prev => prev + dx);
            setPanY(prev => prev + dy);
            setLastPanPoint({ x: e.clientX, y: e.clientY });
            return;
        }

        if (draggedObject) {
            // Move object with cursor in select mode
            const newX = (x - dragOffset.x - panX) / scale;
            const newY = (y - dragOffset.y - panY) / scale;
            
            // Directly mutate the object in the array (like HTML version) for immediate update
            const objIndex = canvasObjects.findIndex(obj => obj.id === draggedObject.id);
            if (objIndex !== -1) {
                canvasObjects[objIndex].x = newX;
                canvasObjects[objIndex].y = newY;
                
                // Force re-render by triggering a state update
                setCanvasObjects([...canvasObjects]);
                
                // Update selected and dragged references
                setSelectedObject(canvasObjects[objIndex]);
                setDraggedObject(canvasObjects[objIndex]);
            }
            return;
        }

        if (isPainting && currentTool === 'retouch' && selectedObject) {
            const imagePos = screenToObjectCoordinates(x, y, selectedObject);
            if (isPointInObject(imagePos.x, imagePos.y, selectedObject)) {
                addPointToCurrentLine(imagePos.x, imagePos.y);
            }
        }
    };

    const handleMouseUp = () => {
        setIsDragging(false);
        if (isPainting) {
            setIsPainting(false);
        }
        if (draggedObject) {
            setDraggedObject(null);
        }
    };

    // Add an image element to canvas, position at cursor if available, else center
    const addImageToCanvas = (img: HTMLImageElement, label = 'Image') => {
        if (!canvas) return;
        let targetX: number;
        let targetY: number;
        if (cursorPosition.x !== 0 || cursorPosition.y !== 0) {
            const { x, y } = screenToCanvasCoordinates(cursorPosition.x, cursorPosition.y);
            targetX = x - img.width / 2;
            targetY = y - img.height / 2;
        } else {
            const centerCanvasX = (canvas.width / 2 - panX) / scale;
            const centerCanvasY = (canvas.height / 2 - panY) / scale;
            targetX = centerCanvasX - img.width / 2;
            targetY = centerCanvasY - img.height / 2;
        }

        const newObj: CanvasObject = {
            id: Date.now() + Math.floor(Math.random() * 1000),
            image: img,
            x: targetX,
            y: targetY,
            label,
            isMainImage: false
        };

        setCanvasObjects(prev => [...prev, newObj]);
        setSelectedObject(newObj);
        setShowUploadZone(false);
    };

    const handleWheel = (e: React.WheelEvent<HTMLCanvasElement>) => {
        e.preventDefault();
        if (!canvas) return;
        
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        
        const scaleBy = 1.1;
        const direction = e.deltaY > 0 ? -1 : 1;
        const oldScale = scale;
        const newScale = direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;
        const clampedScale = Math.max(0.1, Math.min(10, newScale));
        
        setScale(clampedScale);
        const scaleChange = clampedScale / oldScale;
        setPanX(mouseX - (mouseX - panX) * scaleChange);
        setPanY(mouseY - (mouseY - panY) * scaleChange);
    };

    const startNewLine = (x: number, y: number, objectId?: number) => {
        // Save current state to undo stack before adding new line
        setUndoStack(prev => {
            const newStack = [...prev, lines];
            // Limit undo stack to 50 items
            if (newStack.length > 50) {
                newStack.shift();
            }
            return newStack;
        });
        // Clear redo stack when new action is performed
        setRedoStack([]);
        
        const newLine: BrushLine = {
            id: Date.now() + Math.random(),
            points: [x, y],
            brushSize: brushSize,
            brushOpacity: brushOpacity / 100,
            tool: 'brush',
            objectId: objectId || null
        };
        setLines(prev => [...prev, newLine]);
    };

    const addPointToCurrentLine = (x: number, y: number) => {
        setLines(prev => {
            if (prev.length === 0) return prev;
            const updated = [...prev];
            const lastLine = updated[updated.length - 1];
            lastLine.points.push(x, y);
            return updated;
        });
    };

    const fitToScreen = () => {
        if (canvasObjects.length === 0 || !canvas) return;
        
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        canvasObjects.forEach(obj => {
            minX = Math.min(minX, obj.x);
            minY = Math.min(minY, obj.y);
            maxX = Math.max(maxX, obj.x + obj.image.width);
            maxY = Math.max(maxY, obj.y + obj.image.height);
        });
        
        const totalWidth = maxX - minX;
        const totalHeight = maxY - minY;
        const containerWidth = canvas.width - 100;
        const containerHeight = canvas.height - 100;
        
        const scaleX = containerWidth / totalWidth;
        const scaleY = containerHeight / totalHeight;
        const newScale = Math.min(scaleX, scaleY, 1);
        
        setScale(newScale);
        setPanX((canvas.width - totalWidth * newScale) / 2 - minX * newScale);
        setPanY((canvas.height - totalHeight * newScale) / 2 - minY * newScale);
    };

    const zoomIn = () => setScale(prev => Math.min(10, prev * 1.2));
    const zoomOut = () => setScale(prev => Math.max(0.1, prev / 1.2));
    const resetZoom = () => setScale(1);

    const undo = () => {
        if (undoStack.length > 0) {
            const newUndoStack = [...undoStack];
            const lastState = newUndoStack.pop()!;
            setRedoStack(prev => [...prev, lines]);
            setLines(lastState);
            setUndoStack(newUndoStack);
        }
    };

    const redo = () => {
        if (redoStack.length > 0) {
            const newRedoStack = [...redoStack];
            const nextState = newRedoStack.pop()!;
            setUndoStack(prev => [...prev, lines]);
            setLines(nextState);
            setRedoStack(newRedoStack);
        }
    };

    const resetDrawing = () => {
        if (lines.length > 0 && confirm('Clear all brush strokes?')) {
            setUndoStack(prev => [...prev, lines]);
            setLines([]);
            setRedoStack([]);
        }
    };

    // Create a mask canvas (white where brushed, black elsewhere) for the selected object
    const createMaskCanvas = (target: CanvasObject | null): HTMLCanvasElement | null => {
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

        const relevant = lines.filter(l => l.objectId === target.id);
        relevant.forEach(line => {
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
        return maskCanvas;
    };

    // Download the mask for the currently selected object
    const downloadMask = () => {
        if (!selectedObject) {
            alert('Please select an image first.');
            return;
        }
        const relevant = lines.filter(l => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            alert('No brush strokes found for the selected image.');
            return;
        }
        const maskCanvas = createMaskCanvas(selectedObject);
        if (!maskCanvas) return;
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
        defaultValue: string = ''
    ): Promise<string> => {
        return new Promise((resolve) => {
            setPromptModal({
                isOpen: true,
                title,
                description,
                placeholder,
                defaultValue,
                resolve
            });
        });
    };

    const showAlert = (title: string, message: string, type: 'info' | 'warning' | 'error' = 'info') => {
        setAlertModal({
            isOpen: true,
            title,
            message,
            type
        });
    };

    const showConfirm = (
        title: string, 
        message: string, 
        confirmText: string = 'Confirm',
        isDanger: boolean = false
    ): Promise<boolean> => {
        return new Promise((resolve) => {
            setConfirmModal({
                isOpen: true,
                title,
                message,
                confirmText,
                isDanger,
                resolve
            });
        });
    };

    // Action handlers
    const handleErase = async () => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            return;
        }

        const relevant = lines.filter(l => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert('No Mask', 'Please paint over the areas you want to erase first.', 'warning');
            return;
        }

        // Confirm before erasing
        const confirmed = await showConfirm(
            'Erase Areas?',
            'This will permanently remove the painted areas from the image. This action cannot be undone.',
            'Erase',
            true
        );

        if (!confirmed) return;

        setIsGenerating(true);
        
        try {
            // Create mask
            const maskCanvas = createMaskCanvas(selectedObject);
            if (!maskCanvas) throw new Error('Failed to create mask');

            // Convert images to base64
            const originalCanvas = document.createElement('canvas');
            originalCanvas.width = selectedObject.image.width;
            originalCanvas.height = selectedObject.image.height;
            const originalCtx = originalCanvas.getContext('2d');
            if (!originalCtx) throw new Error('Failed to create canvas context');
            originalCtx.drawImage(selectedObject.image, 0, 0);
            
            const originalImage = originalCanvas.toDataURL('image/png');
            const maskImage = maskCanvas.toDataURL('image/png');

            // Make API call
            const response = await fetch('/api/generate-with-mask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    originalImage,
                    mask: maskImage,
                    prompt: 'erase',
                    brushStrokes: relevant,
                    imageSize: {
                        width: selectedObject.image.width,
                        height: selectedObject.image.height
                    }
                })
            });

            if (!response.ok) {
                throw new Error(`API error: ${response.statusText}`);
            }

            const result = await response.json();
            
            // Display generated result
            if (result.generatedImage) {
                const newImage = new Image();
                newImage.onload = () => {
                    // Replace selected object's image
                    const objIndex = canvasObjects.findIndex(obj => obj.id === selectedObject.id);
                    if (objIndex !== -1) {
                        const updatedObjects = [...canvasObjects];
                        updatedObjects[objIndex] = {
                            ...updatedObjects[objIndex],
                            image: newImage
                        };
                        setCanvasObjects(updatedObjects);
                        setSelectedObject(updatedObjects[objIndex]);
                        
                        // Clear brush strokes for this object
                        setLines(lines.filter(l => l.objectId !== selectedObject.id));
                    }
                };
                newImage.src = result.generatedImage;
            }
        } catch (error) {
            console.error('Erase error:', error);
            showAlert('Error', error instanceof Error ? error.message : 'Failed to erase areas', 'error');
        } finally {
            setIsGenerating(false);
        }
    };

    const handleReplaceText = async () => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            return;
        }

        const relevant = lines.filter(l => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert('No Mask', 'Please paint over the text you want to replace.', 'warning');
            return;
        }

        try {
            const textToReplace = await showPrompt(
                'Text Replacement',
                'Enter the text you want to replace the selected area with:',
                'Your text here...',
                'Your text here'
            );

            if (!textToReplace) return;

            // Confirm before replacing
            const confirmed = await showConfirm(
                'Replace Text?',
                `This will replace the selected area with: "${textToReplace}". Continue?`,
                'Replace',
                false
            );

            if (!confirmed) return;

            setIsGenerating(true);

            // Create mask
            const maskCanvas = createMaskCanvas(selectedObject);
            if (!maskCanvas) throw new Error('Failed to create mask');

            // Convert images to base64
            const originalCanvas = document.createElement('canvas');
            originalCanvas.width = selectedObject.image.width;
            originalCanvas.height = selectedObject.image.height;
            const originalCtx = originalCanvas.getContext('2d');
            if (!originalCtx) throw new Error('Failed to create canvas context');
            originalCtx.drawImage(selectedObject.image, 0, 0);
            
            const originalImage = originalCanvas.toDataURL('image/png');
            const maskImage = maskCanvas.toDataURL('image/png');

            // Make API call
            const response = await fetch('/api/generate-with-mask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    originalImage,
                    mask: maskImage,
                    prompt: `Replace with text: "${textToReplace}"`,
                    brushStrokes: relevant,
                    imageSize: {
                        width: selectedObject.image.width,
                        height: selectedObject.image.height
                    }
                })
            });

            if (!response.ok) {
                throw new Error(`API error: ${response.statusText}`);
            }

            const result = await response.json();
            
            // Display generated result
            if (result.generatedImage) {
                const newImage = new Image();
                newImage.onload = () => {
                    // Replace selected object's image
                    const objIndex = canvasObjects.findIndex(obj => obj.id === selectedObject.id);
                    if (objIndex !== -1) {
                        const updatedObjects = [...canvasObjects];
                        updatedObjects[objIndex] = {
                            ...updatedObjects[objIndex],
                            image: newImage
                        };
                        setCanvasObjects(updatedObjects);
                        setSelectedObject(updatedObjects[objIndex]);
                        
                        // Clear brush strokes for this object
                        setLines(lines.filter(l => l.objectId !== selectedObject.id));
                    }
                };
                newImage.src = result.generatedImage;
            }
        } catch (error) {
            if (error === null) return; // User cancelled
            console.error('Replace text error:', error);
            showAlert('Error', error instanceof Error ? error.message : 'Failed to replace text', 'error');
        } finally {
            setIsGenerating(false);
        }
    };

    const handleGenerate = async () => {
        if (!selectedObject) {
            showAlert('No Selection', 'Please select an image first.', 'warning');
            return;
        }

        const relevant = lines.filter(l => l.objectId === selectedObject.id);
        if (relevant.length === 0) {
            showAlert('No Mask', 'Please paint over the areas you want to regenerate.', 'warning');
            return;
        }

        try {
            const prompt = await showPrompt(
                'AI Generation',
                'Describe what you want to generate in the masked area:',
                'e.g., "a red sports car", "mountains in the background"...',
                ''
            );

            if (!prompt) return;

            // Confirm before generating
            const confirmed = await showConfirm(
                'Generate with AI?',
                `This will use AI to generate content based on: "${prompt}". Continue?`,
                'Generate',
                false
            );

            if (!confirmed) return;

            setIsGenerating(true);

            // Create mask
            const maskCanvas = createMaskCanvas(selectedObject);
            if (!maskCanvas) throw new Error('Failed to create mask');

            // Convert images to base64
            const originalCanvas = document.createElement('canvas');
            originalCanvas.width = selectedObject.image.width;
            originalCanvas.height = selectedObject.image.height;
            const originalCtx = originalCanvas.getContext('2d');
            if (!originalCtx) throw new Error('Failed to create canvas context');
            originalCtx.drawImage(selectedObject.image, 0, 0);
            
            const originalImage = originalCanvas.toDataURL('image/png');
            const maskImage = maskCanvas.toDataURL('image/png');

            // Make API call
            const response = await fetch('/api/generate-with-mask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    originalImage,
                    mask: maskImage,
                    prompt,
                    brushStrokes: relevant,
                    imageSize: {
                        width: selectedObject.image.width,
                        height: selectedObject.image.height
                    }
                })
            });

            if (!response.ok) {
                throw new Error(`API error: ${response.statusText}`);
            }

            const result = await response.json();
            
            // Display generated result
            if (result.generatedImage) {
                const newImage = new Image();
                newImage.onload = () => {
                    // Replace selected object's image
                    const objIndex = canvasObjects.findIndex(obj => obj.id === selectedObject.id);
                    if (objIndex !== -1) {
                        const updatedObjects = [...canvasObjects];
                        updatedObjects[objIndex] = {
                            ...updatedObjects[objIndex],
                            image: newImage
                        };
                        setCanvasObjects(updatedObjects);
                        setSelectedObject(updatedObjects[objIndex]);
                        
                        // Clear brush strokes for this object
                        setLines(lines.filter(l => l.objectId !== selectedObject.id));
                    }
                };
                newImage.src = result.generatedImage;
            }
        } catch (error) {
            if (error === null) return; // User cancelled
            console.error('Generate error:', error);
            showAlert('Error', error instanceof Error ? error.message : 'Failed to generate image', 'error');
        } finally {
            setIsGenerating(false);
        }
    };

    const applyChanges = () => {
        alert('Apply changes functionality will be implemented soon.');
    };

    const downloadImage = () => {
        if (canvasObjects.length === 0) return;
        const mainImage = canvasObjects.find(obj => obj.isMainImage);
        if (!mainImage) return;

        const exportCanvas = document.createElement('canvas');
        exportCanvas.width = mainImage.image.width;
        exportCanvas.height = mainImage.image.height;
        const exportCtx = exportCanvas.getContext('2d');
        if (!exportCtx) return;

        exportCtx.drawImage(mainImage.image, 0, 0);
        
        const link = document.createElement('a');
        link.download = 'edited-image.png';
        link.href = exportCanvas.toDataURL();
        link.click();
    };

    const closeEditor = () => {
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
                const hover = getObjectAtPoint(cursorPosition.x, cursorPosition.y);
                if (hover && !hover.isMainImage) return 'grab';
            }
        }
        if (currentTool === 'retouch' && selectedObject) {
            return 'none'; // Hide cursor, we'll draw custom one
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
                    background: #1f1f1fff;
                    cursor: pointer;
                    border: none;
                }

                input[type="range"]::-moz-range-thumb {
                    width: 14px;
                    height: 14px;
                    border-radius: 50%;
                    background: #1f1f1fff;
                    cursor: pointer;
                    border: none;
                }
            `}</style>
            
            <div style={{ 
                height: '100vh', 
                display: 'flex', 
                flexDirection: 'column',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif',
                backgroundColor: '#FAF9F7',
                overflow: 'hidden',
                color: '#37352f'
            }}>
                {/* Top Header */}
                <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '10px 20px',
                    background: '#ffffff',
                    borderBottom: '1px solid #e5e7eb',
                    zIndex: 100,
                    gap: '24px'
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                        <button 
                            onClick={closeEditor}
                            style={{
                                padding: '6px 10px',
                                border: 'none',
                                borderRadius: '6px',
                                background: 'transparent',
                                cursor: 'pointer',
                                fontSize: '14px',
                                color: '#787774',
                                transition: 'all 0.15s ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px'
                            }}
                        >
                            <ArrowLeft size={16} />
                            <span>Back</span>
                        </button>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <h1 style={{ fontSize: '14px', fontWeight: 600, color: '#37352f', margin: 0 }}>
                                {projectTitle}
                            </h1>
                        </div>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flex: '0 0 auto' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                            <button onClick={zoomOut} style={zoomButtonStyle}>−</button>
                            <span style={{
                                fontSize: '13px',
                                fontWeight: 400,
                                color: '#787774',
                                minWidth: '50px',
                                textAlign: 'center',
                                cursor: 'pointer',
                                padding: '4px 8px',
                                borderRadius: '4px',
                                transition: 'all 0.15s ease'
                            }} onClick={resetZoom}>
                                {Math.round(scale * 100)}%
                            </span>
                            <button onClick={zoomIn} style={zoomButtonStyle}>+</button>
                        </div>
                        <div style={{ width: '1px', height: '20px', background: '#e5e7eb', margin: '0 8px' }} />
                        <button onClick={fitToScreen} style={headerButtonStyle} title="Fit canvas to screen">
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
                                cursor: undoStack.length === 0 ? 'not-allowed' : 'pointer'
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
                                cursor: redoStack.length === 0 ? 'not-allowed' : 'pointer'
                            }} 
                            title="Redo (Ctrl+Shift+Z)"
                        >
                            <Redo2 size={14} />
                            <span>Redo</span>
                        </button>
                        <button onClick={resetDrawing} style={{ ...headerButtonStyle, color: '#eb5757', borderColor: 'transparent' }} title="Clear all brush strokes">
                            <RotateCcw size={14} />
                            <span>Reset</span>
                        </button>
                        <div style={{ width: '1px', height: '20px', background: '#e5e7eb', margin: '0 8px' }} />
                        <button onClick={applyChanges} style={headerButtonStyle} title="Apply changes to selected image">
                            <Save size={14} />
                            <span>Apply</span>
                        </button>
                        <button onClick={downloadImage} style={{ ...headerButtonStyle, background: '#373737', color: 'white', borderColor: '#373737' }}>
                            <Download size={14} />
                            <span>Download</span>
                        </button>
                    </div>
                </div>

                {/* Main Container */}
                <div style={{ flex: 1, display: 'flex', position: 'relative', overflow: 'hidden' }}>
                    {/* Sidebar */}
                    <div style={{
                        position: 'absolute',
                        left: '16px',
                        top: '16px',
                        width: sidebarCollapsed ? '60px' : '260px',
                        background: '#ffffff',
                        border: '1px solid #e5e7eb',
                        borderRadius: '12px',
                        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.08)',
                        display: 'flex',
                        flexDirection: 'column',
                        transition: 'all 0.15s ease',
                        zIndex: 50,
                        maxHeight: 'calc(100% - 32px)'
                    }}>
                        {!sidebarCollapsed && (
                            <div style={{
                                padding: '20px 16px 16px',
                                borderBottom: '1px solid #e5e7eb'
                            }}>
                                <div style={{
                                    fontSize: '12px',
                                    fontWeight: 600,
                                    color: '#37352f',
                                    marginBottom: 0
                                }}>Canvas Editor</div>
                                <div style={{
                                    fontSize: '11px',
                                    color: '#9b9a97',
                                    marginTop: '4px'
                                }}>Edit and enhance your image</div>
                            </div>
                        )}

                        <div style={{
                            overflowY: 'auto',
                            padding: '16px',
                            flex: 1
                        }}>
                            {/* Tools Section */}
                            <div style={{ marginBottom: '24px' }}>
                                {!sidebarCollapsed && (
                                    <div style={{
                                        fontSize: '11px',
                                        fontWeight: 600,
                                        color: '#9b9a97',
                                        marginBottom: '6px',
                                        padding: '0 4px'
                                    }}>Tools</div>
                                )}
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '2px', alignItems: sidebarCollapsed ? 'center' : 'stretch' }}>
                                    <ToolButton 
                                        icon={<MousePointer size={18} />} 
                                        label="Select" 
                                        active={currentTool === 'select'}
                                        onClick={() => selectTool('select')}
                                        collapsed={sidebarCollapsed}
                                    />
                                    <ToolButton 
                                        icon={<PenTool size={18} />} 
                                        label="Retouch" 
                                        active={currentTool === 'retouch'}
                                        onClick={() => selectTool('retouch')}
                                        collapsed={sidebarCollapsed}
                                    />
                                    <ToolButton 
                                        icon={<Eraser size={18} />} 
                                        label="Erase" 
                                        active={currentTool === 'erase'}
                                        onClick={() => selectTool('erase')}
                                        collapsed={sidebarCollapsed}
                                    />
                                    <ToolButton 
                                        icon={<Upload size={18} />} 
                                        label="Upload" 
                                        active={currentTool === 'upload'}
                                        onClick={() => fileInputRef.current?.click()}
                                        collapsed={sidebarCollapsed}
                                    />
                                </div>
                            </div>

                            {/* Brush Controls */}
                            {!sidebarCollapsed && currentTool === 'retouch' && (
                                <div style={{ marginBottom: '24px' }}>
                                    <div style={{
                                        fontSize: '11px',
                                        fontWeight: 600,
                                        color: '#9b9a97',
                                        marginBottom: '6px',
                                        padding: '0 4px'
                                    }}>Brush Settings</div>
                                    
                                    <div style={{ marginBottom: '20px' }}>
                                        <div style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center',
                                            fontSize: '14px',
                                            fontWeight: 400,
                                            color: '#37352f',
                                            marginBottom: '10px'
                                        }}>
                                            <span>Size</span>
                                            <span style={{ fontSize: '13px', color: '#9b9a97', fontWeight: 500 }}>
                                                {brushSize}px
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="10"
                                            max="100"
                                            value={brushSize}
                                            onChange={(e) => setBrushSize(parseInt(e.target.value))}
                                            style={sliderStyle}
                                        />
                                    </div>

                                    <div>
                                        <div style={{
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center',
                                            fontSize: '14px',
                                            fontWeight: 400,
                                            color: '#37352f',
                                            marginBottom: '10px'
                                        }}>
                                            <span>Opacity</span>
                                            <span style={{ fontSize: '13px', color: '#9b9a97', fontWeight: 500 }}>
                                                {brushOpacity}%
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min="10"
                                            max="100"
                                            value={brushOpacity}
                                            onChange={(e) => setBrushOpacity(parseInt(e.target.value))}
                                            style={sliderStyle}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Tips Section */}
                            {!sidebarCollapsed && (
                                <div style={{
                                    background: '#FAF9F7',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '6px',
                                    padding: '12px 14px'
                                }}>
                                    <div style={{
                                        fontSize: '14px',
                                        fontWeight: 500,
                                        color: '#37352f',
                                        marginBottom: '8px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '8px'
                                    }}>
                                        <Lightbulb size={16} />
                                        <span>Navigation Tips</span>
                                    </div>
                                    <div style={{
                                        fontSize: '13px',
                                        color: '#787774',
                                        marginBottom: '6px',
                                        lineHeight: 1.6,
                                        paddingLeft: '16px',
                                        position: 'relative'
                                    }}>
                                        <span style={{ position: 'absolute', left: '4px', color: '#9b9a97', fontWeight: 600 }}>•</span>
                                        Pan canvas with <strong>Space + Drag</strong> or <strong>Middle Mouse Button</strong>
                                    </div>
                                    <div style={{
                                        fontSize: '13px',
                                        color: '#787774',
                                        marginBottom: '6px',
                                        lineHeight: 1.6,
                                        paddingLeft: '16px',
                                        position: 'relative'
                                    }}>
                                        <span style={{ position: 'absolute', left: '4px', color: '#9b9a97', fontWeight: 600 }}>•</span>
                                        Zoom with <strong>Mouse Wheel</strong>
                                    </div>
                                    <div style={{
                                        fontSize: '13px',
                                        color: '#787774',
                                        marginBottom: '6px',
                                        lineHeight: 1.6,
                                        paddingLeft: '16px',
                                        position: 'relative'
                                    }}>
                                        <span style={{ position: 'absolute', left: '4px', color: '#9b9a97', fontWeight: 600 }}>•</span>
                                        Reset view with <strong>Ctrl+0</strong>
                                    </div>
                                    <div style={{
                                        fontSize: '13px',
                                        color: '#787774',
                                        marginBottom: '6px',
                                        lineHeight: 1.6,
                                        paddingLeft: '16px',
                                        position: 'relative'
                                    }}>
                                        <span style={{ position: 'absolute', left: '4px', color: '#9b9a97', fontWeight: 600 }}>•</span>
                                        Undo with <strong>Ctrl+Z</strong>, Redo with <strong>Ctrl+Shift+Z</strong>
                                    </div>
                                    <div style={{
                                        fontSize: '13px',
                                        color: '#787774',
                                        lineHeight: 1.6,
                                        paddingLeft: '16px',
                                        position: 'relative'
                                    }}>
                                        <span style={{ position: 'absolute', left: '4px', color: '#9b9a97', fontWeight: 600 }}>•</span>
                                        Press <strong>V</strong>, <strong>R</strong>, <strong>B</strong>, <strong>E</strong>, or <strong>U</strong> to switch tools
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
                            left: sidebarCollapsed ? 'calc(60px + 16px)' : 'calc(260px + 16px)',
                            top: 'calc(16px + 20px)',
                            width: '28px',
                            height: '28px',
                            background: '#ffffff',
                            border: '1px solid #e5e7eb',
                            borderRadius: '6px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.15s ease',
                            zIndex: 60,
                            boxShadow: '0 1px 2px rgba(0, 0, 0, 0.04)'
                        }}
                    >
                        <ChevronLeft 
                            size={16} 
                            style={{ 
                                transition: 'all 0.15s ease',
                                transform: sidebarCollapsed ? 'rotate(180deg)' : 'rotate(0deg)'
                            }} 
                        />
                    </button>

                    {/* Canvas Wrapper */}
                    <div style={{ flex: 1, position: 'relative', overflow: 'hidden', background: '#FAF9F7' }}>
                        <div style={{ width: '100%', height: '100%', position: 'relative', overflow: 'hidden' }}>
                            <canvas
                                ref={canvasRef}
                                onMouseDown={handleMouseDown}
                                onMouseMove={handleMouseMove}
                                onMouseUp={handleMouseUp}
                                onWheel={handleWheel}
                                style={{ 
                                    display: showUploadZone ? 'none' : 'block', 
                                    cursor: getCursorStyle()
                                }}
                            />

                            {/* Upload Zone */}
                            {showUploadZone && (
                                <div 
                                    onClick={() => fileInputRef.current?.click()}
                                    style={{
                                        position: 'absolute',
                                        top: '50%',
                                        left: '50%',
                                        transform: 'translate(-50%, -50%)',
                                        width: '480px',
                                        maxWidth: '90%',
                                        padding: '60px 40px',
                                        background: '#ffffff',
                                        border: '2px dashed #e5e7eb',
                                        borderRadius: '12px',
                                        cursor: 'pointer',
                                        transition: 'all 0.15s ease',
                                        textAlign: 'center'
                                    }}
                                >
                                    <div style={{ fontSize: '48px', marginBottom: '16px', opacity: 0.8 }}>📁</div>
                                    <div style={{ fontSize: '18px', fontWeight: 600, color: '#37352f', marginBottom: '8px' }}>
                                        Upload an image
                                    </div>
                                    <div style={{ fontSize: '14px', color: '#787774' }}>
                                        Click to browse or drag and drop
                                    </div>
                                </div>
                            )}

                            {/* Floating Actions */}
                            {currentTool === 'retouch' && selectedObject && !showUploadZone && (
                                <div style={{
                                    position: 'absolute',
                                    bottom: '24px',
                                    right: '24px',
                                    background: '#ffffff',
                                    border: '1px solid #e5e7eb',
                                    borderRadius: '8px',
                                    padding: '4px',
                                    boxShadow: '0 2px 4px rgba(0, 0, 0, 0.06)',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: '2px',
                                    minWidth: '220px'
                                }}>
                                    <FloatingActionButton 
                                        icon={<Eraser size={16} />} 
                                        label="Erase" 
                                        onClick={handleErase}
                                        disabled={isGenerating || !(selectedObject && lines.some(l => l.objectId === selectedObject.id))}
                                    />
                                    <FloatingActionButton 
                                        icon={<Type size={16} />} 
                                        label="Replace Text" 
                                        onClick={handleReplaceText}
                                        disabled={isGenerating || !(selectedObject && lines.some(l => l.objectId === selectedObject.id))}
                                    />
                                    <FloatingActionButton 
                                        icon={<Wand2 size={16} />} 
                                        label="Generate" 
                                        onClick={handleGenerate}
                                        disabled={isGenerating || !(selectedObject && lines.some(l => l.objectId === selectedObject.id))}
                                    />
                                    <FloatingActionButton 
                                        icon={<Download size={16} />} 
                                        label="Download Mask" 
                                        onClick={downloadMask}
                                        disabled={isGenerating || !(selectedObject && lines.some(l => l.objectId === selectedObject.id))}
                                    />
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
                    setPromptModal(prev => ({ ...prev, isOpen: false, resolve: null }));
                }}
                onSubmit={(value) => {
                    if (promptModal.resolve) {
                        promptModal.resolve(value);
                    }
                    setPromptModal(prev => ({ ...prev, isOpen: false, resolve: null }));
                }}
                title={promptModal.title}
                description={promptModal.description}
                placeholder={promptModal.placeholder}
                defaultValue={promptModal.defaultValue}
            />

            <AlertModal
                isOpen={alertModal.isOpen}
                onClose={() => setAlertModal(prev => ({ ...prev, isOpen: false }))}
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
                    setConfirmModal(prev => ({ ...prev, isOpen: false, resolve: null }));
                }}
                onConfirm={() => {
                    if (confirmModal.resolve) {
                        confirmModal.resolve(true);
                    }
                    setConfirmModal(prev => ({ ...prev, isOpen: false, resolve: null }));
                }}
                title={confirmModal.title}
                message={confirmModal.message}
                confirmText={confirmModal.confirmText}
                isDanger={confirmModal.isDanger}
            />
        </>
    );
}

// Component for tool buttons
function ToolButton({ icon, label, active, onClick, collapsed }: { 
    icon: React.ReactNode; 
    label: string; 
    active: boolean; 
    onClick: () => void;
    collapsed: boolean;
}) {
    return (
        <button
            onClick={onClick}
            data-tool={label.toLowerCase()}
            style={{
                padding: collapsed ? '10px' : '8px 12px',
                border: 'none',
                borderRadius: '4px',
                background: active ? '#FAF9F7' : 'transparent',
                cursor: 'pointer',
                transition: 'all 0.15s ease',
                textAlign: 'left',
                display: 'flex',
                alignItems: 'center',
                justifyContent: collapsed ? 'center' : 'flex-start',
                gap: '10px',
                color: '#37352f'
            }}
            className="tool-card"
        >
            <div style={{ 
                fontSize: '18px', 
                width: '20px', 
                height: '20px', 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'center',
                flexShrink: 0,
                color: '#787774'
            }}>
                {icon}
            </div>
            {!collapsed && (
                <span style={{ 
                    fontSize: '14px', 
                    fontWeight: active ? 500 : 400,
                    color: '#37352f'
                }}>
                    {label}
                </span>
            )}
        </button>
    );
}

// Styles
const headerButtonStyle: React.CSSProperties = {
    padding: '6px 12px',
    border: '1px solid #e5e7eb',
    borderRadius: '6px',
    background: 'transparent',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: 500,
    color: '#787774',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    gap: '6px'
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
    color: '#787774',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center'
};

const sliderStyle: React.CSSProperties = {
    width: '100%',
    height: '2px',
    borderRadius: '2px',
    background: '#ebe5e8ff',
    outline: 'none',
    WebkitAppearance: 'none',
    appearance: 'none',
    cursor: 'pointer'
};

const floatingButtonStyle: React.CSSProperties = {
    padding: '10px 14px',
    background: 'transparent',
    color: '#37352f',
    border: 'none',
    borderRadius: '4px',
    cursor: 'pointer',
    fontWeight: 400,
    fontSize: '14px',
    transition: 'all 0.15s ease',
    display: 'flex',
    alignItems: 'center',
    gap: '10px',
    textAlign: 'left'
};

// Component for floating action buttons with hover/disabled styling
function FloatingActionButton({ 
    icon, 
    label, 
    onClick,
    disabled = false
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
                background: hover && !disabled ? '#F3F4F6' : 'transparent',
                opacity: disabled ? 0.5 : 1,
                cursor: disabled ? 'not-allowed' : 'pointer'
            }}
        >
            {icon}
            <span>{label}</span>
        </button>
    );
}
