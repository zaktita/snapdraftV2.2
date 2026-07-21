import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Users, FolderOpen, Zap, DollarSign, CreditCard, ShieldCheck, TrendingUp, Activity, AlertTriangle } from 'lucide-react';

interface DashboardProps {
    stats: {
        total_users: number;
        active_users: number;
        suspended_users: number;
        new_users_today: number;
        new_users_this_month: number;
        total_projects: number;
        total_generations: number;
        successful_generations: number;
        failed_generations: number;
        total_cost: number;
        active_subscriptions: number;
        total_revenue: number;
        revenue_this_month: number;
        subscription_breakdown: Record<string, number>;
        failed_jobs?: number;
        pending_jobs?: number;
    };
    recent_users: { id: number; name: string; email: string; created_at: string; subscription_tier: string }[];
    feedback: {
        data: Array<{
            id: number;
            submitted_at: string;
            user_id: number;
            user_name: string;
            user_email: string;
            rating: number;
            category: string;
            message: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    feedback_filters: {
        feedback_date_from?: string;
        feedback_date_to?: string;
        feedback_category?: string;
        feedback_rating_min?: string;
        feedback_rating_max?: string;
        feedback_user_email?: string;
    };
    feedback_categories: string[];
}

function StatCard({ label, value, sub, Icon, color }: {
    label: string; value: string | number; sub?: string;
    Icon: React.ElementType; color: string;
}) {
    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500">{label}</p>
                    <p className="mt-1 text-2xl font-bold text-gray-900">{value}</p>
                    {sub && <p className="mt-0.5 text-xs text-gray-400">{sub}</p>}
                </div>
                <div className={`rounded-lg p-2 ${color}`}>
                    <Icon className="h-5 w-5 text-white" />
                </div>
            </div>
        </div>
    );
}

const TIER_COLORS: Record<string, string> = {
    free: 'bg-gray-100 text-gray-700',
    launch: 'bg-blue-100 text-blue-700',
    growth: 'bg-orange-100 text-orange-700',
    scale: 'bg-purple-100 text-purple-700',
};

export default function AdminDashboard({ stats, recent_users, feedback, feedback_filters, feedback_categories }: DashboardProps) {
    const successRate = stats.total_generations > 0
        ? ((stats.successful_generations / stats.total_generations) * 100).toFixed(1)
        : '0';

    const updateFilter = (key: string, value: string) => {
        router.get('/admin', {
            ...feedback_filters,
            [key]: value,
        }, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        router.get('/admin', {}, { preserveState: true, replace: true });
    };

    const buildDownloadUrl = () => {
        const params = new URLSearchParams();
        Object.entries(feedback_filters || {}).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });
        const qs = params.toString();
        return qs ? `/admin/feedback/download?${qs}` : '/admin/feedback/download';
    };

