import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';

interface LazyImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src: string;
    alt: string;
    fallback?: string;
    className?: string;
    skeletonClassName?: string;
}

/**
 * Lazy loading image component with intersection observer.
 * Only loads images when they enter the viewport.
 */
export function LazyImage({
    src,
    alt,
    fallback = '/images/placeholder.png',
    className,
    skeletonClassName,
    ...props
}: LazyImageProps) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [isInView, setIsInView] = useState(false);
    const [error, setError] = useState(false);
    const imgRef = useRef<HTMLImageElement>(null);

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setIsInView(true);
                        observer.disconnect();
                    }
                });
            },
            {
                rootMargin: '50px', // Start loading 50px before image enters viewport
            }
        );

        if (imgRef.current) {
            observer.observe(imgRef.current);
        }

        return () => {
            observer.disconnect();
        };
    }, []);

    const handleLoad = () => {
        setIsLoaded(true);
    };

    const handleError = () => {
        setError(true);
        setIsLoaded(true);
    };

    return (
        <div className={cn('relative overflow-hidden', className)}>
            {!isLoaded && (
                <Skeleton className={cn('absolute inset-0', skeletonClassName)} />
            )}
            <img
                ref={imgRef}
                src={isInView ? (error ? fallback : src) : ''}
                alt={alt}
                className={cn(
                    'transition-opacity duration-300',
                    isLoaded ? 'opacity-100' : 'opacity-0',
                    className
                )}
                onLoad={handleLoad}
                onError={handleError}
                loading="lazy"
                {...props}
            />
        </div>
    );
}

/**
 * Optimized image grid with lazy loading.
 */
interface LazyImageGridProps {
    images: Array<{ id: number; url: string; alt: string }>;
    columns?: number;
    gap?: number;
    onImageClick?: (image: { id: number; url: string; alt: string }) => void;
}

export function LazyImageGrid({
    images,
    columns = 3,
    gap = 4,
    onImageClick,
}: LazyImageGridProps) {
    return (
        <div
            className="grid"
            style={{
                gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                gap: `${gap * 0.25}rem`,
            }}
        >
            {images.map((image) => (
                <div
                    key={image.id}
                    className="cursor-pointer overflow-hidden rounded-lg transition-transform hover:scale-105"
                    onClick={() => onImageClick?.(image)}
                >
                    <LazyImage
                        src={image.url}
                        alt={image.alt}
                        className="aspect-square w-full object-cover"
                    />
                </div>
            ))}
        </div>
    );
}
