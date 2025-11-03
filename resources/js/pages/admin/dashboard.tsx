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

            <div className="min-h-screen bg-gray-50 p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                        <p className="text-gray-600 mt-2">Monitor and manage SnapDraft platform</p>
                    </div>

                    {/* Quick Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Users</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">{stats.total_users}</p>
                                    <p className="text-sm text-gray-500 mt-1">
                                        {stats.active_users} active
                                    </p>
                                </div>
                                <Users className="h-12 w-12 text-blue-500" />
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Projects</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">{stats.total_projects}</p>
                                </div>
                                <FolderOpen className="h-12 w-12 text-green-500" />
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Generations</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">{stats.total_generations}</p>
                                    <p className="text-sm text-gray-500 mt-1">
                                        {successRate}% success rate
                                    </p>
                                </div>
                                <Zap className="h-12 w-12 text-yellow-500" />
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Cost</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">
                                        ${stats.total_cost.toFixed(2)}
                                    </p>
                                </div>
                                <DollarSign className="h-12 w-12 text-emerald-500" />
                            </div>
                        </Card>
                    </div>

                    {/* Subscription Breakdown */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <Card className="p-6">
                            <h3 className="text-lg font-semibold mb-4">Subscription Tiers</h3>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Free</span>
                                    <span className="text-2xl font-bold">{stats.subscription_breakdown.free || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Pro</span>
                                    <span className="text-2xl font-bold text-blue-600">{stats.subscription_breakdown.pro || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Enterprise</span>
                                    <span className="text-2xl font-bold text-purple-600">{stats.subscription_breakdown.enterprise || 0}</span>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-6">
                            <h3 className="text-lg font-semibold mb-4">Generation Stats</h3>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Successful</span>
                                    <span className="text-2xl font-bold text-green-600">{stats.successful_generations}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Failed</span>
                                    <span className="text-2xl font-bold text-red-600">{stats.failed_generations}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-gray-600">Success Rate</span>
                                    <span className="text-2xl font-bold">{successRate}%</span>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    <Card className="p-6">
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
                    </Card>

                    {/* Warnings */}
                    {stats.suspended_users > 0 && (
                        <Card className="p-6 mt-6 border-red-200 bg-red-50">
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5 text-red-600" />
                                <p className="text-red-800 font-medium">
                                    {stats.suspended_users} suspended user{stats.suspended_users > 1 ? 's' : ''} require attention
                                </p>
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
