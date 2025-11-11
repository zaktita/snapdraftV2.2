import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Users, FolderOpen, Zap, DollarSign, Activity } from 'lucide-react';

interface AdminDashboardProps {
    stats: {
        total_users: number;
        active_users: number;
        suspended_users: number;
        total_projects: number;
        total_generations: number;
        successful_generations: number;
        failed_generations: number;
        total_cost: number;
        subscription_breakdown: Record<string, number>;
    };
}

export default function AdminDashboard({ stats }: AdminDashboardProps) {
    const successRate = stats.total_generations > 0
        ? ((stats.successful_generations / stats.total_generations) * 100).toFixed(1)
        : '0';

    return (
        <AppLayout>
            <Head title="Admin Dashboard" />

            <div className="min-h-screen bg-background p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-semibold">Admin Dashboard</h1>
                        <p className="text-muted-foreground mt-2">Monitor and manage SnapDraft platform</p>
                    </div>

                    {/* Quick Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Users</p>
                                    <p className="text-3xl font-semibold mt-2">{stats.total_users}</p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {stats.active_users} active
                                    </p>
                                </div>
                                <Users className="h-12 w-12 text-muted-foreground" />
                            </div>
                        </div>

                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Projects</p>
                                    <p className="text-3xl font-semibold mt-2">{stats.total_projects}</p>
                                </div>
                                <FolderOpen className="h-12 w-12 text-muted-foreground" />
                            </div>
                        </div>

                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Generations</p>
                                    <p className="text-3xl font-semibold mt-2">{stats.total_generations}</p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {successRate}% success rate
                                    </p>
                                </div>
                                <Zap className="h-12 w-12 text-muted-foreground" />
                            </div>
                        </div>

                        <div className="rounded-lg bg-muted/40 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Cost</p>
                                    <p className="text-3xl font-semibold mt-2">
                                        ${stats.total_cost.toFixed(2)}
                                    </p>
                                </div>
                                <DollarSign className="h-12 w-12 text-muted-foreground" />
                            </div>
                        </div>
                    </div>

                    {/* Subscription Breakdown */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <div className="rounded-lg bg-muted/40 p-6">
                            <h3 className="text-lg font-semibold mb-4">Subscription Tiers</h3>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Free</span>
                                    <span className="text-2xl font-semibold">{stats.subscription_breakdown.free || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Pro</span>
                                    <span className="text-2xl font-semibold">{stats.subscription_breakdown.pro || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Enterprise</span>
                                    <span className="text-2xl font-semibold">{stats.subscription_breakdown.enterprise || 0}</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-muted/40 p-6">
                            <h3 className="text-lg font-semibold mb-4">Generation Stats</h3>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Successful</span>
                                    <span className="text-2xl font-semibold">{stats.successful_generations}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Failed</span>
                                    <span className="text-2xl font-semibold">{stats.failed_generations}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Success Rate</span>
                                    <span className="text-2xl font-semibold">{successRate}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Quick Actions */}
                    <div className="rounded-lg bg-muted/40 p-6">
                        <h3 className="text-lg font-semibold mb-4">Quick Actions</h3>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Button asChild className="w-full">
                                <Link href="/admin/users">
                                    <Users className="h-4 w-4 mr-2" />
                                    Manage Users
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="w-full">
                                <Link href="/admin/usage">
                                    <Activity className="h-4 w-4 mr-2" />
                                    View Usage
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="w-full">
                                <Link href="/projects">
                                    <FolderOpen className="h-4 w-4 mr-2" />
                                    View Projects
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Warnings */}
                    {stats.suspended_users > 0 && (
                        <div className="rounded-lg bg-destructive/10 p-6 mt-6">
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5 text-destructive" />
                                <p className="text-destructive font-medium">
                                    {stats.suspended_users} suspended user{stats.suspended_users > 1 ? 's' : ''} require attention
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
