import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { csrfHeaders } from '@/lib/csrf';
import { Search as SearchIcon, FolderOpen, Image, Clock, Loader, Star, MoreVertical } from 'lucide-react';
import { useState, FormEvent } from 'react';

interface SearchResult {
    projects: Array<{
        id: number;
        title: string;
        name: string;
        description?: string;
        created_at: string;
        featured_image?: string;
        images_count: number;
        is_favorite: boolean;
    }>;
    images: Array<{
        id: number;
        project_id: number;
        prompt: string;
        url: string;
        created_at: string;
    }>;
    query: string;
    total: number;
}

export default function Search() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [hasSearched, setHasSearched] = useState(false);

    const handleSearch = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        
        if (!query.trim()) return;

        setLoading(true);
        setHasSearched(true);

        try {
            const response = await fetch('/search', {
                method: 'POST',
                headers: csrfHeaders({
                    'Content-Type': 'application/json',
                }),
                body: JSON.stringify({ q: query }),
            });

            if (response.ok) {
                const data = await response.json();
                setResults(data);
            } else {
                setResults(null);
            }
        } catch (error) {
            console.error('Search error:', error);
            setResults(null);
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
        
        if (diffInSeconds < 60) return 'a few seconds ago';
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) return `${diffInMinutes} min ago`;
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 7) return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
        
        const diffInWeeks = Math.floor(diffInDays / 7);
        if (diffInWeeks < 4) return `${diffInWeeks} week${diffInWeeks > 1 ? 's' : ''} ago`;
        
        return date.toLocaleDateString('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <AppLayout>
            <Head title="Search" />
            
            <div className="min-h-screen bg-white">
                <div className="mx-auto px-8 py-16">
                    {/* Header */}
                    <div className="mb-16">
                        <h1 className="mb-3 text-[32px] font-black leading-tight" style={{ color: '#191919' }}>
                            Search
                        </h1>
                        <p className="text-base font-normal" style={{ color: '#505050' }}>
                            Find your projects and images
                        </p>
                    </div>

                    {/* Search Bar */}
                    <div className="mb-12">
                        <form onSubmit={handleSearch} className="flex gap-4">
                            <div className="relative flex-1">
                                <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                                <Input
                                    placeholder="Search projects, images, or descriptions..."
                                    className="pl-10 h-11"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                            </div>
                            <Button 
                                type="submit" 
                                disabled={loading}
                                className="h-11 px-6 font-medium"
                                style={{ backgroundColor: '#191919', color: 'white' }}
                            >
                                {loading ? (
                                    <>
                                        <Loader className="mr-2 h-4 w-4 animate-spin" />
                                        Searching...
                                    </>
                                ) : (
                                    'Search'
                                )}
                            </Button>
                        </form>
                    </div>

                    {/* Results */}
                    {hasSearched && results ? (
                        <div className="space-y-12">
                            {results.total === 0 ? (
                                <div className="rounded-lg bg-muted/40 p-12">
                                    <div className="text-center">
                                        <SearchIcon className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
                                        <h2 className="text-2xl font-semibold mb-2">No results found</h2>
                                        <p className="text-muted-foreground max-w-md mx-auto">
                                            No projects or images match "{results.query}". Try a different search term.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    {/* Projects Results */}
                                    {results.projects.length > 0 && (
                                        <div>
                                            <h2 className="text-[24px] font-bold mb-6" style={{ color: '#191919' }}>
                                                Projects ({results.projects.length})
                                            </h2>
                                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                {results.projects.map((project) => (
                                                    <Link
                                                        key={project.id}
                                                        href={`/projects/${project.id}`}
                                                    >
                                                        <div className="group cursor-pointer rounded-xl overflow-hidden bg-background border border-border hover:border-border/60 transition-all">
                                                            {/* Image Container */}
                                                            <div className="aspect-[4/3] bg-muted relative overflow-hidden">
                                                                {project.featured_image ? (
                                                                    <img 
                                                                        src={`/storage/${project.featured_image}`} 
                                                                        alt={project.title || project.name}
                                                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                                    />
                                                                ) : (
                                                                    <div className="size-full flex items-center justify-center">
                                                                        <PlaceholderPattern className="size-full" />
                                                                    </div>
                                                                )}
                                                                
                                                                {/* Favorite Badge */}
                                                                {project.is_favorite && (
                                                                    <div className="absolute top-3 right-3 flex size-8 items-center justify-center rounded-full bg-background/90 backdrop-blur-sm">
                                                                        <Star className="size-4 fill-yellow-500 text-yellow-500" />
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Content */}
                                                            <div className="p-4">
                                                                <h3 className="text-[18px] font-semibold mb-1 truncate" style={{ color: '#191919' }}>
                                                                    {project.title || project.name}
                                                                </h3>
                                                                <div className="flex items-center justify-between text-sm" style={{ color: '#808080' }}>
                                                                    <span>{project.images_count} {project.images_count === 1 ? 'image' : 'images'}</span>
                                                                    <span>{formatDate(project.created_at)}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Images Results */}
                                    {results.images.length > 0 && (
                                        <div>
                                            <h2 className="text-[24px] font-bold mb-6" style={{ color: '#191919' }}>
                                                Images ({results.images.length})
                                            </h2>
                                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                                {results.images.map((image) => (
                                                    <Link
                                                        key={image.id}
                                                        href={`/projects/${image.project_id}`}
                                                        className="group"
                                                    >
                                                        <Card className="overflow-hidden cursor-pointer hover:opacity-90 transition-opacity">
                                                            <div className="aspect-square bg-muted overflow-hidden">
                                                                <img
                                                                    src={`/storage/${image.url}`}
                                                                    alt={image.prompt}
                                                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                                />
                                                            </div>
                                                            <div className="p-3">
                                                                <p className="text-sm text-muted-foreground line-clamp-2">
                                                                    {image.prompt}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground mt-2">
                                                                    {formatDate(image.created_at)}
                                                                </p>
                                                            </div>
                                                        </Card>
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    ) : !hasSearched ? (
                        /* Placeholder */
                        <div className="rounded-lg bg-muted/40 p-12">
                            <div className="text-center">
                                <SearchIcon className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
                                <h2 className="text-2xl font-semibold mb-2">Start Searching</h2>
                                <p className="text-muted-foreground max-w-md mx-auto">
                                    Enter a keyword to search across your projects and images.
                                </p>
                                <div className="flex items-center justify-center gap-6 mt-8">
                                    <div className="text-center">
                                        <FolderOpen className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                        <p className="text-sm text-muted-foreground">Search Projects</p>
                                    </div>
                                    <div className="text-center">
                                        <Image className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                        <p className="text-sm text-muted-foreground">Find Images</p>
                                    </div>
                                    <div className="text-center">
                                        <Clock className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                        <p className="text-sm text-muted-foreground">Quick Access</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
