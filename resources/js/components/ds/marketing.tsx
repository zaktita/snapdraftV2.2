import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    type ComponentType,
    type PropsWithChildren,
    type MouseEvent as ReactMouseEvent,
    useCallback,
    useRef,
} from 'react';

interface SectionProps extends PropsWithChildren {
    id?: string;
    className?: string;
    contentClassName?: string;
}

export function Section({
    id,
    className,
    contentClassName,
    children,
}: SectionProps) {
    return (
        <section id={id} className={cn('py-20 lg:py-24', className)}>
            <div className={cn('ds-section-wrap', contentClassName)}>
                {children}
            </div>
        </section>
    );
}

interface SectionHeaderProps extends PropsWithChildren {
    title: string;
    description?: string;
    centered?: boolean;
    className?: string;
}

export function SectionHeader({
    title,
    description,
    centered = true,
    className,
    children,
}: SectionHeaderProps) {
    return (
        <div className={cn('mb-12', centered && 'text-center', className)}>
            <h2 className="ds-heading-2">{title}</h2>
            {description && (
                <p
                    className={cn(
                        'ds-body mt-3',
                        centered && 'mx-auto max-w-2xl',
                    )}
                >
                    {description}
                </p>
            )}
            {children}
        </div>
    );
}

interface WireframePlaceholderProps {
    className?: string;
    aspect?: 'video' | 'square' | 'wide';
    rows?: number;
    bars?: number;
    label?: string;
}

export function WireframePlaceholder({
    className,
    aspect = 'video',
    rows = 2,
    bars = 5,
    label,
}: WireframePlaceholderProps) {
    const aspectClass =
        aspect === 'square'
            ? 'aspect-square'
            : aspect === 'wide'
              ? 'aspect-[16/8]'
              : 'aspect-video';

    return (
        <div className={cn('ds-wireframe overflow-hidden', className)}>
            <div
                className={cn(
                    'border-b border-divider-subtle bg-surface-2/80 p-3',
                    aspectClass,
                )}
            >
                <div className="grid h-full grid-cols-6 gap-3">
                    {Array.from({ length: bars }).map((_, index) => (
                        <div
                            key={index}
                            className="rounded-lg border border-divider-subtle bg-surface-0"
                        />
                    ))}
                </div>
            </div>
            <div className="space-y-3 p-4">
                {label && (
                    <p className="ds-label tracking-wide uppercase">{label}</p>
                )}
                {Array.from({ length: rows }).map((_, index) => (
                    <div
                        key={index}
                        className="ds-wireframe-line"
                        style={{ width: `${92 - index * 14}%` }}
                    />
                ))}
            </div>
        </div>
    );
}

interface FeatureCardProps {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
    className?: string;
}

