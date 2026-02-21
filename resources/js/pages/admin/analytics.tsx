import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DollarSign, Zap, Users, CreditCard } from 'lucide-react';

interface TrendPoint { date: string; count?: number; cost?: number; revenue?: number; }

interface Props {
    generation_trend: TrendPoint[];
    revenue_trend: TrendPoint[];
    signup_trend: TrendPoint[];
    top_users: { name: string; email: string; generations: number; subscription_tier: string }[];
    summary: {
        total_revenue: number; revenue_period: number;
        total_generations: number; period_generations: number;
        new_users_period: number; active_subs: number;
    };
    period: string;
}

const PERIODS = [
    { value: '24hours', label: '24h' }, { value: '7days', label: '7 days' },
    { value: '30days', label: '30 days' }, { value: '90days', label: '90 days' },
];

const TIER_COLORS: Record<string, string> = {
    free: 'bg-gray-100 text-gray-600', launch: 'bg-blue-100 text-blue-700',
    growth: 'bg-orange-100 text-orange-700', scale: 'bg-purple-100 text-purple-700',
};

export default function AnalyticsPage({ generation_trend, revenue_trend, signup_trend, top_users, summary, period }: Props) {
    const setPeriod = (p: string) => router.get('/admin/analytics', { period: p }, { preserveState: true });

    return (
        <AdminLayout title="Analytics">
            <Head title="Analytics" />
            <div className="space-y-6">
                {/* Period selector */}
                <div className="flex gap-2">
                    {PERIODS.map((p) => (
                        <Button key={p.value} size="sm"
                            variant={period === p.value ? 'default' : 'outline'}
                            className={period === p.value ? 'bg-indigo-600 hover:bg-indigo-700' : ''}
                            onClick={() => setPeriod(p.value)}>
                            {p.label}
                        </Button>
                    ))}
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {[
                        { label: 'Revenue (period)', value: `$${Number(summary.revenue_period).toFixed(2)}`, sub: `$${Number(summary.total_revenue).toFixed(2)} total`, Icon: DollarSign, color: 'bg-emerald-500' },
                        { label: 'Generations (period)', value: summary.period_generations, sub: `${summary.total_generations} total`, Icon: Zap, color: 'bg-amber-500' },
                        { label: 'New Users (period)', value: summary.new_users_period, Icon: Users, color: 'bg-indigo-500' },
                        { label: 'Active Subscriptions', value: summary.active_subs, Icon: CreditCard, color: 'bg-violet-500' },
                    ].map((c) => (
                        <div key={c.label} className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500">{c.label}</p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900">{c.value}</p>
                                    {c.sub && <p className="mt-0.5 text-xs text-gray-400">{c.sub}</p>}
                                </div>
                                <div className={`rounded-lg p-2 ${c.color}`}>
                                    <c.Icon className="h-5 w-5 text-white" />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Revenue trend */}
                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700">Revenue Trend</h2>
                        {revenue_trend.length === 0
                            ? <p className="text-sm text-gray-400">No revenue data for this period.</p>
                            : (
                                <div className="space-y-1.5">
                                    {revenue_trend.map((r) => (
                                        <div key={r.date} className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500">{r.date}</span>
                                            <span className="font-semibold text-emerald-700">${Number(r.revenue ?? 0).toFixed(2)}</span>
                                        </div>
                                    ))}
                                </div>
                            )
                        }
                    </div>

                    {/* Signup trend */}
                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700">New User Sign-ups</h2>
                        {signup_trend.length === 0
                            ? <p className="text-sm text-gray-400">No signups in this period.</p>
                            : (
                                <div className="space-y-1.5">
                                    {signup_trend.map((s) => (
                                        <div key={s.date} className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500">{s.date}</span>
                                            <span className="font-semibold text-indigo-700">{s.count}</span>
                                        </div>
                                    ))}
                                </div>
                            )
                        }
                    </div>

                    {/* Generation trend */}
                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700">AI Generations</h2>
                        {generation_trend.length === 0
                            ? <p className="text-sm text-gray-400">No generation data for this period.</p>
                            : (
                                <div className="space-y-1.5">
                                    {generation_trend.map((g) => (
                                        <div key={g.date} className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500">{g.date}</span>
                                            <div className="text-right">
                                                <span className="font-semibold text-amber-700">{g.count}</span>
                                                {g.cost && <span className="ml-2 text-gray-400">${Number(g.cost).toFixed(4)}</span>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )
                        }
                    </div>
                </div>

                {/* Top users */}
                {top_users.length > 0 && (
                    <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700">Top Users by Generations</h2>
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                <tr>
                                    <th className="pb-2 text-left">#</th>
                                    <th className="pb-2 text-left">User</th>
                                    <th className="pb-2 text-left">Plan</th>
                                    <th className="pb-2 text-right">Generations</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {top_users.map((u, i) => (
                                    <tr key={u.email}>
                                        <td className="py-2 text-gray-400 text-xs">{i + 1}</td>
                                        <td className="py-2">
                                            <p className="font-medium text-gray-800">{u.name}</p>
                                            <p className="text-xs text-gray-400">{u.email}</p>
                                        </td>
                                        <td className="py-2">
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${TIER_COLORS[u.subscription_tier] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {u.subscription_tier}
                                            </span>
                                        </td>
                                        <td className="py-2 text-right font-bold text-gray-900">{u.generations}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
