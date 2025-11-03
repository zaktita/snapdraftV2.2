import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavGroups } from '@/components/nav-groups';
import { NavUser } from '@/components/nav-user';
import { CreditsCard } from '@/components/credits-card';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { plans as subscriptionPlans, portal as subscriptionPortal } from '@/routes/subscription';
import { type NavItem, type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Folder,
    LayoutGrid,
    FolderPlus,
    Search,
    Bell,
    FolderOpen,
    Star,
    Clock,
    PenTool,
    Settings,
    Coins,
    HelpCircle,
    UserPlus,
    LogOut,
    CreditCard,
    Shield,
    Crown,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Home',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Quick Find',
        href: '/search',
        icon: Search,
    },
    {
        title: 'Updates',
        href: '/updates',
        icon: Bell,
    },
];

const projectNavGroups: NavGroup[] = [
    {
        title: 'Projects',
        items: [
            {
                title: 'All Projects',
                href: '/projects',
                icon: FolderOpen,
            },
            {
                title: 'Favorites',
                href: '/projects?filter=favorites',
                icon: Star,
            },
            {
                title: 'Recent',
                href: '/projects?filter=recent',
                icon: Clock,
            },
        ],
    },
    {
        title: 'Tools',
        items: [
            {
                title: 'Canvas Editor',
                href: '/canvas-editor',
                icon: PenTool,
            },
        ],
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user as typeof auth.user & {
        is_admin?: boolean;
        subscription_tier?: string;
    };

    // Build dynamic nav groups based on user
    const dynamicProjectNavGroups: NavGroup[] = [
        {
            title: 'Projects',
            items: [
                {
                    title: 'All Projects',
                    href: '/projects',
                    icon: FolderOpen,
                },
                {
                    title: 'Favorites',
                    href: '/projects?filter=favorites',
                    icon: Star,
                },
                {
                    title: 'Recent',
                    href: '/projects?filter=recent',
                    icon: Clock,
                },
            ],
        },
        {
            title: 'Tools',
            items: [
                {
                    title: 'Canvas Editor',
                    href: '/canvas-editor',
                    icon: PenTool,
                },
            ],
        },
        {
            title: 'Account',
            items: [
                {
                    title: 'Subscription',
                    href: user.subscription_tier === 'free' ? subscriptionPlans().url : subscriptionPortal().url,
                    icon: user.subscription_tier === 'free' ? Crown : CreditCard,
                },
            ],
        },
    ];

    // Add admin section for admins
    if (user.is_admin) {
        dynamicProjectNavGroups.push({
            title: 'Admin',
            items: [
                {
                    title: 'Dashboard',
                    href: '/admin/dashboard',
                    icon: Shield,
                },
                {
                    title: 'Users',
                    href: '/admin/users',
                    icon: UserPlus,
                },
                {
                    title: 'Usage',
                    href: '/admin/usage',
                    icon: LayoutGrid,
                },
            ],
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem className="mt-2">
                        <SidebarMenuButton
                            asChild
                            className="bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90"
                        >
                            <Link href="/projects/create" prefetch>
                                <FolderPlus />
                                <span>New Project</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavGroups groups={dynamicProjectNavGroups} />
            </SidebarContent>

            <SidebarFooter>
                <CreditsCard />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
