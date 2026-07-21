import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn, resolveUrl } from '@/lib/utils';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: null,
    },
    {
        title: 'Subscription',
        href: '/settings/subscription',
        icon: null,
    },
    {
        title: 'Invoices',
        href: '/settings/invoices',
        icon: null,
    },
];

function navItemIsActive(currentPath: string, href: NavItem['href']): boolean {
    const target = resolveUrl(href);
    if (currentPath === target) {
        return true;
    }
    // Keep Invoices highlighted on detail pages
    if (target === '/settings/invoices' && currentPath.startsWith('/settings/invoices/')) {
        return true;
    }
    return false;
}

export default function SettingsLayout({
    children,
    wide = false,
}: PropsWithChildren<{ wide?: boolean }>) {
    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6 md:px-8">
            <Heading
                title="Settings"
                description="Manage your profile, subscription, and invoices"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-52 shrink-0">
                    <nav className="flex flex-col space-y-1 space-x-0 rounded-2xl border border-border bg-card p-2">
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${resolveUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start rounded-xl', {
                                    'bg-[var(--sd-or-pale)] text-[var(--sd-or-soft)] hover:bg-[var(--sd-or-pale)] hover:text-[var(--sd-or-soft)]':
                                        navItemIsActive(currentPath, item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className={cn('flex-1', wide ? 'min-w-0 md:max-w-3xl' : 'md:max-w-2xl')}>
                    <section className={cn('space-y-12', wide ? 'max-w-3xl' : 'max-w-xl')}>
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
