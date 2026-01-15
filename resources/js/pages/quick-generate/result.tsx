import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import * as quickGenerate from '@/routes/quick-generate';
import { Download, Edit, RefreshCw, CheckCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Quick Generate', href: quickGenerate.index().url },
    { title: 'Result', href: '#' },
];

interface Session {
    id: number;
    caption: string;
    extracted_title: string;
    extracted_description: string;
    selected_cluster_id: number;
    selected_image_indices: number[];
}

interface Image {
    id: number;
    url: string;
    metadata: {
        title: string;
        description: string;
        cluster_id: number;
        selected_images: number[];
    };
}

interface BrandReference {
    id: number;
    url: string;
    order: number;
}

interface Project {
    id: number;
    name: string;
    format: string;
}

interface ResultProps {
    session: Session;
    project: Project;
    image: Image;
    references: BrandReference[];
}

export default function QuickGenerateResult({ session, project, image, references }: ResultProps) {
    const handleDownload = () => {
        const link = document.createElement('a');
        link.href = `/storage/${image.url}`;
        link.download = `${session.extracted_title.replace(/\s+/g, '_')}.png`;
        link.click();
    };

    const handleEditInCanvas = () => {
        // Navigate to canvas editor with this image
        router.visit(`/canvas-editor?project=${project.id}&image=${image.id}`);
    };

    const handleGenerateAnother = () => {
        router.visit(quickGenerate.index().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Result - Quick Generate" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                {/* Success Header */}
                <div className="flex items-center gap-3 rounded-lg border bg-green-50 p-4 text-green-900 dark:bg-green-950 dark:text-green-100">
                    <CheckCircle className="h-6 w-6" />
                    <div>
                        <h2 className="font-semibold">Visual Generated Successfully!</h2>
                        <p className="text-sm opacity-90">
                            Your brand-consistent visual is ready. You can download, edit, or generate another.
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Generated Visual - 2 columns */}
                    <div className="lg:col-span-2">
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold">Generated Visual</h2>
                            <div className="bg-muted rounded-lg p-4">
                                <img
                                    src={`/storage/${image.url}`}
                                    alt={session.extracted_title}
                                    className="mx-auto max-h-[600px] w-full rounded-md object-contain"
                                />
                            </div>

                            {/* Actions */}
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Button onClick={handleDownload} variant="default">
                                    <Download className="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                                <Button onClick={handleEditInCanvas} variant="outline">
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit in Canvas
                                </Button>
                                <Button onClick={handleGenerateAnother} variant="outline">
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Generate Another
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Details Sidebar - 1 column */}
                    <div className="space-y-6">
                        {/* Extracted Details */}
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h3 className="mb-4 font-semibold">AI-Extracted Details</h3>
                            <div className="space-y-3">
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Title</p>
                                    <p className="mt-1 font-medium">{session.extracted_title}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Description</p>
                                    <p className="mt-1 text-sm">{session.extracted_description}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Original Caption</p>
                                    <p className="mt-1 text-sm italic">{session.caption}</p>
                                </div>
                            </div>
                        </div>

                        {/* Selected References */}
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h3 className="mb-4 font-semibold">Selected References</h3>
                            <p className="text-muted-foreground mb-3 text-sm">
                                AI selected {session.selected_image_indices.length} images from Cluster{' '}
                                {session.selected_cluster_id}
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                {session.selected_image_indices.map((index) => {
                                    const ref = references[index];
                                    return ref ? (
                                        <div key={index} className="aspect-square overflow-hidden rounded-md border">
                                            <img
                                                src={`/${ref.url}`}
                                                alt={`Reference ${index + 1}`}
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                    ) : null;
                                })}
                            </div>
                        </div>

                        {/* Project Info */}
                        <div className="rounded-lg border bg-card p-6 shadow-sm">
                            <h3 className="mb-4 font-semibold">Project Info</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Project Name</span>
                                    <span className="font-medium">{project.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Format</span>
                                    <span className="font-medium">{project.format}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">References Used</span>
                                    <span className="font-medium">{references.length} images</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
