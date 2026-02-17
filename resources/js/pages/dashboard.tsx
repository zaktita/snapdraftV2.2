import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    FolderOpen, 
    Star, 
    Image as ImageIcon, 
    AlertCircle, 
    Plus, 
    Crown,
    TrendingUp,
    Zap,
    Clock,
    ArrowRight,
    Sparkles
} from 'lucide-react';
import { cn } from '@/lib/utils';

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
    max_projects?: number;
    remaining_project_slots?: number;
    csv_max_rows?: number;
}

interface DashboardProps {
    stats: Stats;
    recent_projects: Project[];
}

export default function Dashboard({ stats, recent_projects }: DashboardProps) {
    const getTierBadge = (tier: string) => {
        switch (tier) {
            case 'launch':
                return <Badge className="bg-blue-500 hover:bg-blue-600">Launch</Badge>;
            case 'growth':
                return <Badge className="bg-orange-500 hover:bg-orange-600">Growth</Badge>;
            case 'scale':
                return <Badge className="bg-purple-500 hover:bg-purple-600">Scale</Badge>;
            default:
                return <Badge variant="secondary">Free</Badge>;
        }
    };

    const successRate = stats.generations_this_month > 0
        ? Math.round((stats.successful_generations / stats.generations_this_month) * 100)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            
            <div className="p-6 md:p-8 max-w-[1600px] mx-auto">
                {/* Welcome Header - Notion style */}
                <div className="mb-8">
                    <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary/10 to-primary/5">
                                <Sparkles className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">Welcome back!</h1>
                                <p className="text-sm text-muted-foreground mt-0.5">Here's what's happening with your projects</p>
                            </div>
                        </div>
                        <Button asChild size="default" className="gap-2">
                            <Link href="/projects/create">
                                <Plus className="h-4 w-4" />
                                New Project
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Low Credits Warning */}
                {stats.is_low_credits && (
                    <Card className="mb-6 border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/20">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
                                    <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div className="flex-1 space-y-1">
                                    <p className="font-semibold text-amber-900 dark:text-amber-100">Running low on credits</p>
                                    <p className="text-sm text-amber-700 dark:text-amber-300">
                                        You have {stats.credits_remaining} credits remaining. Consider upgrading your plan or purchasing additional credits.
                                    </p>
                                </div>
                                <Button asChild variant="outline" size="sm" className="shrink-0">
                                    <Link href="/subscription/plans">
                                        <Crown className="h-4 w-4 mr-2" />
                                        Upgrade
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Stats Grid - Minimal clean cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    {/* Total Projects */}
                    <div className="rounded-lg bg-muted/40 p-6">
                        <p className="text-sm text-muted-foreground mb-2">Total Projects</p>
                        <p className="text-3xl font-semibold">{stats.total_projects}</p>
                        <p className="text-xs text-muted-foreground mt-2">
                            {stats.favorite_projects} favorites
                        </p>
                    </div>

                    {/* Total Images */}
                    <div className="rounded-lg bg-muted/40 p-6">
                        <p className="text-sm text-muted-foreground mb-2">Total Images</p>
                        <p className="text-3xl font-semibold">{stats.total_images}</p>
                        <p className="text-xs text-muted-foreground mt-2">Generated visuals</p>
                    </div>

                    {/* This Month */}
                    <div className="rounded-lg bg-muted/40 p-6">
                        <p className="text-sm text-muted-foreground mb-2">This Month</p>
                        <p className="text-3xl font-semibold">{stats.generations_this_month}</p>
                        <p className="text-xs text-muted-foreground mt-2">
                            {successRate}% success rate
                        </p>
                    </div>

                    {/* Credits */}
                    <div className="rounded-lg bg-muted/40 p-6">
                        <p className="text-sm text-muted-foreground mb-2">Credits</p>
                        <p className="text-3xl font-semibold">
                            {stats.credits_total === 999999 ? '∞' : stats.credits_remaining}
                        </p>
                        <p className="text-xs text-muted-foreground mt-2">
                            of {stats.credits_total === 999999 ? 'Unlimited' : stats.credits_total}
                        </p>
                    </div>
                </div>

                {/* Two Column Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Recent Projects - Takes 2 columns */}
                    <div className="lg:col-span-2">
                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h2 className="text-lg font-semibold">Recent Projects</h2>
                                    <p className="text-sm text-muted-foreground mt-1">Your latest work and creations</p>
                                </div>
                                <Button asChild variant="ghost" size="sm" className="gap-1">
                                    <Link href="/projects">
                                        View All
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </div>
                            {recent_projects.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {recent_projects.slice(0, 4).map((project) => (
                                        <Link
                                            key={project.id}
                                            href={`/projects/${project.id}`}
                                            className="group block"
                                        >
                                            <div className="rounded-lg overflow-hidden bg-background hover:bg-muted/50 transition-colors">
                                                {project.thumbnail ? (
                                                    <div className="aspect-video overflow-hidden bg-muted">
                                                        <img
                                                            src={`/storage/${project.thumbnail}`}
                                                            alt={project.title}
                                                            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="aspect-video bg-muted/60 flex items-center justify-center">
                                                        <FolderOpen className="h-12 w-12 text-muted-foreground/40" />
                                                    </div>
                                                )}
                                                <div className="p-4">
                                                    <div className="flex items-start justify-between gap-2 mb-2">
                                                        <h3 className="font-medium truncate group-hover:text-foreground/80 transition-colors">
                                                            {project.title}
                                                        </h3>
                                                        {project.is_favorite && (
                                                            <Star className="h-4 w-4 text-foreground/60 fill-foreground/60 shrink-0" />
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground line-clamp-2 mb-3">
                                                        {project.description}
                                                    </p>
                                                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                        <span className="flex items-center gap-1">
                                                            <ImageIcon className="h-3 w-3" />
                                                            {project.images_count}
                                                        </span>
                                                        <span className="flex items-center gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            {project.created_at}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-12 text-center">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted mb-4">
                                            <FolderOpen className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                        <h3 className="font-semibold mb-1">No projects yet</h3>
                                        <p className="text-sm text-muted-foreground mb-4">Get started by creating your first project</p>
                                        <Button asChild>
                                            <Link href="/projects/create">
                                                <Plus className="h-4 w-4 mr-2" />
                                                Create Your First Project
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                        </div>
                    </div>

                    {/* Sidebar - Takes 1 column */}
                    <div className="space-y-6">
                        {/* Subscription Card */}
                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between mb-6">
                                <h2 className="text-base font-semibold">Subscription</h2>
                                {getTierBadge(stats.subscription_tier)}
                            </div>
                            <div className="space-y-4">
                                {/* Credits usage */}
                                {stats.credits_total !== 999999 ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Credits Used</span>
                                            <span className="font-medium">{stats.credits_percentage}%</span>
                                        </div>
                                        <Progress value={stats.credits_percentage} className="h-2" />
                                        <p className="text-xs text-muted-foreground">
                                            {stats.credits_used} of {stats.credits_total} used
                                        </p>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 rounded-lg bg-muted/60 p-3">
                                        <Zap className="h-4 w-4" />
                                        <span className="text-sm font-medium">Unlimited credits</span>
                                    </div>
                                )}

                                <Button asChild className="w-full" size="sm">
                                    <Link href={stats.subscription_tier === 'free' ? '/subscription/plans' : '/subscription'}>
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

                                <Separator />

                                {/* This Month Stats */}
                                <div className="space-y-3">
                                    <h4 className="text-sm font-medium">This Month</h4>
                                    <div className="grid grid-cols-3 gap-3">
                                        <div className="space-y-1">
                                            <p className="text-xs text-muted-foreground">Successful</p>
                                            <p className="text-lg font-semibold">{stats.successful_generations}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs text-muted-foreground">Failed</p>
                                            <p className="text-lg font-semibold">{stats.failed_generations}</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-xs text-muted-foreground">Rate</p>
                                            <p className="text-lg font-semibold">{successRate}%</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="rounded-lg bg-muted/40 p-6">
                            <h2 className="text-base font-semibold mb-4">Quick Actions</h2>
                            <div className="space-y-2">
                                <Button asChild variant="ghost" className="w-full justify-start gap-2" size="sm">
                                    <Link href="/projects">
                                        <FolderOpen className="h-4 w-4" />
                                        View All Projects
                                    </Link>
                                </Button>
                                <Button asChild variant="ghost" className="w-full justify-start gap-2" size="sm">
                                    <Link href="/projects?filter=favorites">
                                        <Star className="h-4 w-4" />
                                        Favorites
                                    </Link>
                                </Button>
                                <Button asChild variant="ghost" className="w-full justify-start gap-2" size="sm">
                                    <Link href="/canvas-editor">
                                        <ImageIcon className="h-4 w-4" />
                                        Canvas Editor
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
