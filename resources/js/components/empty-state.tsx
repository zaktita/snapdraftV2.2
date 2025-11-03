import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { FileImage, FolderPlus, ImagePlus, Upload } from 'lucide-react';

interface EmptyStateProps {
    icon?: React.ReactNode;
    title: string;
    description: string;
    actionLabel?: string;
    actionHref?: string;
    secondaryActionLabel?: string;
    secondaryActionHref?: string;
}

export function EmptyState({
    icon,
    title,
    description,
    actionLabel,
    actionHref,
    secondaryActionLabel,
    secondaryActionHref,
}: EmptyStateProps) {
    return (
        <Card className="flex flex-col items-center justify-center p-12 text-center">
            {icon && <div className="mb-4 text-muted-foreground">{icon}</div>}
            <h3 className="mb-2 text-lg font-semibold">{title}</h3>
            <p className="mb-6 max-w-sm text-sm text-muted-foreground">{description}</p>
            <div className="flex gap-3">
                {actionHref && actionLabel && (
                    <Button asChild>
                        <Link href={actionHref}>{actionLabel}</Link>
                    </Button>
                )}
                {secondaryActionHref && secondaryActionLabel && (
                    <Button variant="outline" asChild>
                        <Link href={secondaryActionHref}>{secondaryActionLabel}</Link>
                    </Button>
                )}
            </div>
        </Card>
    );
}

// Preset empty states
export function NoProjectsYet() {
    return (
        <EmptyState
            icon={<FolderPlus className="h-12 w-12" />}
            title="No projects yet"
            description="Get started by creating your first project. Choose from CSV batch generation, image-based creation, or text-to-image."
            actionLabel="Create Project"
            actionHref="/projects/create"
        />
    );
}

export function NoImagesInProject() {
    return (
        <EmptyState
            icon={<ImagePlus className="h-12 w-12" />}
            title="No images generated yet"
            description="This project doesn't have any generated images. Start the generation process or add images manually."
            actionLabel="Generate Images"
            actionHref="#"
        />
    );
}

export function UploadFailed({ onRetry }: { onRetry?: () => void }) {
    return (
        <Card className="flex flex-col items-center justify-center p-8 text-center border-destructive bg-destructive/10">
            <Upload className="mb-4 h-12 w-12 text-destructive" />
            <h3 className="mb-2 text-lg font-semibold text-destructive">Upload Failed</h3>
            <p className="mb-6 max-w-sm text-sm text-muted-foreground">
                There was an error uploading your files. Please check your connection and try
                again.
            </p>
            {onRetry && (
                <Button onClick={onRetry} variant="destructive">
                    Retry Upload
                </Button>
            )}
        </Card>
    );
}

export function NoSearchResults({ query }: { query: string }) {
    return (
        <EmptyState
            icon={<FileImage className="h-12 w-12" />}
            title="No results found"
            description={`No projects found matching "${query}". Try a different search term or create a new project.`}
            actionLabel="Clear Search"
            actionHref="/projects"
            secondaryActionLabel="Create Project"
            secondaryActionHref="/projects/create"
        />
    );
}
