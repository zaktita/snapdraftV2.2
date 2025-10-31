import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Project {
    id: number;
    title: string;
}

interface ProjectEditProps {
    project: Project;
}

export default function ProjectEdit({ project }: ProjectEditProps) {
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
        {
            title: 'Edit',
            href: `/projects/${project.id}/edit`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${project.title}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={`/projects/${project.id}`}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">Edit Project</h1>
                        <p className="text-sm text-muted-foreground">
                            Update project settings and details
                        </p>
                    </div>
                </div>

                <div className="flex flex-1 items-center justify-center rounded-lg border-2 border-dashed">
                    <div className="text-center">
                        <p className="text-lg font-medium text-muted-foreground">
                            Project edit form coming soon
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            This is where project settings will be edited
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
