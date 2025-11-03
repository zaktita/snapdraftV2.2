import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FolderOpen, Star, Image as ImageIcon, AlertCircle, Plus, Crown } from 'lucide-react';
import { H1, Subtext } from '@/components/ds/typography';
import { StatsCard } from '@/components/ds/stats-card';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface Project {
    id: number;
    title: string;
    description: string;
    is_favorite: boolean;
    images_count: number;
    thumbnail: string | null;
    created_at: string;
}

interface Stats {
    total_projects: number;
    favorite_projects: number;
    total_images: number;
    generations_this_month: number;
    successful_generations: number;
    failed_generations: number;
    credits_remaining: number;
    credits_total: number;
    credits_used: number;
    credits_percentage: number;
    subscription_tier: string;
    is_low_credits: boolean;
}

interface DashboardProps {
    stats: Stats;
    recent_projects: Project[];
}

export default function Dashboard({ stats, recent_projects }: DashboardProps) {
    const getTierBadge = (tier: string) => {
        switch (tier) {
            case 'pro':
                return <Badge className="bg-blue-500">Pro</Badge>;
            case 'enterprise':
                return <Badge className="bg-purple-500">Enterprise</Badge>;
            default:
                return <Badge variant="outline">Free</Badge>;
        }
    };

    const successRate = stats.generations_this_month > 0
        ? Math.round((stats.successful_generations / stats.generations_this_month) * 100)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            
            <div className="p-8">
                {/* Welcome Header */}
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <H1>Welcome back!</H1>
                        <Subtext className="mt-1">Here's what's happening with your projects</Subtext>
                    </div>
                    <Button asChild>
                        <Link href="/projects/create">
                            <Plus className="h-4 w-4 mr-2" />
                            New Project
                        </Link>
                    </Button>
                </div>

                {/* Low Credits Warning */}
                {stats.is_low_credits && (
                    <Card className="p-4 bg-yellow-50 border-yellow-200">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <p className="font-medium text-yellow-900">Running low on credits</p>
                                <p className="text-sm text-yellow-700 mt-1">
                                    You have {stats.credits_remaining} credits remaining. Consider upgrading your plan or purchasing additional credits.
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href="/subscription/plans">
                                    <Crown className="h-4 w-4 mr-2" />
                                    Upgrade
                                </Link>
                            </Button>
                        </div>
                    </Card>
                )}

                {/* Stats Grid - Minimal cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <StatsCard label="Total Projects" value={stats.total_projects} subtext={`${stats.favorite_projects} favorites`} />
                    <StatsCard label="Total Images" value={stats.total_images} subtext="Generated visuals" />
                    <StatsCard label="This Month" value={stats.generations_this_month} subtext={`${successRate}% success rate`} />
                    <StatsCard label="Credits" value={stats.credits_total === 999999 ? '∞' : stats.credits_remaining} subtext={`of ${stats.credits_total === 999999 ? 'Unlimited' : stats.credits_total}`} accent />
                </div>

                {/* Two Column Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Recent Projects - Takes 2 columns */}
                    <div className="lg:col-span-2">
                        <Card className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-xl font-semibold">Recent Projects</h2>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href="/projects">View All</Link>
                                </Button>
                            </div>

                            {recent_projects.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {recent_projects.map((project) => (
                                        <Link
                                            key={project.id}
                                            href={`/projects/${project.id}`}
                                            className="group"
                                        >
                                            <Card className="overflow-hidden hover:shadow-lg transition-shadow">
                                                {project.thumbnail ? (
                                                    <div className="aspect-video bg-gray-100 overflow-hidden">
                                                        <img
                                                            src={project.thumbnail}
                                                            alt={project.title}
                                                            className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="aspect-video bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                        <FolderOpen className="h-12 w-12 text-gray-400" />
                                                    </div>
                                                )}
                                                <div className="p-4">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <h3 className="font-semibold text-gray-900 truncate">
                                                            {project.title}
                                                        </h3>
                                                        {project.is_favorite && (
                                                            <Star className="h-4 w-4 text-yellow-500 flex-shrink-0 fill-yellow-500" />
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-500 mt-1 line-clamp-2">
                                                        {project.description}
                                                    </p>
                                                    <div className="flex items-center justify-between mt-3">
                                                        <span className="text-xs text-gray-500">
                                                            {project.images_count} images
                                                        </span>
                                                        <span className="text-xs text-gray-400">
                                                            {project.created_at}
                                                        </span>
                                                    </div>
                                                </div>
                                            </Card>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <FolderOpen className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                                    <p className="text-gray-600 mb-4">No projects yet</p>
                                    <Button asChild>
                                        <Link href="/projects/create">Create Your First Project</Link>
                                    </Button>
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Sidebar - Takes 1 column */}
                    <div className="space-y-6">
                        {/* Subscription + This Month (combined) */}
                        <Card className="p-5 border-none shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="font-semibold">Subscription</h3>
                                {getTierBadge(stats.subscription_tier)}
                            </div>

                            {/* Credits usage */}
                            {stats.credits_total !== 999999 ? (
                                <div>
                                    <p className="text-xs text-gray-500 mb-1">Credits Used</p>
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="text-xs text-gray-500">{stats.credits_used} of {stats.credits_total}</span>
                                        <span className="text-xs text-gray-500">{stats.credits_percentage}%</span>
                                    </div>
                                    <Progress value={stats.credits_percentage} className="h-1 bg-gray-100" indicatorClassName="bg-gray-400" />
                                </div>
                            ) : (
                                <p className="text-xs text-gray-500">Unlimited credits</p>
                            )}

                            <div className="mt-3">
                                <Button asChild className="w-full bg-[#1F2937] hover:bg-[#111827] text-white" size="sm">
                                    <Link href={stats.subscription_tier === 'free' ? '/subscription/plans' : '/subscription/portal'}>
                                        {stats.subscription_tier === 'free' ? (
                                            <>
                                                <Crown className="h-4 w-4 mr-2" />
                                                Upgrade Plan
                                            </>
                                        ) : (
                                            'Manage Billing'
                                        )}
                                    </Link>
                                </Button>
                            </div>

                            {/* This Month small metrics */}
                            <div className="mt-5">
                                <h4 className="text-sm font-medium text-gray-900 mb-2">This Month</h4>
                                <div className="grid grid-cols-3 gap-3">
                                    <div>
                                        <p className="text-[11px] text-gray-500">Successful</p>
                                        <p className="text-base font-semibold text-gray-900">{stats.successful_generations}</p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] text-gray-500">Failed</p>
                                        <p className="text-base font-semibold text-gray-900">{stats.failed_generations}</p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] text-gray-500">Success Rate</p>
                                        <p className="text-base font-semibold text-gray-900">{successRate}%</p>
                                    </div>
                                </div>
                            </div>
                        </Card>

                        {/* Quick Actions */}
                        <Card className="p-6">
                            <h3 className="font-semibold mb-4">Quick Actions</h3>
                            <div className="space-y-2">
                                <Button asChild variant="outline" className="w-full justify-start" size="sm">
                                    <Link href="/projects">
                                        <FolderOpen className="h-4 w-4 mr-2" />
                                        View All Projects
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="w-full justify-start" size="sm">
                                    <Link href="/projects?filter=favorites">
                                        <Star className="h-4 w-4 mr-2" />
                                        Favorites
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="w-full justify-start" size="sm">
                                    <Link href="/canvas-editor">
                                        <ImageIcon className="h-4 w-4 mr-2" />
                                        Canvas Editor
                                    </Link>
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
