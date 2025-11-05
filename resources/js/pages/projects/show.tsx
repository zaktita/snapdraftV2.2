import { Button } from '@/components/ui/button';
import { BatchProgress } from '@/components/batch-progress';
import { useGenerationProgress } from '@/hooks/use-generation-progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Star, Download, MoreHorizontal, BoxSelect, Square, SquareCheck, Edit, Maximize, RotateCw, Share, Trash2, Check, Plus, CheckCircle, X, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';

interface Image {
    id: number;
    url: string;
    thumbnail_url: string;
    prompt?: string;
    is_favorite?: boolean;
}

interface Project {
    id: number;
    title: string;
    created_at: string;
    updated_at: string;
    images_count: number;
    is_favorite: boolean;
    description?: string;
    featured_image?: string;
    images: Image[];
}

interface ProjectShowProps {
    project: Project;
    justCreated?: boolean; // Flag to indicate project was just created
    expectedImages?: number; // Expected number of images to generate
}

export default function ProjectShow({ project, justCreated = false, expectedImages = 0 }: ProjectShowProps) {
    const page = usePage<{ success?: string }>();
    const [selectedImages, setSelectedImages] = useState<number[]>([]);
    const [favoriteImages, setFavoriteImages] = useState<number[]>([]);
    const [isEditingTitle, setIsEditingTitle] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const [showSuccess, setShowSuccess] = useState(false);
    const [showOptimisticProgress, setShowOptimisticProgress] = useState(justCreated);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [lightboxImageIndex, setLightboxImageIndex] = useState(0);
    const titleInputRef = useRef<HTMLInputElement>(null);
    
    // Show success message on mount if present
    useEffect(() => {
        if (page.props.success) {
            setShowSuccess(true);
            // Auto-hide after 5 seconds
            const timer = setTimeout(() => setShowSuccess(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [page.props.success]);
    
    // Track generation progress with auto-reload on completion
    const { progress, isGenerating } = useGenerationProgress(
        project.id, 
        true,
        () => {
            // Hide optimistic progress and reload the page when generation completes
            setShowOptimisticProgress(false);
            router.reload({ only: ['project'] });
        }
    );
    
    // Hide optimistic progress once real progress data arrives
    useEffect(() => {
        if (progress && showOptimisticProgress) {
            setShowOptimisticProgress(false);
        }
    }, [progress]);
    
    useEffect(() => {
        if (isEditingTitle && titleInputRef.current) {
            titleInputRef.current.focus();
            titleInputRef.current.select();
        }
    }, [isEditingTitle]);

    const handleTitleDoubleClick = () => {
        setIsEditingTitle(true);
    };

    const handleTitleBlur = () => {
        setIsEditingTitle(false);
        if (editTitle.trim() && editTitle !== project.title) {
            router.patch(`/projects/${project.id}`, { title: editTitle }, {
                preserveScroll: true,
            });
        } else {
            setEditTitle(project.title);
        }
    };

    const handleTitleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleTitleBlur();
        } else if (e.key === 'Escape') {
            setIsEditingTitle(false);
            setEditTitle(project.title);
        }
    };

    const handleGenerateMore = () => {
        // Post to generate endpoint
        router.post(`/projects/${project.id}/generate`, {}, {
            preserveScroll: true,
        });
    };
    
    const openLightbox = (index: number) => {
        setLightboxImageIndex(index);
        setLightboxOpen(true);
    };
    
    const closeLightbox = () => {
        setLightboxOpen(false);
    };
    
    const goToPrevImage = () => {
        setLightboxImageIndex((prev) => (prev === 0 ? project.images.length - 1 : prev - 1));
    };
    
    const goToNextImage = () => {
        setLightboxImageIndex((prev) => (prev === project.images.length - 1 ? 0 : prev + 1));
    };
    
    // Keyboard navigation for lightbox
    useEffect(() => {
        if (!lightboxOpen) return;
        
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') goToPrevImage();
            if (e.key === 'ArrowRight') goToNextImage();
        };
        
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [lightboxOpen, project.images.length]);
    
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Projects',
            href: '/projects',
        },
        {
            title: project.title,
            href: `/projects/${project.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.title} />

            <div className="min-h-screen bg-white">
                <div className="mx-auto px-8 py-8">
                    {/* Success Toast */}
                    {showSuccess && page.props.success && (
                        <div className="mb-6 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
                            <div className="flex items-center gap-3">
                                <CheckCircle className="size-5" />
                                <span className="font-medium">{page.props.success}</span>
                            </div>
                            <button
                                onClick={() => setShowSuccess(false)}
                                className="text-green-600 hover:text-green-800"
                            >
                                <Plus className="size-4 rotate-45" />
                            </button>
                        </div>
                    )}

                    {/* Header */}
                    <div className="mb-8 flex items-center justify-between">
                        {/* Left side */}
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="sm" asChild className="text-gray-500">
                                <Link href="/projects" className="flex items-center gap-2">
                                    <ArrowLeft className="size-4" />
                                    My Projects
                                </Link>
                            </Button>
                            
                        </div>

                        {/* Right side */}
                        <div className="flex items-center gap-3">
                            <Button 
                                variant="ghost" 
                                size="sm" 
                                className="gap-2 border hover:bg-gray-100"
                                style={{ borderColor: '#E0E0E0', color: '#333333', minWidth: '125px' }}
                                onClick={() => {
                                    if (selectedImages.length === project.images.length) {
                                        setSelectedImages([]);
                                    } else {
                                        setSelectedImages(project.images.map((_, idx) => idx));
                                    }
                                }}
                            >
                                {selectedImages.length === project.images.length ? (
                                    <>
                                        <Square className="size-4" />
                                        Deselect All
                                    </>
                                ) : (
                                    <>
                                        <SquareCheck className="size-4" />
                                        Select All
                                    </>
                                )}
                            </Button>
                            <Button 
                                size="sm" 
                                className="gap-2"
                                style={{ 
                                    backgroundColor: selectedImages.length > 0 ? '#1a1a1a' : '#F0F0F0', 
                                    color: selectedImages.length > 0 ? '#ffffff' : '#A9A9A9',
                                    cursor: selectedImages.length === 0 ? 'not-allowed' : 'pointer',
                                    border: 'none'
                                }}
                                disabled={selectedImages.length === 0}
                                onClick={async () => {
                                    if (selectedImages.length === 0) return;
                                    
                                    // Import JSZip dynamically
                                    const JSZip = (await import('jszip')).default;
                                    const zip = new JSZip();
                                    
                                    // Download each selected image and add to zip
                                    const promises = selectedImages.map(async (index) => {
                                        const image = project.images[index];
                                        try {
                                            const response = await fetch(image.url);
                                            const blob = await response.blob();
                                            const filename = `${project.title}_image_${index + 1}.jpg`;
                                            zip.file(filename, blob);
                                        } catch (error) {
                                            console.error(`Failed to download image ${index}:`, error);
                                        }
                                    });
                                    
                                    await Promise.all(promises);
                                    
                                    // Generate zip file and trigger download
                                    const content = await zip.generateAsync({ type: 'blob' });
                                    const link = document.createElement('a');
                                    link.href = URL.createObjectURL(content);
                                    link.download = `${project.title}_images.zip`;
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    URL.revokeObjectURL(link.href);
                                }}
                            >
                                <Download className="size-4" />
                                Download ( {selectedImages.length} )
                            </Button>
                        </div>
                    </div>

                    {/* Generation Progress - Show Optimistically if Just Created */}
                    {(showOptimisticProgress || (isGenerating && progress)) && (
                        <div className="mb-6">
                            {showOptimisticProgress ? (
                                // Optimistic progress - show immediately while waiting for first poll
                                <BatchProgress
                                    total={expectedImages || 1}
                                    completed={0}
                                    failed={0}
                                    status="processing"
                                />
                            ) : progress ? (
                                // Real progress from API
                                <BatchProgress
                                    total={progress.expected_total}
                                    completed={progress.completed}
                                    failed={progress.failed}
                                    status={progress.is_complete ? 'completed' : 'processing'}
                                />
                            ) : null}
                        </div>
                    )}

                    {/* Project Title */}
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            {isEditingTitle ? (
                                <input
                                    ref={titleInputRef}
                                    type="text"
                                    value={editTitle}
                                    onChange={(e) => setEditTitle(e.target.value)}
                                    onBlur={handleTitleBlur}
                                    onKeyDown={handleTitleKeyDown}
                                    className="text-2xl font-semibold text-gray-900 border-b-2 border-blue-500 bg-transparent outline-none mb-1"
                                />
                            ) : (
                                <h1 
                                    className="text-2xl font-semibold text-gray-900 mb-1 cursor-text hover:opacity-80 transition-opacity"
                                    onDoubleClick={handleTitleDoubleClick}
                                    title="Double-click to rename"
                                >
                                    {project.title}
                                </h1>
                            )}
                            <p className="text-gray-500">
                                {project.images_count} images
                            </p>
                        </div>
                        
                        <Button
                            size="sm"
                            className="gap-2"
                            style={{ backgroundColor: '#1a1a1a', color: '#ffffff', border: 'none' }}
                            onClick={handleGenerateMore}
                        >
                            <Plus className="size-4" />
                            Generate More
                        </Button>
                    </div>

                    {/* Images Grid */}
                    {project.images.length > 0 ? (
                        <div className="grid grid-cols-5 gap-4">
                            {project.images.map((image, index) => {
                                const isSelected = selectedImages.includes(index);
                                const isFavorite = favoriteImages.includes(index);
                                
                                return (
                                    <div
                                        key={image.id}
                                        className="group relative aspect-square overflow-hidden bg-gray-100 cursor-pointer"
                                        style={{ borderRadius: '12px' }}
                                        onClick={() => {
                                            setSelectedImages(prev => 
                                                prev.includes(index) 
                                                    ? prev.filter(i => i !== index)
                                                    : [...prev, index]
                                            );
                                        }}
                                    >
                                        <img
                                            src={image.thumbnail_url}
                                            alt={image.prompt || `${project.title} - Image ${index + 1}`}
                                            className="h-full w-full object-cover"
                                        />
                                    
                                    {/* Selection Indicator - Always visible when selected */}
                                    {isSelected && (
                                        <div className="absolute inset-0 bg-black/20 pointer-events-none" />
                                    )}
                                    
                                    {/* Hover Overlay */}
                                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none" />
                                    
                                    {/* Checkbox - Always visible when selected, or on hover (above overlay) */}
                                    <div className={`absolute left-3 top-3 z-20 ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'} transition-opacity duration-200`}>
                                        <button 
                                            className="flex size-6 items-center justify-center rounded-xl transition-colors hover:scale-110"
                                            style={{ 
                                                backgroundColor: isSelected ? '#333333' : 'white',
                                            }}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setSelectedImages(prev => 
                                                    prev.includes(index) 
                                                        ? prev.filter(i => i !== index)
                                                        : [...prev, index]
                                                );
                                            }}
                                        >
                                            {isSelected && <Check className="size-4 text-white" />}
                                        </button>
                                    </div>
                                    
                                    {/* Bottom Center - Action Icons (above overlay) */}
                                    <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-20">
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Expand"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                openLightbox(index);
                                            }}
                                        >
                                            <Maximize className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Edit"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Open canvas editor with image
                                                const encodedImageUrl = encodeURIComponent(image.url);
                                                const encodedTitle = encodeURIComponent(project.title);
                                                router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}`);
                                            }}
                                        >
                                            <Edit className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Regenerate"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Handle regenerate - post to regenerate endpoint
                                                router.post(`/projects/${project.id}/images/${image.id}/regenerate`, {}, {
                                                    preserveScroll: true,
                                                });
                                            }}
                                        >
                                            <RotateCw className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-all hover:scale-110 hover:shadow-lg"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Download"
                                            onClick={async (e) => {
                                                e.stopPropagation();
                                                // Handle download
                                                try {
                                                    const response = await fetch(image.url);
                                                    const blob = await response.blob();
                                                    const url = URL.createObjectURL(blob);
                                                    const link = document.createElement('a');
                                                    link.href = url;
                                                    link.download = `${project.title}_image_${index + 1}.jpg`;
                                                    document.body.appendChild(link);
                                                    link.click();
                                                    document.body.removeChild(link);
                                                    URL.revokeObjectURL(url);
                                                } catch (error) {
                                                    console.error('Failed to download image:', error);
                                                }
                                            }}
                                        >
                                            <Download className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-gray-500 mb-4">No images generated yet.</p>
                            <Button
                                onClick={handleGenerateMore}
                                className="gap-2"
                                style={{ backgroundColor: '#1a1a1a', color: '#ffffff', border: 'none' }}
                            >
                                <Plus className="size-4" />
                                Generate Images
                            </Button>
                        </div>
                    )}
                </div>
            </div>
            
            {/* Lightbox Modal */}
            {lightboxOpen && project.images.length > 0 && (
                <div 
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
                    onClick={closeLightbox}
                >
                    {/* Close Button */}
                    <button
                        className="absolute right-6 top-6 z-50 flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                        onClick={closeLightbox}
                        title="Close (Esc)"
                    >
                        <X className="size-6" />
                    </button>
                    
                    {/* Image Counter */}
                    <div className="absolute left-1/2 top-6 z-50 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm text-white backdrop-blur-sm">
                        {lightboxImageIndex + 1} / {project.images.length}
                    </div>
                    
                    {/* Previous Button */}
                    {project.images.length > 1 && (
                        <button
                            className="absolute left-6 top-1/2 z-50 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                            onClick={(e) => {
                                e.stopPropagation();
                                goToPrevImage();
                            }}
                            title="Previous (←)"
                        >
                            <ChevronLeft className="size-6" />
                        </button>
                    )}
                    
                    {/* Next Button */}
                    {project.images.length > 1 && (
                        <button
                            className="absolute right-6 top-1/2 z-50 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20"
                            onClick={(e) => {
                                e.stopPropagation();
                                goToNextImage();
                            }}
                            title="Next (→)"
                        >
                            <ChevronRight className="size-6" />
                        </button>
                    )}
                    
                    {/* Main Image */}
                    <div 
                        className="relative max-h-[90vh] max-w-[90vw]"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <img
                            src={project.images[lightboxImageIndex].url}
                            alt={project.images[lightboxImageIndex].prompt || `${project.title} - Image ${lightboxImageIndex + 1}`}
                            className="max-h-[90vh] max-w-[90vw] object-contain"
                        />
                        
                        {/* Image Actions */}
                        <div className="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-full bg-white/10 p-3 backdrop-blur-sm">
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30"
                                title="Edit"
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    const encodedImageUrl = encodeURIComponent(image.url);
                                    const encodedTitle = encodeURIComponent(project.title);
                                    router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedImageUrl}&title=${encodedTitle}`);
                                }}
                            >
                                <Edit className="size-4" />
                            </button>
                            
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30"
                                title="Download"
                                onClick={async () => {
                                    const image = project.images[lightboxImageIndex];
                                    try {
                                        const response = await fetch(image.url);
                                        const blob = await response.blob();
                                        const url = URL.createObjectURL(blob);
                                        const link = document.createElement('a');
                                        link.href = url;
                                        link.download = `${project.title}_image_${lightboxImageIndex + 1}.jpg`;
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                        URL.revokeObjectURL(url);
                                    } catch (error) {
                                        console.error('Failed to download image:', error);
                                    }
                                }}
                            >
                                <Download className="size-4" />
                            </button>
                            
                            <button
                                className="flex size-10 items-center justify-center rounded-full bg-white/20 text-white transition-all hover:bg-white/30"
                                title="Regenerate"
                                onClick={() => {
                                    const image = project.images[lightboxImageIndex];
                                    router.post(`/projects/${project.id}/images/${image.id}/regenerate`, {}, {
                                        preserveScroll: true,
                                    });
                                    closeLightbox();
                                }}
                            >
                                <RotateCw className="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}