import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

type AppPageProps = {
    children: ReactNode;
    className?: string;
    /** Wider content (default 1600px) */
    wide?: boolean;
};

/**
 * Standard logged-in page shell — canvas shows through; content is padded.
 */
export function AppPage({ children, className, wide = true }: AppPageProps) {
    return (
        <div
            className={cn(
                'mx-auto w-full space-y-8 p-6 md:p-8',
                wide ? 'max-w-[1600px]' : 'max-w-5xl',
                className,
            )}
        >
            {children}
        </div>
    );
}

type AppPageHeaderProps = {
    title: string;
    description?: string;
    icon?: ReactNode;
    actions?: ReactNode;
    className?: string;
};

/**
 * Shared page header: display title + muted subtext + optional actions.
 */
export function AppPageHeader({
    title,
    description,
    icon,
    actions,
    className,
}: AppPageHeaderProps) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex items-start gap-3 min-w-0">
                {icon ? (
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--sd-or-pale)] text-primary">
                        {icon}
                    </div>
                ) : null}
                <div className="min-w-0 space-y-1">
                    <h1 className="font-display text-3xl font-normal tracking-tight text-foreground">
                        {title}
                    </h1>
                    {description ? (
                        <p className="text-sm text-muted-foreground max-w-2xl">
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-2 shrink-0">
                    {actions}
                </div>
            ) : null}
        </header>
    );
}
