import { FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Search, Plus, Minus } from 'lucide-react';

interface CreditUser {
    id: number; name: string; email: string;
    subscription_tier: string; credits_remaining: number; credits_used: number;
    credits_limit: number; has_subscription: boolean; total_generations: number;
}

interface Props {
    users: { data: CreditUser[]; current_page: number; last_page: number; total: number };
    filters: { search?: string };
}

type Op = 'add' | 'deduct';

export default function CreditsPage({ users, filters }: Props) {
    const [selected, setSelected] = useState<CreditUser | null>(null);
    const [op, setOp] = useState<Op>('add');
    const [amount, setAmount] = useState('');

    const { data, setData, get } = useForm({ search: filters.search ?? '' });
    const handleSearch = (e: FormEvent) => { e.preventDefault(); get('/admin/credits', { preserveState: true }); };

    const openAdjust = (user: CreditUser, operation: Op) => {
        setSelected(user); setOp(operation); setAmount('');
    };
    const closeAdjust = () => setSelected(null);

    const commit = () => {
        if (!selected || !amount) return;
        router.post(`/admin/credits/${selected.id}/adjust`, { amount: Number(amount), operation: op }, { onSuccess: closeAdjust });
    };

    const pct = (u: CreditUser) => u.credits_limit > 0 ? Math.round((u.credits_remaining / u.credits_limit) * 100) : 0;

    return (
        <AdminLayout title="Credits">
            <Head title="Credits Management" />
            <div className="space-y-4">
                <form onSubmit={handleSearch} className="flex gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <Input placeholder="Search user…" value={data.search}
                            onChange={(e) => setData('search', e.target.value)} className="pl-9 w-64" />
                    </div>
                    <Button type="submit" variant="outline" size="sm">Search</Button>
                </form>

                <p className="text-sm text-gray-500">{users.total} users</p>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">User</th>
                                <th className="px-4 py-3 text-left">Plan</th>
                                <th className="px-4 py-3 text-left">Credits</th>
                                <th className="px-4 py-3 text-left">Usage bar</th>
                                <th className="px-4 py-3 text-left">Generations</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {users.data.map((u) => (
                                <tr key={u.id} className="hover:bg-gray-50/60">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{u.name}</p>
                                        <p className="text-xs text-gray-400">{u.email}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs capitalize text-gray-500">{u.subscription_tier ?? 'free'}</td>
                                    <td className="px-4 py-3 text-xs">
                                        <span className="font-semibold text-gray-900">{u.credits_remaining}</span>
                                        <span className="text-gray-400"> / {u.credits_limit}</span>
                                        <div className="text-[10px] text-gray-400">used: {u.credits_used}</div>
                                    </td>
                                    <td className="px-4 py-3 w-32">
                                        <div className="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                                            <div className="h-2 rounded-full bg-indigo-400 transition-all"
                                                style={{ width: `${pct(u)}%` }} />
                                        </div>
                                        <p className="mt-0.5 text-[10px] text-gray-400">{pct(u)}% remaining</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-500">{u.total_generations}</td>
                                    <td className="px-4 py-3">
                                        {u.has_subscription ? (
                                            <div className="flex gap-1.5">
                                                <Button size="sm" variant="outline"
                                                    className="h-7 gap-1 text-xs text-emerald-600 border-emerald-200 hover:bg-emerald-50"
                                                    onClick={() => openAdjust(u, 'add')}>
                                                    <Plus className="h-3 w-3" /> Add
                                                </Button>
                                                <Button size="sm" variant="outline"
                                                    className="h-7 gap-1 text-xs text-red-600 border-red-200 hover:bg-red-50"
                                                    onClick={() => openAdjust(u, 'deduct')}>
                                                    <Minus className="h-3 w-3" /> Deduct
                                                </Button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-gray-400">No subscription</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">Page {users.current_page} of {users.last_page}</span>
                        {users.current_page > 1 && <Button variant="outline" size="sm" onClick={() => router.get('/admin/credits', { ...filters, page: users.current_page - 1 })}>Prev</Button>}
                        {users.current_page < users.last_page && <Button variant="outline" size="sm" onClick={() => router.get('/admin/credits', { ...filters, page: users.current_page + 1 })}>Next</Button>}
                    </div>
                )}
            </div>

            <Dialog open={!!selected} onOpenChange={closeAdjust}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{op === 'add' ? 'Add Credits' : 'Deduct Credits'} - {selected?.name}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <p className="text-sm text-gray-500">
                            Current balance: <strong className="text-gray-900">{selected?.credits_remaining}</strong> / {selected?.credits_limit} credits
                        </p>
                        <div>
                            <label className="text-xs font-medium text-gray-600">Amount</label>
                            <Input type="number" min="1" max="10000" value={amount}
                                onChange={(e) => setAmount(e.target.value)} className="mt-1" placeholder="e.g. 50" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeAdjust}>Cancel</Button>
                        <Button onClick={commit} disabled={!amount || Number(amount) < 1}
                            className={op === 'add' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'}>
                            {op === 'add' ? 'Add Credits' : 'Deduct Credits'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
