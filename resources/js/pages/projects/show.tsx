import { Button } from '@/components/ui/button';
import { BatchProgress } from '@/components/batch-progress';
import { useGenerationProgress } from '@/hooks/use-generation-progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Star, Download, MoreHorizontal, BoxSelect, Square, SquareCheck, Edit, Maximize, RotateCw, Share, Trash2, Check, Plus } from 'lucide-react';
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
}

export default function ProjectShow({ project }: ProjectShowProps) {
    const [selectedImages, setSelectedImages] = useState<number[]>([]);
    const [favoriteImages, setFavoriteImages] = useState<number[]>([]);
    const [isEditingTitle, setIsEditingTitle] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const titleInputRef = useRef<HTMLInputElement>(null);
    
    // Track generation progress
    const { progress, isGenerating } = useGenerationProgress(project.id);
    
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

                    {/* Generation Progress */}
                    {isGenerating && progress && (
                        <div className="mb-6">
                            <BatchProgress
                                total={progress.expected_total}
                                completed={progress.completed}
                                failed={progress.failed}
                                status={progress.is_complete ? 'completed' : 'processing'}
                            />
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
                                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200" />
                                    
                                    {/* Checkbox - Always visible when selected, or on hover (above overlay) */}
                                    <div className={`absolute left-3 top-3 z-10 ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'} transition-opacity duration-200`}>
                                        <button 
                                            className="flex size-6 items-center justify-center rounded-xl transition-colors"
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
                                    <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10">
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-colors"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Expand"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Handle expand
                                            }}
                                        >
                                            <Maximize className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-colors"
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
                                            className="flex size-10 items-center justify-center rounded-full transition-colors"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Regenerate"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Handle regenerate
                                            }}
                                        >
                                            <RotateCw className="size-4" style={{ color: '#333333' }} />
                                        </button>
                                        <button 
                                            className="flex size-10 items-center justify-center rounded-full transition-colors"
                                            style={{ backgroundColor: '#F0F0F0' }}
                                            title="Download"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                // Handle download
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
        </AppLayout>
    );
}