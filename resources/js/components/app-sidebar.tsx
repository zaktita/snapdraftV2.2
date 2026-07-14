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

import { type NavItem, type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Folder,
    LayoutGrid,
    FolderPlus,
    FolderOpen,
    Star,
    Clock,
    PenTool,
    Settings,
    Coins,
    HelpCircle,
    UserPlus,
    LogOut,

    Shield,
    FlaskConical,
    Layers,
    Boxes,
    ImagePlus,
    Combine,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Home',
        href: dashboard(),
        icon: LayoutGrid,
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

const footerNavItems: NavItem[] = [
    {
        title: 'Share Feedback',
        href: '/feedback',
        icon: HelpCircle,
    },
];

export function AppSidebar() {
    const { auth, labsEnabled } = usePage<SharedData & { labsEnabled?: boolean }>().props;
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

    ];

    // Add admin section for admins
    if (user.is_admin) {
        dynamicProjectNavGroups.push({
            title: 'Admin',
            items: [
                {
                    title: 'Dashboard',
                    href: '/admin',
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

        // Test labs — local environment only
        if (labsEnabled) {
            dynamicProjectNavGroups.push({
                title: 'Test Labs',
                items: [
                    {
                        title: 'PromptForge CSV Lab',
                        href: '/test/prompt-forge',
                        icon: FlaskConical,
                    },
                    {
                        title: 'Clustering Lab',
                        href: '/test/clustering',
                        icon: Layers,
                    },
                    {
                        title: 'Cluster Generation',
                        href: '/test/cluster-generation',
                        icon: Boxes,
                    },
                    {
                        title: 'Master Prompt Lab',
                        href: '/test/master-prompt',
                        icon: ImagePlus,
                    },
                    {
                        title: 'Clustered Master Prompt',
                        href: '/test/clustered-master-prompt',
                        icon: Combine,
                    },
                ],
            });
        }
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
                            className="hover:bg-sidebar-primary hover:text-sidebar-primary-foreground bg-neutral-900 text-sidebar-primary-foreground/90 transition-colors duration-150"
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
