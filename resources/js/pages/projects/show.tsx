import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Star, Download, MoreHorizontal, BoxSelect, Square, SquareCheck } from 'lucide-react';

interface Project {
    id: number;
    title: string;
    created_at: string;
    updated_at: string;
    images_count: number;
    is_favorite: boolean;
    description?: string;
    featured_image?: string;
}

interface ProjectShowProps {
    project: Project;
}

export default function ProjectShow({ project }: ProjectShowProps) {
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

    const sampleImages = [
        'https://images.unsplash.com/photo-1541701494587-cb58502866ab?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1497366412874-3415097a27e7?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1541701494587-cb58502866ab?w=400&h=300&fit=crop',
        'https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=300&fit=crop',
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
                            <Button variant="secondary" size="sm" className="gap-2">
                                <Star className="size-4" />
                                View Settings
                            </Button>
                            <Button variant="secondary" size="sm" className="gap-2">
                                <SquareCheck className="size-4" />
                                Select All
                            </Button>
                            <Button 
                                size="sm" 
                                className="gap-2 bg-black text-white hover:bg-gray-800"
                            >
                                <Download className="size-4" />
                                Download ( 0 )
                            </Button>
                        </div>
                    </div>

                    {/* Project Title */}
                    <div className="mb-8">
                        <h1 className="text-2xl font-semibold text-gray-900 mb-1">
                            {project.title}
                        </h1>
                        <p className="text-gray-500">
                            {project.images_count} images
                        </p>
                    </div>

                    {/* Images Grid */}
                    <div className="grid grid-cols-5 gap-4">
                        {sampleImages.map((imageUrl, index) => (
                            <div
                                key={index}
                                className="aspect-square overflow-hidden rounded-lg bg-gray-100 cursor-pointer hover:opacity-90 transition-opacity"
                            >
                                <img
                                    src={imageUrl}
                                    alt={`${project.title} - Image ${index + 1}`}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}