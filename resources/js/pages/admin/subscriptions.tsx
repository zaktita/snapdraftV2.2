import { FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Search, XCircle } from 'lucide-react';

interface Subscription {
    id: string; status: string; name: string;
    billing_period: string; price: number; currency: string;
    starts_at: string | null; ends_at: string | null; renews_at: string | null;
    cancelled_at: string | null; cancellation_reason: string | null;
    lemonsqueezy_id: string | null;
    credits_remaining: number; credits_used: number; credits_limit: number;
    user: { id: number; name: string; email: string } | null;
    plan: { id: string; name: string; slug: string } | null;
}

interface Props {
    subscriptions: { data: Subscription[]; current_page: number; last_page: number; total: number };
    filters: { search?: string; status?: string };
}

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    expired: 'bg-gray-100 text-gray-600',
    past_due: 'bg-amber-100 text-amber-700',
    trialing: 'bg-blue-100 text-blue-700',
};

export default function SubscriptionsPage({ subscriptions, filters }: Props) {
    const [selected, setSelected] = useState<Subscription | null>(null);
    const [cancelReason, setCancelReason] = useState('');

    const { data, setData, get } = useForm({
        search: filters.search ?? '', status: filters.status ?? 'all',
    });

    const handleSearch = (e: FormEvent) => { e.preventDefault(); get('/admin/subscriptions', { preserveState: true }); };
    const openCancel = (sub: Subscription) => { setSelected(sub); setCancelReason(''); };
    const closeCancel = () => setSelected(null);

    const commitCancel = () => {
        if (!selected) return;
        router.post(`/admin/subscriptions/${selected.id}/cancel`, { reason: cancelReason }, { onSuccess: closeCancel });
    };

    return (
        <AdminLayout title="Subscriptions">
            <Head title="Subscriptions" />
            <div className="space-y-4">
                <form onSubmit={handleSearch} className="flex flex-wrap gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <Input placeholder="Search user…" value={data.search}
                            onChange={(e) => setData('search', e.target.value)} className="pl-9 w-64" />
                    </div>
                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                        <SelectTrigger className="w-36"><SelectValue placeholder="All Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="past_due">Past Due</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline" size="sm">Search</Button>
                </form>

                <p className="text-sm text-gray-500">{subscriptions.total} subscriptions</p>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">User</th>
                                <th className="px-4 py-3 text-left">Plan</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Credits</th>
                                <th className="px-4 py-3 text-left">Price</th>
                                <th className="px-4 py-3 text-left">Renews</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {subscriptions.data.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50/60">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{s.user?.name ?? '—'}</p>
                                        <p className="text-xs text-gray-400">{s.user?.email}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{s.plan?.name ?? s.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${STATUS_COLORS[s.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        {s.credits_remaining} / {s.credits_limit}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        {s.price ? `$${Number(s.price).toFixed(2)}/${s.billing_period}` : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-400">
                                        {s.renews_at ? new Date(s.renews_at).toLocaleDateString() : s.ends_at ? new Date(s.ends_at).toLocaleDateString() : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {s.status === 'active' && (
                                            <Button size="sm" variant="outline"
                                                className="h-7 text-xs text-red-600 border-red-200 hover:bg-red-50"
                                                onClick={() => openCancel(s)}>
                                                <XCircle className="mr-1 h-3 w-3" /> Cancel
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {subscriptions.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">Page {subscriptions.current_page} of {subscriptions.last_page}</span>
                        {subscriptions.current_page > 1 && <Button variant="outline" size="sm" onClick={() => router.get('/admin/subscriptions', { ...filters, page: subscriptions.current_page - 1 })}>Prev</Button>}
                        {subscriptions.current_page < subscriptions.last_page && <Button variant="outline" size="sm" onClick={() => router.get('/admin/subscriptions', { ...filters, page: subscriptions.current_page + 1 })}>Next</Button>}
                    </div>
                )}
            </div>

            <Dialog open={!!selected} onOpenChange={closeCancel}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel Subscription</DialogTitle>
                        <DialogDescription>Cancel subscription for {selected?.user?.name}? This sets the status to cancelled in our DB. The user's Lemon Squeezy subscription may need to be cancelled separately.</DialogDescription>
                    </DialogHeader>
                    <Textarea placeholder="Reason (optional)…" value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} />
                    <DialogFooter>
                        <Button variant="outline" onClick={closeCancel}>Cancel</Button>
                        <Button variant="destructive" onClick={commitCancel}>Confirm Cancel</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
