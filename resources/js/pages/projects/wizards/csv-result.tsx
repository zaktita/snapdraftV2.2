import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';
import { CheckCircle, ArrowRight, Edit, Download, RefreshCw } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'CSV Wizard', href: '/projects/create/csv' },
    { title: 'Result', href: '#' },
];

interface Image {
    id: number;
    url: string;
    thumbnail_url: string | null;
    prompt: string | null;
    metadata?: {
        cluster_id?: string | number | null;
        selected_images?: number[] | null;
        reference_cluster?: Array<{
            id: number;
            url: string;
            thumbnail_url?: string | null;
            order?: number | null;
            index?: number | null;
        }> | null;
    };
}

interface BrandReference {
    id: number;
    url: string;
    thumbnail_url: string | null;
    order: number | null;
}

interface Project {
    id: number;
    title: string;
    images?: Image[];
    brand_references?: BrandReference[];
    brandReferences?: BrandReference[];
}

interface ResultProps {
    session: {
        id: number;
        status: string;
        project_id: number;
        total_jobs: number | null;
    };
    project: Project;
    summary?: {
        csv_rows?: number | null;
        total: number | null;
        processed: number | null;
        pending: number | null;
        failed: number | null;
        auto_format_count?: number | null;
        validation_failure_count?: number | null;
    };
    failures?: Array<{
        id: number;
        csv_row_index: number | null;
        title: string | null;
        caption: string | null;
        description: string | null;
        format: string | null;
        error_message: string | null;
    }>;
    urls: {
        project: string;
    };
}

