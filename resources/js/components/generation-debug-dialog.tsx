import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { AlertCircle, Bug, ChevronDown, Loader2 } from 'lucide-react';

export type GenerationDebugData = {
    row_index?: number;
    available: boolean;
    message?: string;
    pipeline?: string | null;
    cluster?: {
        key: string;
        label: string;
        summary: string | null;
        keywords: string[];
        metadata: Record<string, unknown> | null;
        images_sent_to_model: Array<{
            cluster_image_id: number;
            brand_reference_id: number | null;
            order: number | null;
            url: string | null;
            thumbnail_url: string | null;
            is_anchor: boolean;
        }>;
    } | null;
    master_prompt?: string | null;
    slots_detected?: string[] | null;
    copy?: Record<string, string> | null;
    visual_lock_summary?: string | null;
    prompt_json?: Record<string, unknown> | null;
    compiled_prompt?: string | null;
    image_request_prompt?: string | null;
    image_generation?: Record<string, unknown> | null;
    match?: Record<string, unknown> | null;
    json_valid?: boolean | null;
    history_status?: string | null;
};

function DebugSection({
    title,
    children,
    defaultOpen = false,
}: {
    title: string;
    children: React.ReactNode;
    defaultOpen?: boolean;
}) {
    return (
        <Collapsible defaultOpen={defaultOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left text-sm font-medium hover:bg-muted/50">
                {title}
                <ChevronDown className="h-4 w-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2">{children}</CollapsibleContent>
        </Collapsible>
    );
}

function JsonBlock({ data }: { data?: Record<string, unknown> | null }) {
    if (!data) return <p className="text-muted-foreground text-sm">Not available yet.</p>;
    return (
        <pre className="bg-muted max-h-80 overflow-auto rounded-md p-3 text-xs">
            {JSON.stringify(data, null, 2)}
        </pre>
    );
}

function PreBlock({ content }: { content?: string | null }) {
    if (!content) return <p className="text-muted-foreground text-sm">Not available yet.</p>;
    return (
        <pre className="bg-muted max-h-80 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">
            {content}
        </pre>
    );
}

interface GenerationDebugDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    loading: boolean;
    error: string | null;
    data: GenerationDebugData | null;
}

export function GenerationDebugDialog({
    open,
    onOpenChange,
    title,
    description = 'Prompt and cluster used to generate this image',
    loading,
    error,
    data,
}: GenerationDebugDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] w-[90vw] max-w-[90vw] sm:max-w-[90vw] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Bug className="h-4 w-4" />
                        {title}
                    </DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>

                {loading && (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="text-muted-foreground h-6 w-6 animate-spin" />
                    </div>
                )}

                {error && (
                    <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        {error}
                    </div>
                )}

                {!loading && data && (
                    <div className="space-y-3">
                        {!data.available && (
                            <p className="text-muted-foreground text-sm">
                                {data.message ?? 'Debug data is not available yet.'}
                            </p>
                        )}

                        {data.available && (
                            <>
                                {data.pipeline && (
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">{data.pipeline}</Badge>
                                        {data.history_status && (
                                            <Badge variant="secondary">{data.history_status}</Badge>
                                        )}
                                    </div>
                                )}

                                <DebugSection title="Cluster" defaultOpen>
                                    {data.cluster ? (
                                        <div className="space-y-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Badge variant="outline">{data.cluster.label}</Badge>
                                                <Badge variant="secondary">{data.cluster.key}</Badge>
                                            </div>
                                            {data.cluster.summary && (
                                                <p className="text-muted-foreground text-sm">{data.cluster.summary}</p>
                                            )}
                                            {data.cluster.images_sent_to_model.length > 0 && (
                                                <div>
                                                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                                                        Reference images sent to model
                                                    </p>
                                                    <div className="grid grid-cols-4 gap-2">
                                                        {data.cluster.images_sent_to_model.map((img) => (
                                                            <div key={img.cluster_image_id} className="relative overflow-hidden rounded border">
                                                                {img.thumbnail_url || img.url ? (
                                                                    <img
                                                                        src={img.thumbnail_url ?? img.url ?? ''}
                                                                        alt=""
                                                                        className="aspect-square w-full object-cover"
                                                                    />
                                                                ) : (
                                                                    <div className="bg-muted flex aspect-square items-center justify-center text-xs">
                                                                        No preview
                                                                    </div>
                                                                )}
                                                                {img.is_anchor && (
                                                                    <Badge className="absolute top-1 left-1 text-[10px]" variant="secondary">
                                                                        anchor
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                            {data.cluster.metadata && (
                                                <JsonBlock data={data.cluster.metadata} />
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">No cluster data available.</p>
                                    )}
                                </DebugSection>

                                {data.pipeline === 'master_prompt_lab' ? (
                                    <>
                                        <DebugSection title="Master prompt" defaultOpen>
                                            <PreBlock content={data.master_prompt} />
                                        </DebugSection>
                                        {(data.slots_detected?.length || data.copy || data.visual_lock_summary) && (
                                            <DebugSection title="Detected slots & copy">
                                                <div className="space-y-2">
                                                    {data.slots_detected && data.slots_detected.length > 0 && (
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {data.slots_detected.map((slot) => (
                                                                <Badge key={slot} variant="secondary">
                                                                    {slot}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    )}
                                                    {data.visual_lock_summary && (
                                                        <p className="text-muted-foreground text-sm">
                                                            {data.visual_lock_summary}
                                                        </p>
                                                    )}
                                                    {data.copy && <JsonBlock data={data.copy} />}
                                                </div>
                                            </DebugSection>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <DebugSection title="Prompt JSON" defaultOpen>
                                            <JsonBlock data={data.prompt_json ?? null} />
                                        </DebugSection>

                                        <DebugSection title="Compiled prompt">
                                            <PreBlock content={data.compiled_prompt} />
                                        </DebugSection>

                                        <DebugSection title="Image request prompt">
                                            <PreBlock content={data.image_request_prompt} />
                                        </DebugSection>
                                    </>
                                )}
                            </>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
