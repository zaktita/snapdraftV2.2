import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessages } from '@/components/flash-messages';
import { type BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { X } from 'lucide-react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { props } = usePage<{ impersonating?: unknown; auth: { user: { name: string; email: string } } }>();
    const isImpersonating = !!props.impersonating;
    const user = props.auth?.user;

    return (
        <AppShell variant="sidebar">
            {isImpersonating && (
                <div className="flex items-center justify-between bg-amber-500 px-6 py-2 text-sm font-medium text-amber-950 z-50">
                    <span>🎭 Impersonating <strong>{user?.name}</strong> ({user?.email})</span>
                    <Link href="/admin/impersonate/stop" method="post" as="button"
                        className="flex items-center gap-1 rounded bg-amber-950/20 px-2 py-0.5 text-xs hover:bg-amber-950/30">
                        <X className="h-3 w-3" /> Stop
                    </Link>
                </div>
            )}
            <FlashMessages />
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
