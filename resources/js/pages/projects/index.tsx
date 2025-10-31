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
    MoreVertical,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
    images_count: number;
    is_favorite: boolean;
}

interface ProjectsPageProps {
    projects: Project[];
    success?: string;
}

type ViewMode = 'grid' | 'list';
type FilterTab = 'all' | 'recent' | 'favorites';
type SortOption = 'date-desc' | 'date-asc' | 'name-asc' | 'name-desc' | 'images-desc';

export default function ProjectsIndex({ projects = [], success }: ProjectsPageProps) {
    const page = usePage();
    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter') as FilterTab | null;
    
    const [viewMode, setViewMode] = useState<ViewMode>('grid');
    const [activeTab, setActiveTab] = useState<FilterTab>(filterParam || 'all');
    const [sortBy, setSortBy] = useState<SortOption>('date-desc');
    const [showSuccess, setShowSuccess] = useState(!!success);

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
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const handleToggleFavorite = (projectId: number, e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        router.post(`/projects/${projectId}/toggle-favorite`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (projectId: number) => {
        if (confirm('Are you sure you want to delete this project?')) {
            router.delete(`/projects/${projectId}`);
        }
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

                    {/* Projects Grid */}
                    <div className="mt-16">
                        {sortedProjects.length === 0 ? (
                            // Empty State
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <Link href="/projects/create">
                                    <Card className="group cursor-pointer border border-gray-200 transition-all hover:border-gray-300 hover:shadow-sm">
                                        <div className="aspect-video bg-gray-50 p-8">
                                            <div className="flex h-full items-center justify-center">
                                                <Plus className="size-12 text-gray-400 transition-transform group-hover:scale-110" />
                                            </div>
                                        </div>
                                        <CardContent className="p-6">
                                            <h3 className="text-[18px] font-semibold" style={{ color: '#191919' }}>
                                                Create New Project
                                            </h3>
                                            <p className="mt-2 text-sm font-normal" style={{ color: '#505050' }}>
                                                Get started with a new project
                                            </p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                        ) : (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {sortedProjects.map((project) => (
                                    <ProjectCard
                                        key={project.id}
                                        project={project}
                                        formatDate={formatDate}
                                        onToggleFavorite={handleToggleFavorite}
                                        onDelete={handleDelete}
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
}

function ProjectCard({ project, formatDate, onToggleFavorite, onDelete }: ProjectCardProps) {
    return (
        <Link href={`/projects/${project.id}`}>
            <Card className="group cursor-pointer border border-gray-200 transition-all hover:border-gray-300 hover:shadow-md">
                {/* Image Placeholder */}
                <div className="aspect-video bg-gradient-to-br from-gray-100 to-gray-200 p-0 relative overflow-hidden">
                    {project.featured_image ? (
                        <img 
                            src={project.featured_image} 
                            alt={project.title}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full items-center justify-center">
                            <div className="text-center">
                                <div className="mx-auto mb-3 size-12 rounded-full bg-white/80 flex items-center justify-center">
                                    <svg className="size-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p className="text-sm font-medium text-gray-500">No image</p>
                            </div>
                        </div>
                    )}
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors" />
                </div>
                
                {/* Card Content */}
                <CardContent className="p-6">
                    <h3 className="text-[18px] font-semibold group-hover:opacity-80" style={{ color: '#191919' }}>
                        {project.title}
                    </h3>
                    
                    <div className="mt-4 flex items-center justify-between">
                        <div className="flex items-center gap-3 text-sm">
                            <span style={{ color: '#505050' }}>{formatDate(project.created_at)}</span>
                            <Badge
                                variant="secondary"
                                className="text-xs font-medium"
                                style={{ backgroundColor: '#F5F5F5', color: '#505050' }}
                            >
                                {project.images_count} images
                            </Badge>
                        </div>
                        
                        {/* Actions Menu */}
                        <div className="flex items-center gap-1">
                            <button
                                onClick={(e) => onToggleFavorite(project.id, e)}
                                className="rounded p-1.5 transition-colors hover:bg-gray-50"
                                style={{ color: project.is_favorite ? '#ff4444' : '#191919' }}
                                title="Toggle favorite"
                            >
                                {project.is_favorite ? (
                                    <Heart className="size-4 fill-current" />
                                ) : (
                                    <Heart className="size-4" />
                                )}
                            </button>
                            
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    router.visit(`/projects/${project.id}/edit`);
                                }}
                                className="rounded p-1.5 transition-colors hover:bg-gray-50"
                                style={{ color: '#191919' }}
                                title="Edit project"
                            >
                                <Edit className="size-4" />
                            </button>
                            
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    onDelete(project.id);
                                }}
                                className="rounded p-1.5 transition-colors hover:bg-red-50"
                                style={{ color: '#ff4444' }}
                                title="Delete project"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}