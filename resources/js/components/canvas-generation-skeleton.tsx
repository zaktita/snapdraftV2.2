import { Skeleton } from '@/components/ui/skeleton';

interface CanvasGenerationSkeletonProps {
    left: number;
    top: number;
    width: number;
    height: number;
    label?: string;
}

/** Pulsing placeholder over the canvas where the next generated image will appear. */
export function CanvasGenerationSkeleton({
    left,
    top,
    width,
    height,
    label = 'Generating…',
}: CanvasGenerationSkeletonProps) {
    if (width < 4 || height < 4) return null;

    return (
        <div
            aria-busy="true"
            aria-label={label}
            style={{
                position: 'absolute',
                left,
                top,
                width,
                height,
                pointerEvents: 'none',
                zIndex: 40,
                overflow: 'hidden',
                borderRadius: 4,
                border: '1px dashed color-mix(in oklab, var(--color-border) 80%, var(--color-foreground))',
                boxSizing: 'border-box',
            }}
        >
            <Skeleton className="absolute inset-0 h-full w-full rounded-[3px]" />
            <div
                style={{
                    position: 'absolute',
                    inset: 0,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: 8,
                    textAlign: 'center',
                    fontSize: Math.min(13, Math.max(11, width * 0.04)),
                    fontWeight: 500,
                    color: 'var(--color-muted-foreground)',
                    background:
                        'linear-gradient(180deg, transparent 0%, color-mix(in oklab, var(--color-background) 55%, transparent) 100%)',
                }}
            >
                {label}
            </div>
        </div>
    );
}