export default function CsvWizardResult({ session, project, urls, summary, failures }: ResultProps) {
    const images = project.images ?? [];
    const brandReferences = (project.brandReferences ?? project.brand_references ?? []).slice().sort((a, b) => {
        const ao = a.order ?? 0;
        const bo = b.order ?? 0;
        return ao - bo;
    });
    const csvRows = summary?.csv_rows ?? null;
    const total = summary?.total ?? session.total_jobs;
    const processed = summary?.processed;
    const failed = summary?.failed;
    const autoFormatCount = summary?.auto_format_count ?? null;
    const validationFailureCount = summary?.validation_failure_count ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Result - CSV Wizard" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                <div className="flex items-center gap-3 rounded-lg border bg-green-50 p-4 text-green-900 dark:bg-green-950 dark:text-green-100">
                    <CheckCircle className="h-6 w-6" />
                    <div>
                        <h2 className="font-semibold">Batch Started Successfully</h2>
                        <p className="text-sm opacity-90">
                            Your project is ready. Generated images will appear in the project gallery.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button onClick={() => router.visit(urls.project)}>
                        View Project
                        <ArrowRight className="ml-2 h-4 w-4" />
                    </Button>
                    {images.length > 0 && (
                        <Button variant="outline" onClick={() => {
                            const firstImage = images[0];
                            const encodedUrl = encodeURIComponent(firstImage.url);
                            const encodedTitle = encodeURIComponent(project.title);
                            router.visit(`/canvas-editor?projectId=${project.id}&image=${encodedUrl}&title=${encodedTitle}&imageId=${firstImage.id}`);
                        }}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit in Canvas
                        </Button>
                    )}
                    <Button variant="outline" onClick={() => router.visit('/projects/create/csv')}>
                        Create Another
                    </Button>
                    <div className="text-muted-foreground text-sm">
                        Session #{session.id} • Project #{session.project_id}{' '}
                        {total ? `• ${total} items` : ''}
                    </div>
                </div>

                {(csvRows || total || processed || failed) && (
                    <div className="grid gap-3 md:grid-cols-5">
                        <div className="rounded-lg border bg-card p-4">
                            <div className="text-muted-foreground text-xs">CSV Rows</div>
                            <div className="mt-1 text-2xl font-semibold">{csvRows ?? '—'}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <div className="text-muted-foreground text-xs">Queued Jobs</div>
                            <div className="mt-1 text-2xl font-semibold">{total ?? '—'}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <div className="text-muted-foreground text-xs">Failed</div>
                            <div className="mt-1 text-2xl font-semibold">{failed ?? (failures?.length ?? 0)}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <div className="text-muted-foreground text-xs">Auto Format</div>
                            <div className="mt-1 text-2xl font-semibold">{autoFormatCount ?? '—'}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <div className="text-muted-foreground text-xs">Invalid Rows</div>
                            <div className="mt-1 text-2xl font-semibold">{validationFailureCount ?? '—'}</div>
                        </div>
                    </div>
                )}

                {(autoFormatCount ?? 0) > 0 && (
                    <div className="text-muted-foreground text-sm">
                        Some rows left the format blank — the AI chose the best format.
                    </div>
                )}

                {!!failures?.length && (
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">Failed Items ({failures.length})</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    These rows could not be generated. Fix the data and re-upload.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        const headers = ['title', 'caption', 'description', 'format', 'error'];
                                        const rows = (failures ?? []).map(f => [
                                            f.title ?? '',
                                            f.caption ?? '',
                                            f.description ?? '',
                                            f.format ?? '',
                                            f.error_message ?? '',
                                        ]);
                                        const escapeCsv = (v: string) => `"${String(v).replaceAll('"', '""')}"`;
                                        const csv = [headers.join(','), ...rows.map(r => r.map(escapeCsv).join(','))].join('\n');
                                        const blob = new Blob([csv], { type: 'text/csv' });
                                        const url = URL.createObjectURL(blob);
                                        const a = document.createElement('a');
                                        a.href = url;
                                        a.download = `failed_rows_session_${session.id}.csv`;
                                        document.body.appendChild(a);
                                        a.click();
                                        a.remove();
                                        URL.revokeObjectURL(url);
                                    }}
                                >
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    Export failures CSV
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => router.visit('/projects/create/csv')}
                                >
                                    <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                                    Retry in CSV Wizard
                                </Button>
                            </div>
                        </div>
                        <div className="space-y-3">
                            {failures.map((f) => (
                                <div key={f.id} className="rounded-md border p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="font-medium">
                                            Row {typeof f.csv_row_index === 'number' ? f.csv_row_index + 1 : '—'}
                                            {f.title ? ` • ${f.title}` : ''}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {f.format ? `Format: ${f.format}` : ''}
                                        </div>
                                    </div>
                                    {f.caption && (
                                        <div className="text-muted-foreground mt-2 text-sm">
                                            <span className="font-medium">Caption:</span> {f.caption}
                                        </div>
                                    )}
                                    {f.error_message && (
                                        <div className="mt-2 text-sm text-destructive">
                                            {f.error_message}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {images.length > 0 && (
                    <div className="rounded-lg border bg-card p-6 shadow-sm">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="font-semibold">Latest Images</h3>
                            <Button variant="ghost" size="sm" onClick={() => router.visit(urls.project)}>
                                View all in project <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                            {images.slice(0, 12).map((img) => (
                                <div key={img.id} className="overflow-hidden rounded-md border">
                                    <div className="aspect-square">
                                        <img
                                            src={`/storage/${img.thumbnail_url || img.url}`}
                                            alt={img.prompt || `Image ${img.id}`}
                                            className="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div className="border-t p-2">
                                        <details>
                                            <summary className="text-muted-foreground cursor-pointer text-xs">
                                                Prompt & image cluster
                                            </summary>
                                            {!!img.prompt && (
                                                <div className="mt-2 whitespace-pre-wrap text-xs">
                                                    <div className="text-muted-foreground mb-1 text-[11px]">Prompt sent to model</div>
                                                    {img.prompt}
                                                </div>
                                            )}
                                            <div className="mt-2">
                                                <div className="text-muted-foreground text-[11px]">
                                                    Cluster ID: {String(img.metadata?.cluster_id ?? '—')}
                                                </div>
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {(img.metadata?.reference_cluster && img.metadata.reference_cluster.length > 0
                                                        ? img.metadata.reference_cluster
                                                        : (img.metadata?.selected_images?.length
                                                            ? img.metadata.selected_images
                                                                .map((i) => brandReferences[i])
                                                                .filter(Boolean)
                                                                .map((r, idx) => ({
                                                                    id: r.id,
                                                                    url: r.url,
                                                                    thumbnail_url: r.thumbnail_url,
                                                                    order: r.order,
                                                                    index: idx,
                                                                }))
                                                            : brandReferences.map((r, idx) => ({
                                                                id: r.id,
                                                                url: r.url,
                                                                thumbnail_url: r.thumbnail_url,
                                                                order: r.order,
                                                                index: idx,
                                                            }))))
                                                        .slice(0, 10)
                                                        .map((ref) => (
                                                            <img
                                                                key={ref.id}
                                                                src={`/storage/${ref.thumbnail_url || ref.url}`}
                                                                alt={`Reference ${ref.id}`}
                                                                className="h-10 w-10 rounded border object-cover"
                                                            />
                                                        ))}
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
