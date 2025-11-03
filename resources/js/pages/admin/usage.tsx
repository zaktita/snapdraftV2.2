import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Activity, TrendingUp, DollarSign, Users, Calendar } from 'lucide-react';

interface UsagePageProps {
    date_range: string;
    stats: {
        total_generations: number;
        total_cost: number;
        average_cost: number;
        active_users: number;
    };
    top_users: Array<{
        id: number;
        name: string;
        email: string;
        generations: number;
        cost: number;
    }>;
}

export default function UsagePage({ date_range, stats, top_users }: UsagePageProps) {
    const handleDateRangeChange = (value: string) => {
        router.get('/admin/usage', { date_range: value }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Usage Monitoring" />

            <div className="min-h-screen bg-gray-50 p-8">
                <div className="mx-auto max-w-7xl">
                    {/* Header */}
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Usage Monitoring</h1>
                            <p className="text-gray-600 mt-2">Track platform usage and costs</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Calendar className="h-5 w-5 text-gray-400" />
                            <Select value={date_range} onValueChange={handleDateRangeChange}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="24h">Last 24 Hours</SelectItem>
                                    <SelectItem value="7d">Last 7 Days</SelectItem>
                                    <SelectItem value="30d">Last 30 Days</SelectItem>
                                    <SelectItem value="90d">Last 90 Days</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Total Generations</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">
                                        {stats.total_generations.toLocaleString()}
                                    </p>
                                </div>
                                <Activity className="h-12 w-12 text-blue-500" />
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
                                <DollarSign className="h-12 w-12 text-green-500" />
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Average Cost</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">
                                        ${stats.average_cost.toFixed(3)}
                                    </p>
                                </div>
                                <TrendingUp className="h-12 w-12 text-purple-500" />
                            </div>
                        </Card>

                        <Card className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">Active Users</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">
                                        {stats.active_users}
                                    </p>
                                </div>
                                <Users className="h-12 w-12 text-yellow-500" />
                            </div>
                        </Card>
                    </div>

                    {/* Top Users Table */}
                    <Card>
                        <div className="p-6 border-b">
                            <h2 className="text-xl font-semibold">Top Users by Generation Count</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b bg-gray-50">
                                    <tr>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">User</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Generations</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Total Cost</th>
                                        <th className="text-left p-4 font-medium text-sm text-gray-700">Avg Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_users.length > 0 ? (
                                        top_users.map((user) => (
                                            <tr key={user.id} className="border-b hover:bg-gray-50">
                                                <td className="p-4">
                                                    <div>
                                                        <p className="font-medium text-gray-900">{user.name}</p>
                                                        <p className="text-sm text-gray-500">{user.email}</p>
                                                    </div>
                                                </td>
                                                <td className="p-4 font-medium">
                                                    {user.generations.toLocaleString()}
                                                </td>
                                                <td className="p-4 font-medium text-green-600">
                                                    ${user.cost.toFixed(2)}
                                                </td>
                                                <td className="p-4 text-gray-600">
                                                    ${(user.cost / user.generations).toFixed(3)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="p-12 text-center text-gray-500">
                                                No usage data for selected period
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