    return (
        <AdminLayout title="Dashboard">
            <Head title="Admin Dashboard" />
            <div className="space-y-6">
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard label="Total Users" value={stats.total_users}
                        sub={`${stats.active_users} active this month`} Icon={Users} color="bg-indigo-500" />
                    <StatCard label="Active Subscriptions" value={stats.active_subscriptions}
                        sub={`${stats.new_users_this_month} new this month`} Icon={CreditCard} color="bg-emerald-500" />
                    <StatCard label="Total Revenue" value={`$${Number(stats.total_revenue).toFixed(2)}`}
                        sub={`$${Number(stats.revenue_this_month).toFixed(2)} this month`} Icon={DollarSign} color="bg-violet-500" />
                    <StatCard label="Generations" value={stats.total_generations}
                        sub={`${successRate}% success rate`} Icon={Zap} color="bg-amber-500" />
                </div>
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard label="Total Projects" value={stats.total_projects} Icon={FolderOpen} color="bg-sky-500" />
                    <StatCard label="Suspended" value={stats.suspended_users} Icon={ShieldCheck} color="bg-red-500" />
                    <StatCard label="New Today" value={stats.new_users_today} Icon={TrendingUp} color="bg-teal-500" />
                    <StatCard label="AI Cost" value={`$${Number(stats.total_cost).toFixed(4)}`}
                        sub="total generation cost" Icon={Activity} color="bg-pink-500" />
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-2">
                    <Link href="/admin/failed-jobs" className="block">
                        <StatCard
                            label="Failed Jobs"
                            value={stats.failed_jobs ?? 0}
                            sub={`${stats.pending_jobs ?? 0} pending in queue`}
                            Icon={AlertTriangle}
                            color={(stats.failed_jobs ?? 0) > 0 ? 'bg-red-600' : 'bg-zinc-500'}
                        />
                    </Link>
                    <Link href="/admin/audit-log" className="block">
                        <StatCard
                            label="Audit Log"
                            value="View"
                            sub="Admin actions trail"
                            Icon={ShieldCheck}
                            color="bg-zinc-700"
                        />
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700">Subscription Breakdown</h2>
                        <div className="space-y-2.5">
                            {Object.entries(stats.subscription_breakdown).map(([tier, count]) => (
                                <div key={tier} className="flex items-center justify-between">
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${TIER_COLORS[tier] ?? 'bg-gray-100 text-gray-700'}`}>{tier}</span>
                                    <span className="text-sm font-bold text-gray-900">{count}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-700">Recent Sign-ups</h2>
                            <Link href="/admin/users" className="text-xs text-indigo-600 hover:underline">View all</Link>
                        </div>
                        <div className="space-y-2.5">
                            {recent_users.map((u) => (
                                <div key={u.id} className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-800">{u.name}</p>
                                        <p className="text-xs text-gray-400">{u.email}</p>
                                    </div>
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${TIER_COLORS[u.subscription_tier] ?? 'bg-gray-100 text-gray-700'}`}>
                                        {u.subscription_tier ?? 'free'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    {[
                        { href: '/admin/users', label: 'Manage Users' },
                        { href: '/admin/subscriptions', label: 'Subscriptions' },
                        { href: '/admin/credits', label: 'Credits' },
                        { href: '/admin/plans', label: 'Plans' },
                        { href: '/admin/projects', label: 'Projects' },
                        { href: '/admin/failed-jobs', label: 'Failed Jobs' },
                        { href: '/admin/audit-log', label: 'Audit Log' },
                        { href: '/admin#feedback', label: 'Feedback' },
                        { href: '/admin/analytics', label: 'Analytics' },
                    ].map((l) => (
                        <Link key={l.href} href={l.href}
                            className="rounded-lg bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100">
                            {l.label}
                        </Link>
                    ))}
                </div>

                <div id="feedback" className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-700">User Feedback</h2>
                        <a href={buildDownloadUrl()} className="rounded-lg bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100">
                            Download CSV
                        </a>
                    </div>

                    <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <input
                            type="date"
                            value={feedback_filters.feedback_date_from ?? ''}
                            onChange={(e) => updateFilter('feedback_date_from', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        />
                        <input
                            type="date"
                            value={feedback_filters.feedback_date_to ?? ''}
                            onChange={(e) => updateFilter('feedback_date_to', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        />
                        <select
                            value={feedback_filters.feedback_category ?? ''}
                            onChange={(e) => updateFilter('feedback_category', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        >
                            <option value="">All categories</option>
                            {feedback_categories.map((c) => <option key={c} value={c}>{c}</option>)}
                        </select>
                        <input
                            type="number"
                            min={1}
                            max={10}
                            placeholder="Min rating"
                            value={feedback_filters.feedback_rating_min ?? ''}
                            onChange={(e) => updateFilter('feedback_rating_min', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        />
                        <input
                            type="number"
                            min={1}
                            max={10}
                            placeholder="Max rating"
                            value={feedback_filters.feedback_rating_max ?? ''}
                            onChange={(e) => updateFilter('feedback_rating_max', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="User email"
                            value={feedback_filters.feedback_user_email ?? ''}
                            onChange={(e) => updateFilter('feedback_user_email', e.target.value)}
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm"
                        />
                    </div>

                    <div className="mb-4">
                        <button onClick={clearFilters} className="text-xs font-medium text-gray-500 hover:text-gray-700">
                            Clear filters
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                <tr>
                                    <th className="pb-2 text-left">Submitted</th>
                                    <th className="pb-2 text-left">User</th>
                                    <th className="pb-2 text-left">User ID</th>
                                    <th className="pb-2 text-left">Rating</th>
                                    <th className="pb-2 text-left">Category</th>
                                    <th className="pb-2 text-left">Message</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {feedback.data.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-2 text-xs text-gray-500">{item.submitted_at}</td>
                                        <td className="py-2">
                                            <p className="font-medium text-gray-800">{item.user_name}</p>
                                            <p className="text-xs text-gray-400">{item.user_email}</p>
                                        </td>
                                        <td className="py-2 text-gray-700">{item.user_id}</td>
                                        <td className="py-2 font-semibold text-gray-900">{item.rating}</td>
                                        <td className="py-2 text-gray-700">{item.category}</td>
                                        <td className="py-2 max-w-xl text-gray-700">{item.message}</td>
                                    </tr>
                                ))}
                                {feedback.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-sm text-gray-500">No feedback found for current filters.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {feedback.links.map((link, idx) => (
                            <button
                                key={`${link.label}-${idx}`}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                                className={`rounded-md px-3 py-1 text-xs ${link.active ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'} ${!link.url ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
