import { type ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Users,
    CreditCard,
    Layers,
    FolderOpen,
    BarChart3,
    ChevronRight,
    LogOut,
    ShieldAlert,
    X,
} from 'lucide-react';

interface AdminLayoutProps {
    children: ReactNode;
    title?: string;
}

const navItems = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard, exact: true },
    { label: 'Users', href: '/admin/users', icon: Users },
    { label: 'Plans', href: '/admin/plans', icon: Layers },
    { label: 'Subscriptions', href: '/admin/subscriptions', icon: CreditCard },
    { label: 'Credits', href: '/admin/credits', icon: CreditCard },
    { label: 'Projects', href: '/admin/projects', icon: FolderOpen },
    { label: 'Analytics', href: '/admin/analytics', icon: BarChart3 },
];

function isActive(href: string, currentPath: string, exact = false) {
    if (exact) return currentPath === href;
    return currentPath.startsWith(href);
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { url, props } = usePage<{
        auth: { user: { name: string; email: string; is_admin: boolean } };
        impersonating?: { id: number; name: string; email: string };
        flash: { success?: string; error?: string };
    }>();

    const currentPath = url.split('?')[0];
    const user = props.auth?.user;
    const impersonating = props.impersonating;
    const flash = props.flash;

    return (
        <div className="flex h-screen overflow-hidden bg-gray-50">
            {/* Sidebar */}
            <aside className="flex w-60 shrink-0 flex-col bg-zinc-950 text-zinc-100">
                {/* Brand */}
                <div className="flex h-16 items-center gap-2.5 border-b border-zinc-800 px-5">
                    <div className="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-500">
                        <ShieldAlert className="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <p className="text-sm font-semibold leading-none">SnapDraft</p>
                        <p className="mt-0.5 text-[10px] font-medium uppercase tracking-widest text-zinc-500">
                            Super Admin
                        </p>
                    </div>
                </div>

                {/* Navigation */}
                <nav className="flex-1 overflow-y-auto py-4">
                    <ul className="space-y-0.5 px-3">
                        {navItems.map((item) => {
                            const active = isActive(item.href, currentPath, item.exact);
                            return (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className={cn(
                                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100',
                                        )}
                                    >
                                        <item.icon className="h-4 w-4 shrink-0" />
                                        {item.label}
                                        {active && (
                                            <ChevronRight className="ml-auto h-3 w-3 opacity-50" />
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                {/* Footer */}
                <div className="border-t border-zinc-800 p-4">
                    <div className="mb-3 flex items-center gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-700 text-xs font-semibold text-zinc-200">
                            {user?.name?.charAt(0)?.toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-zinc-200">{user?.name}</p>
                            <p className="truncate text-xs text-zinc-500">{user?.email}</p>
                        </div>
                    </div>
                    <Link
                        href="/dashboard"
                        className="flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-xs text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
                    >
                        <LogOut className="h-3.5 w-3.5" />
                        Back to App
                    </Link>
                </div>
            </aside>

            {/* Main */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Impersonation banner */}
                {impersonating && (
                    <div className="flex items-center justify-between bg-amber-500 px-6 py-2 text-sm font-medium text-amber-950">
                        <span>
                            🎭 Impersonating{' '}
                            <strong>{user?.name}</strong> ({user?.email}) &mdash; admin session paused
                        </span>
                        <Link
                            href="/admin/impersonate/stop"
                            method="post"
                            as="button"
                            className="flex items-center gap-1 rounded bg-amber-950/20 px-2 py-0.5 text-xs hover:bg-amber-950/30"
                        >
                            <X className="h-3 w-3" /> Stop Impersonating
                        </Link>
                    </div>
                )}

                {/* Flash messages */}
                {(flash?.success || flash?.error) && (
                    <div
                        className={cn(
                            'mx-6 mt-4 rounded-lg px-4 py-3 text-sm font-medium',
                            flash.success
                                ? 'bg-green-50 text-green-800 ring-1 ring-green-200'
                                : 'bg-red-50 text-red-800 ring-1 ring-red-200',
                        )}
                    >
                        {flash.success || flash.error}
                    </div>
                )}

                {/* Header bar */}
                {title && (
                    <div className="flex h-14 shrink-0 items-center border-b border-gray-200 bg-white px-6">
                        <h1 className="text-base font-semibold text-gray-900">{title}</h1>
                    </div>
                )}

                {/* Scrollable content */}
                <main className="flex-1 overflow-y-auto p-6">{children}</main>
            </div>
        </div>
    );
}