export function FeatureCard({
    icon: Icon,
    title,
    description,
    className,
}: FeatureCardProps) {
    return (
        <Card
            className={cn(
                'ds-card-minimal gap-4 border-divider-strong pb-5',
                className,
            )}
        >
            <CardHeader className="px-5 pt-5">
                <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-brand/15">
                    <Icon className="h-5 w-5 text-brand" />
                </div>
                <CardTitle className="text-lg">{title}</CardTitle>
                <CardDescription className="text-sm leading-relaxed">
                    {description}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

interface StepCardProps {
    number: string;
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
    className?: string;
}

export function StepCard({
    number,
    icon: Icon,
    title,
    description,
    className,
}: StepCardProps) {
    return (
        <Card
            className={cn(
                'ds-card-minimal gap-3 border-divider-strong pb-5',
                className,
            )}
        >
            <CardHeader className="px-5 pt-5">
                <div className="mb-1 flex items-center gap-3">
                    <span className="text-3xl font-semibold tracking-tight text-brand/35">
                        {number}
                    </span>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                        <Icon className="h-4.5 w-4.5" />
                    </div>
                </div>
                <CardTitle className="text-lg">{title}</CardTitle>
                <CardDescription className="text-sm leading-relaxed">
                    {description}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

export function PlanBadge({ children }: PropsWithChildren) {
    return (
        <Badge className="bg-brand text-brand-foreground hover:bg-brand/90">
            {children}
        </Badge>
    );
}

interface StatPillProps {
    value: string;
    label: string;
}

export function StatPill({ value, label }: StatPillProps) {
    return (
        <div className="rounded-lg border border-divider-subtle bg-surface-1 p-4 text-center">
            <p className="text-2xl font-semibold text-brand">{value}</p>
            <p className="ds-caption mt-1">{label}</p>
        </div>
    );
}

export function MarketingFrame({
    children,
    className,
}: PropsWithChildren<{ className?: string }>) {
    return (
        <div
            className={cn(
                'rounded-xl border border-divider-subtle bg-surface-1 p-6 shadow-sm',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function FeatureChecklist({ items }: { items: string[] }) {
    return (
        <ul className="space-y-3">
            {items.map((item) => (
                <li
                    key={item}
                    className="flex items-start gap-3 text-sm text-foreground/90"
                >
                    <span className="mt-0.5 h-2 w-2 rounded-full bg-brand" />
                    {item}
                </li>
            ))}
        </ul>
    );
}

export function DividerBand({ children }: PropsWithChildren) {
    return (
        <div className="border-y border-divider-subtle bg-surface-1/75 py-8">
            {children}
        </div>
    );
}

/* ── Homepage-specific components ── */

export function GradientOrb() {
    return (
        <div
            className="pointer-events-none absolute inset-0 overflow-hidden"
            aria-hidden="true"
        >
            {/* Primary orb */}
            <div className="hero-orb absolute -top-32 -right-32 h-[500px] w-[500px] rounded-full bg-brand/20 blur-[120px] lg:-right-16 lg:h-[600px] lg:w-[600px]" />
            {/* Secondary orb */}
            <div className="hero-orb-2 absolute -bottom-24 -left-24 h-[350px] w-[350px] rounded-full bg-brand/10 blur-[100px] lg:h-[400px] lg:w-[400px]" />
            {/* Dot pattern overlay */}
            <div className="dot-pattern absolute inset-0 opacity-40 dark:opacity-20" />
        </div>
    );
}

interface BentoCardProps {
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
    size?: 'lg' | 'sm';
    className?: string;
}

export function BentoCard({
    icon: Icon,
    title,
    description,
    size = 'sm',
    className,
}: BentoCardProps) {
    const cardRef = useRef<HTMLDivElement>(null);

    const handleMouseMove = useCallback(
        (e: ReactMouseEvent<HTMLDivElement>) => {
            const rect = cardRef.current?.getBoundingClientRect();
            if (!rect) return;
            cardRef.current!.style.setProperty(
                '--mouse-x',
                `${e.clientX - rect.left}px`,
            );
            cardRef.current!.style.setProperty(
                '--mouse-y',
                `${e.clientY - rect.top}px`,
            );
        },
        [],
    );

    return (
        <div
            ref={cardRef}
            onMouseMove={handleMouseMove}
            className={cn(
                'bento-glow group relative overflow-hidden rounded-2xl border border-divider-subtle bg-surface-0 p-6 transition-all duration-300 hover:border-brand/30 hover:shadow-md',
                size === 'lg' && 'p-8',
                className,
            )}
        >
            <div
                className={cn(
                    'mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 transition-colors group-hover:bg-brand/15',
                    size === 'lg' && 'h-12 w-12',
                )}
            >
                <Icon
                    className={cn(
                        'h-5 w-5 text-brand',
                        size === 'lg' && 'h-6 w-6',
                    )}
                />
            </div>
            <h3
                className={cn(
                    'font-semibold text-foreground',
                    size === 'lg' ? 'text-xl' : 'text-lg',
                )}
            >
                {title}
            </h3>
            <p
                className={cn(
                    'mt-2 leading-relaxed text-muted-foreground',
                    size === 'lg' ? 'text-base' : 'text-sm',
                )}
            >
                {description}
            </p>
        </div>
    );
}

interface StepTimelineItemProps {
    number: string;
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
}

export function StepTimeline({ steps }: { steps: StepTimelineItemProps[] }) {
    return (
        <div className="relative grid gap-8 md:grid-cols-3 md:gap-0">
            {/* Connecting line (desktop only) */}
            <div className="absolute top-7 right-[16.66%] left-[16.66%] hidden h-px bg-divider-strong md:block" />

            {steps.map((step, i) => (
                <div
                    key={i}
                    className="relative flex flex-col items-center text-center"
                >
                    {/* Step number circle */}
                    <div className="relative z-10 flex h-14 w-14 items-center justify-center rounded-full border-2 border-brand/25 bg-surface-0">
                        <step.icon className="h-6 w-6 text-brand" />
                    </div>
                    <span className="mt-4 text-sm font-semibold text-brand">
                        {step.number}
                    </span>
                    <h3 className="mt-2 text-lg font-semibold text-foreground">
                        {step.title}
                    </h3>
                    <p className="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground">
                        {step.description}
                    </p>
                </div>
            ))}
        </div>
    );
}
