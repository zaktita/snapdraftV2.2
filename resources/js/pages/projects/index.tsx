import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { NoProjectsYet } from '@/components/empty-state';
import { ProjectListSkeleton } from '@/components/ui/skeleton-loaders';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { create as create_project } from '@/routes/projects';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpDown,
    Check,
    CheckCircle,
    ChevronsUpDown,
    Edit,
    Grid3x3,
    Heart,
    LayoutGrid,
    List,
    MoreHorizontal,
    MoreVertical,
    Plus,
    Star,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'All Projects',
        href: '/projects',
    },
];

interface Project {
    id: number;
    title: string;
    featured_image?: string;
    created_at: string;
    updated_at: string;
    images_count: number;
    is_favorite: boolean;
}

interface PaginatedProjects {
    data: Project[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface ProjectsPageProps {
    projects: PaginatedProjects | Project[]; // Support both paginated and array format
    success?: string;
}

type ViewMode = 'grid' | 'list';
type FilterTab = 'all' | 'recent' | 'favorites';
type SortOption = 'date-desc' | 'date-asc' | 'name-asc' | 'name-desc' | 'images-desc';

export default function ProjectsIndex({ projects: projectsData = [], success }: ProjectsPageProps) {
    const page = usePage();
    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter') as FilterTab | null;
    
    // Extract projects array from paginated data or use directly if array
    const projects = Array.isArray(projectsData) ? projectsData : projectsData.data;
    const pagination = !Array.isArray(projectsData) ? projectsData : null;
    
    const [viewMode, setViewMode] = useState<ViewMode>('grid');
    const [activeTab, setActiveTab] = useState<FilterTab>(filterParam || 'all');
    const [sortBy, setSortBy] = useState<SortOption>('date-desc');
    const [showSuccess, setShowSuccess] = useState(!!success);
    
    // Local state to track optimistic favorite updates
    const [optimisticFavorites, setOptimisticFavorites] = useState<Record<number, boolean>>({});

    // Auto-hide success message after 5 seconds
    useEffect(() => {
        if (success) {
            const timer = setTimeout(() => setShowSuccess(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [success]);

    // Update active tab when URL changes
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const filterParam = urlParams.get('filter') as FilterTab | null;
        if (filterParam) {
            setActiveTab(filterParam);
        } else {
            setActiveTab('all');
        }
    }, [page.url]);

    // Filter projects based on active tab
    const filteredProjects = projects.filter((project) => {
        if (activeTab === 'recent') {
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
            return new Date(project.created_at) > oneWeekAgo;
        }
        if (activeTab === 'favorites') {
            return project.is_favorite;
        }
        return true; // 'all'
    });

    // Sort projects
    const sortedProjects = [...filteredProjects].sort((a, b) => {
        switch (sortBy) {
            case 'date-asc':
                return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
            case 'date-desc':
                return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
            case 'name-asc':
                return a.title.localeCompare(b.title);
            case 'name-desc':
                return b.title.localeCompare(a.title);
            case 'images-desc':
                return b.images_count - a.images_count;
            default:
                return 0;
        }
    });

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
        
        // Less than a minute
        if (diffInSeconds < 60) {
            return 'a few seconds ago';
        }
        
        // Less than an hour
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) {
            return `${diffInMinutes} min ago`;
        }
        
        // Less than a day
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) {
            return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
        }
        
        // Less than a week
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 7) {
            return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
        }
        
        // Less than a month
        const diffInWeeks = Math.floor(diffInDays / 7);
        if (diffInWeeks < 4) {
            return `${diffInWeeks} week${diffInWeeks > 1 ? 's' : ''} ago`;
        }
        
        // Default to date format
        return date.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    const handleToggleFavorite = (projectId: number, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Get current favorite state (from optimistic state or original project data)
        const project = projects.find(p => p.id === projectId);
        const currentState = optimisticFavorites[projectId] ?? project?.is_favorite ?? false;
        const newState = !currentState;
        
        // Optimistically update the UI immediately
        setOptimisticFavorites(prev => ({
            ...prev,
            [projectId]: newState
        }));
        
        // Send request to backend
        router.post(`/projects/${projectId}/toggle-favorite`, {}, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                // Revert optimistic update on error
                setOptimisticFavorites(prev => ({
                    ...prev,
                    [projectId]: currentState
                }));
            },
            onSuccess: () => {
                // Clear optimistic state on success so backend data takes over
                setOptimisticFavorites(prev => {
                    const newState = { ...prev };
                    delete newState[projectId];
                    return newState;
                });
            }
        });
    };

    const handleDelete = (projectId: number) => {
        if (confirm('Are you sure you want to delete this project? This will delete all images in the project.')) {
            router.delete(`/projects/${projectId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleRename = (projectId: number, newTitle: string) => {
        router.patch(`/projects/${projectId}`, { title: newTitle }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['projects'] });
            },
            onError: (errors) => {
                console.error('Failed to rename project:', errors);
            }
        });
    };

    const handleGenerateMore = (projectId: number, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        // Navigate to the project's generation page or show modal
        console.log('Generate more images for project:', projectId);
        // TODO: Implement generation flow
        // router.visit(`/projects/${projectId}/generate`);
    };

    const tabs = [
        { key: 'all' as const, label: 'All Projects', href: '/projects' },
        { key: 'recent' as const, label: 'Recent', href: '/projects?filter=recent' },
        { key: 'favorites' as const, label: 'Favorites', href: '/projects?filter=favorites' },
    ];

    const sortOptions = [
        { value: 'date-desc' as const, label: 'Newest First' },
        { value: 'date-asc' as const, label: 'Oldest First' },
        { value: 'name-asc' as const, label: 'Name A-Z' },
        { value: 'name-desc' as const, label: 'Name Z-A' },
        { value: 'images-desc' as const, label: 'Most Images' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All Projects" />

            {/* Clean, spacious layout with white background */}
            <div className="min-h-screen bg-white">
                <div className="mx-auto px-8 py-16">
                    {/* Success Message */}
                    {showSuccess && success && (
                        <Alert className="relative mb-12 border-green-600/20 bg-green-50 dark:bg-green-950/20">
                            <CheckCircle className="size-4 text-green-600" />
                            <AlertTitle>Success</AlertTitle>
                            <AlertDescription>{success}</AlertDescription>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="absolute right-2 top-2 size-6"
                                onClick={() => setShowSuccess(false)}
                            >
                                <span className="sr-only">Close</span>
                                ×
                            </Button>
                        </Alert>
                    )}

                    {/* Header Section */}
                    <div className="mb-16">
                        <div className="flex items-start justify-between">
                            <div>
                                <h1 className="mb-3 text-[32px] font-black leading-tight" style={{ color: '#191919' }}>
                                    Projects
                                </h1>
                                <p className="text-base font-normal" style={{ color: '#505050' }}>
                                    Manage and organize your projects
                                </p>
                            </div>
                            <div className="flex items-center gap-4">
                                {/* Sort Button */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            className="h-9 justify-start gap-2 px-3 font-medium" 
                                            style={{ color: '#505050' }}
                                        >
                                            <ChevronsUpDown className="size-4" />
                                            Sort by: {sortOptions.find(option => option.value === sortBy)?.label}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-48">
                                        {sortOptions.map((option) => (
                                            <DropdownMenuItem 
                                                key={option.value}
                                                onClick={() => setSortBy(option.value)}
                                                className="gap-2"
                                            >
                                                {sortBy === option.value && <Check className="size-4" />}
                                                {option.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* View Toggle */}
                                <div className="flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
                                    <Button
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('grid')}
                                        className="size-8 p-0"
                                    >
                                        <LayoutGrid className="size-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'list' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('list')}
                                        className="size-8 p-0"
                                    >
                                        <List className="size-4" />
                                    </Button>
                                </div>

                                {/* New Project Button */}
                                <Button 
                                    onClick={() => router.visit(create_project().url)}
                                    className="h-9 gap-2 px-4 font-medium"
                                    style={{ backgroundColor: '#191919', color: 'white' }}
                                >
                                    <Plus className="size-4" />
                                    New Project
                                </Button>
                            </div>
                        </div>

                        {/* Filter Tabs */}
                        <div className="mt-12 flex gap-2">
                            {tabs.map((tab) => (
                                <Link
                                    key={tab.key}
                                    href={tab.href}
                                    className={`rounded-lg px-4 py-2.5 text-sm font-medium transition-colors ${
                                        activeTab === tab.key
                                            ? 'text-white'
                                            : 'hover:bg-gray-50'
                                    }`}
                                    style={activeTab === tab.key 
                                        ? { backgroundColor: '#191919' }
                                        : { color: '#505050' }
                                    }
                                >
                                    {tab.label}
                                </Link>
                            ))}
                        </div>
                    </div>

                    {/* Projects Grid/List */}
                    <div className="mt-16">
                        {projects.length === 0 ? (
                            // Truly no projects at all
                            <NoProjectsYet />
                        ) : sortedProjects.length === 0 ? (
                            // Empty State for filtered view
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <Link href="/projects/create">
                                    <div className="group cursor-pointer rounded-lg bg-muted/40 hover:bg-muted/60 transition-colors">
                                        <div className="aspect-video bg-muted/60 p-8">
                                            <div className="flex h-full items-center justify-center">
                                                <Plus className="size-12 text-muted-foreground transition-transform group-hover:scale-110" />
                                            </div>
                                        </div>
                                        <div className="p-6">
                                            <h3 className="text-[18px] font-semibold">
                                                Create New Project
                                            </h3>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                No projects match your filter
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        ) : viewMode === 'grid' ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {sortedProjects.map((project) => (
                                    <ProjectCard
                                        key={project.id}
                                        project={project}
                                        formatDate={formatDate}
                                        onToggleFavorite={handleToggleFavorite}
                                        onDelete={handleDelete}
                                        onRename={handleRename}
                                        onGenerateMore={handleGenerateMore}
                                        isFavorite={optimisticFavorites[project.id] ?? project.is_favorite}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="space-y-3 flex flex-col">
                                {sortedProjects.map((project) => (
                                    <ProjectListItem
                                        key={project.id}
                                        project={project}
                                        formatDate={formatDate}
                                        onToggleFavorite={handleToggleFavorite}
                                        onDelete={handleDelete}
                                        onRename={handleRename}
                                        onGenerateMore={handleGenerateMore}
                                        isFavorite={optimisticFavorites[project.id] ?? project.is_favorite}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface ProjectCardProps {
    project: Project;
    formatDate: (date: string) => string;
    onToggleFavorite: (id: number, e: React.MouseEvent) => void;
    onDelete: (id: number) => void;
    onRename: (id: number, newTitle: string) => void;
    onGenerateMore: (id: number, e: React.MouseEvent) => void;
    isFavorite: boolean;
}

function ProjectCard({ project, formatDate, onToggleFavorite, onDelete, onRename, onGenerateMore, isFavorite }: ProjectCardProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const [showActions, setShowActions] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const dropdownRef = useRef<HTMLDivElement>(null);

    // Sync local state when project.title changes from backend
    useEffect(() => {
        setEditTitle(project.title);
    }, [project.title]);

    useEffect(() => {
        if (isEditing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [isEditing]);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setShowDropdown(false);
            }
        };

        if (showDropdown) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showDropdown]);

    const handleRename = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsEditing(true);
        setShowDropdown(false);
    };

    const handleBlur = () => {
        const trimmedTitle = editTitle.trim();
        if (trimmedTitle && trimmedTitle !== project.title) {
            onRename(project.id, trimmedTitle);
        } else {
            setEditTitle(project.title);
        }
        setIsEditing(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleBlur();
        } else if (e.key === 'Escape') {
            setIsEditing(false);
            setEditTitle(project.title);
        }
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setShowDropdown(false);
        onDelete(project.id);
    };

    const toggleDropdown = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setShowDropdown(!showDropdown);
    };

    return (
        <Link href={`/projects/${project.id}`}>
            <div 
                className="group cursor-pointer rounded-xl overflow-hidden bg-background border border-border hover:border-border/60 transition-all"
                onMouseEnter={() => setShowActions(true)}
                onMouseLeave={() => setShowActions(false)}
            >
                {/* Image Container */}
                <div className="aspect-[4/3] bg-muted relative overflow-hidden">
                    {project.featured_image ? (
                        <img 
                            src={project.featured_image} 
                            alt={project.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full items-center justify-center">
                            <div className="text-center">
                                <div className="mx-auto mb-2 size-10 rounded-full bg-muted-foreground/10 flex items-center justify-center">
                                    <svg className="size-5 text-muted-foreground/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    )}
                    
                    {/* Dark overlay on hover */}
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors pointer-events-none" />
                    
                    {/* Favorite Icon - Top Right on Hover */}
                    <button
                        onClick={(e) => onToggleFavorite(project.id, e)}
                        className={`absolute top-3 right-3 rounded-full p-2 bg-background/90 backdrop-blur-sm shadow-sm transition-all ${
                            showActions ? 'opacity-100' : 'opacity-0'
                        }`}
                        title="Toggle favorite"
                    >
                        {isFavorite ? (
                            <Star className="size-4 fill-yellow-400 stroke-yellow-400" />
                        ) : (
                            <Star className="size-4 stroke-muted-foreground" />
                        )}
                    </button>
                </div>
                
                {/* Card Content */}
                <div className="p-4">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                            {isEditing ? (
                                <input
                                    ref={inputRef}
                                    type="text"
                                    value={editTitle}
                                    onChange={(e) => setEditTitle(e.target.value)}
                                    onBlur={handleBlur}
                                    onKeyDown={handleKeyDown}
                                    onClick={(e) => e.stopPropagation()}
                                    className="w-full text-base font-medium border-b-2 border-primary bg-transparent outline-none mb-1"
                                />
                            ) : (
                                <h3 className="text-base font-medium text-foreground mb-1 truncate">
                                    {project.title}
                                </h3>
                            )}
                            
                            <p className="text-xs text-muted-foreground">
                                Edited {formatDate(project.updated_at)}
                            </p>
                        </div>

                        {/* Three Dots Menu */}
                        <div 
                            ref={dropdownRef}
                            className={`relative flex-shrink-0 transition-all ${showActions ? 'opacity-100' : 'opacity-0'}`}
                        >
                            <button
                                onClick={toggleDropdown}
                                className="rounded-full p-1 hover:bg-muted transition-colors"
                                title="More actions"
                            >
                                <MoreHorizontal className="size-4" />
                            </button>
                            
                            {/* Dropdown Menu */}
                            <div 
                                className={`absolute right-0 bottom-full mb-1 w-40 rounded-lg bg-background border border-border shadow-lg py-1 z-10 ${
                                    showDropdown ? 'block' : 'hidden'
                                }`}
                            >
                                <button
                                    onClick={handleRename}
                                    className="w-full px-4 py-2 text-left text-sm hover:bg-muted transition-colors flex items-center gap-2"
                                >
                                    <Edit className="size-3.5" />
                                    Rename
                                </button>
                                <button
                                    onClick={handleDelete}
                                    className="w-full px-4 py-2 text-left text-sm hover:bg-muted transition-colors flex items-center gap-2 text-destructive"
                                >
                                    <Trash2 className="size-3.5" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    );
}

interface ProjectListItemProps {
    project: Project;
    formatDate: (date: string) => string;
    onToggleFavorite: (id: number, e: React.MouseEvent) => void;
    onDelete: (id: number) => void;
    onRename: (id: number, newTitle: string) => void;
    onGenerateMore: (id: number, e: React.MouseEvent) => void;
    isFavorite: boolean;
}

function ProjectListItem({ project, formatDate, onToggleFavorite, onDelete, onRename, onGenerateMore, isFavorite }: ProjectListItemProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editTitle, setEditTitle] = useState(project.title);
    const [showActions, setShowActions] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const dropdownRef = useRef<HTMLDivElement>(null);

    // Sync local state when project.title changes from backend
    useEffect(() => {
        setEditTitle(project.title);
    }, [project.title]);

    useEffect(() => {
        if (isEditing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [isEditing]);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setShowDropdown(false);
            }
        };

        if (showDropdown) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showDropdown]);

    const handleRename = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsEditing(true);
        setShowDropdown(false);
    };

    const handleBlur = () => {
        const trimmedTitle = editTitle.trim();
        if (trimmedTitle && trimmedTitle !== project.title) {
            onRename(project.id, trimmedTitle);
        } else {
            setEditTitle(project.title);
        }
        setIsEditing(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleBlur();
        } else if (e.key === 'Escape') {
            setIsEditing(false);
            setEditTitle(project.title);
        }
    };

    const handleDelete = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setShowDropdown(false);
        onDelete(project.id);
    };

    const toggleDropdown = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setShowDropdown(!showDropdown);
    };

    return (
        <Link href={`/projects/${project.id}`}>
            <div 
                className="group cursor-pointer rounded-lg bg-background border border-border hover:border-border/60 transition-all p-4"
                onMouseEnter={() => setShowActions(true)}
                onMouseLeave={() => setShowActions(false)}
            >
                <div className="flex items-center gap-4">
                    {/* Thumbnail */}
                    <div className="h-16 w-24 shrink-0 overflow-hidden rounded-lg bg-muted relative">
                        {project.featured_image ? (
                            <img 
                                src={project.featured_image} 
                                alt={project.title}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center">
                                <svg className="size-4 text-muted-foreground/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        )}
                    </div>

                    {/* Content */}
                    <div className="flex min-w-0 flex-1 items-center justify-between gap-4">
                        <div className="min-w-0 flex-1">
                            {/* Title */}
                            {isEditing ? (
                                <input
                                    ref={inputRef}
                                    type="text"
                                    value={editTitle}
                                    onChange={(e) => setEditTitle(e.target.value)}
                                    onBlur={handleBlur}
                                    onKeyDown={handleKeyDown}
                                    onClick={(e) => e.stopPropagation()}
                                    className="w-full text-base font-medium border-b-2 border-primary bg-transparent outline-none"
                                />
                            ) : (
                                <h3 className="truncate text-base font-medium text-foreground">
                                    {project.title}
                                </h3>
                            )}

                            {/* Subtitle */}
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Edited {formatDate(project.updated_at)}
                            </p>
                        </div>

                        {/* Actions */}
                        <div className="flex shrink-0 items-center gap-2">
                            {/* Favorite Icon */}
                            <button
                                onClick={(e) => onToggleFavorite(project.id, e)}
                                className={`rounded-full p-1.5 hover:bg-muted transition-all ${
                                    showActions ? 'opacity-100' : 'opacity-0'
                                }`}
                                title="Toggle favorite"
                            >
                                {isFavorite ? (
                                    <Star className="size-4 fill-yellow-400 stroke-yellow-400" />
                                ) : (
                                    <Star className="size-4 stroke-muted-foreground" />
                                )}
                            </button>

                            {/* Three Dots Menu */}
                            <div 
                                ref={dropdownRef}
                                className={`relative transition-all ${showActions ? 'opacity-100' : 'opacity-0'}`}
                            >
                                <button
                                    onClick={toggleDropdown}
                                    className="rounded-full p-1.5 hover:bg-muted"
                                    title="More actions"
                                >
                                    <MoreHorizontal className="size-4" />
                                </button>
                                
                                {/* Dropdown Menu */}
                                <div 
                                    className={`absolute right-0 top-full mt-1 w-40 rounded-lg bg-background border border-border shadow-lg py-1 z-10 ${
                                        showDropdown ? 'block' : 'hidden'
                                    }`}
                                >
                                    <button
                                        onClick={handleRename}
                                        className="w-full px-4 py-2 text-left text-sm hover:bg-muted transition-colors flex items-center gap-2"
                                    >
                                        <Edit className="size-3.5" />
                                        Rename
                                    </button>
                                    <button
                                        onClick={handleDelete}
                                        className="w-full px-4 py-2 text-left text-sm hover:bg-muted transition-colors flex items-center gap-2 text-destructive"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    );
}