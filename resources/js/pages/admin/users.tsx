import { useState, FormEvent, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Search, Settings2, Ban, CheckCircle, Trash2, Crown, UserCheck, KeyRound,
    SaveIcon, Plus, Minus, ShieldCheck, Zap, CreditCard, User as UserIcon, AlertTriangle, RefreshCw,
} from 'lucide-react';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_suspended: boolean;
    suspension_reason: string | null;
    has_subscription: boolean;
    subscription_id: string | null;
    subscription_status: string;
    subscription_tier: string;
    subscription_plan: string | null;
    credits_remaining: number;
    credits_used: number;
    credits_limit: number;
    total_generations: number;
    created_at: string;
    last_generation_at: string | null;
    email_verified_at: string | null;
}

interface Plan {
    id: string; name: string; slug: string;
    price: number; billing_cycle: string;
    credits_limit: number; max_projects: number;
}

interface Props {
    users: { data: AdminUser[]; current_page: number; last_page: number; total: number };
    filters: { search?: string; status?: string };
    plans: Plan[];
}

const TIER_COLORS: Record<string, string> = {
    free: 'bg-gray-100 text-gray-600',
    launch: 'bg-blue-100 text-blue-700',
    growth: 'bg-orange-100 text-orange-700',
    scale: 'bg-purple-100 text-purple-700',
    starter: 'bg-blue-100 text-blue-700',
    pro: 'bg-orange-100 text-orange-700',
    business: 'bg-purple-100 text-purple-700',
};

function Initials({ name }: { name: string }) {
    const parts = name.trim().split(' ');
    const init = (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
    return (
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
            {init.toUpperCase()}
        </div>
    );
}

export default function UsersPage({ users, filters, plans }: Props) {
    const [drawerId, setDrawerId]     = useState<number | null>(null);
    const [confirmDelete, setConfirmDelete]       = useState(false);
    const [confirmImpersonate, setConfirmImpersonate] = useState(false);
    const [saving, setSaving]         = useState(false);

    // Drawer form fields (initialised when drawer opens)
    const [profileName, setProfileName]     = useState('');
    const [profileEmail, setProfileEmail]   = useState('');
    const [profileAdmin, setProfileAdmin]   = useState(false);
    const [selectedPlan, setSelectedPlan]   = useState('');
    const [creditOp, setCreditOp]           = useState<'add' | 'deduct'>('add');
    const [creditAmt, setCreditAmt]         = useState('');
    const [suspendReason, setSuspendReason] = useState('');
    const [showSuspendInput, setShowSuspendInput] = useState(false);

    // Always derive current drawer data from live Inertia props
    const drawer = drawerId !== null ? (users.data.find(u => u.id === drawerId) ?? null) : null;

    // Re-init form fields whenever the drawer user's data changes in Inertia props
    useEffect(() => {
        if (!drawer) return;
        setProfileName(drawer.name);
        setProfileEmail(drawer.email);
        setProfileAdmin(drawer.is_admin);
        setSelectedPlan(drawer.subscription_tier === 'free' ? '' : drawer.subscription_tier);
    }, [drawer?.id, drawer?.name, drawer?.email, drawer?.is_admin, drawer?.subscription_tier]);

    const { data, setData, get } = useForm({ search: filters.search ?? '', status: filters.status ?? 'all' });

    const openDrawer = (u: AdminUser) => {
        setDrawerId(u.id);
        setCreditAmt('');
        setSuspendReason('');
        setShowSuspendInput(false);
        setConfirmDelete(false);
        setConfirmImpersonate(false);
    };
    const closeDrawer = () => { setDrawerId(null); setConfirmDelete(false); setConfirmImpersonate(false); };

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        get('/admin/users', { preserveState: true });
    };

    // After any mutation reload only the users prop so drawer stays open with fresh data
    const reload = () => router.reload({ only: ['users'] });

    const saveProfile = () => {
        if (!drawer) return;
        setSaving(true);
        router.put(`/admin/users/${drawer.id}`, {
            name: profileName, email: profileEmail, is_admin: profileAdmin,
        }, { onFinish: () => setSaving(false), onSuccess: reload });
    };

    const changePlan = () => {
        if (!drawer || !selectedPlan) return;
        setSaving(true);
        router.put(`/admin/users/${drawer.id}/tier`, { plan: selectedPlan }, {
            onFinish: () => setSaving(false),
            onSuccess: reload,
        });
    };

    const adjustCredits = () => {
        if (!drawer || !creditAmt || Number(creditAmt) < 1) return;
        setSaving(true);
        router.post(`/admin/credits/${drawer.id}/adjust`, { amount: Number(creditAmt), operation: creditOp }, {
            onFinish: () => { setSaving(false); setCreditAmt(''); },
            onSuccess: reload,
        });
    };

    const doSuspend = () => {
        if (!drawer) return;
        router.post(`/admin/users/${drawer.id}/suspend`, { reason: suspendReason || 'Suspended by admin' }, {
            onSuccess: () => { reload(); setShowSuspendInput(false); },
        });
    };

    const doReactivate = () => {
        if (!drawer) return;
        router.post(`/admin/users/${drawer.id}/reactivate`, {}, { onSuccess: reload });
    };

    const doPasswordReset = () => {
        if (!drawer) return;
        router.post(`/admin/users/${drawer.id}/password-reset`);
    };

    const doImpersonate = () => {
        if (!drawer) return;
        router.post(`/admin/users/${drawer.id}/impersonate`);
    };

    const doDelete = () => {
        if (!drawer) return;
        router.delete(`/admin/users/${drawer.id}`, { onSuccess: closeDrawer });
    };

    const creditPct = drawer && drawer.credits_limit > 0
        ? Math.round((drawer.credits_remaining / drawer.credits_limit) * 100) : 0;

    return (
        <AdminLayout title="Users">
            <Head title="User Management" />

            {/* Slide-over backdrop */}
            {drawer && (
                <div className="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm" onClick={closeDrawer} />
            )}

            {/* Slide-over panel */}
            {drawer && (
                <div className="fixed right-0 top-0 z-50 flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-2xl">
                    {/* Header */}
                    <div className="flex items-start justify-between border-b border-gray-100 px-6 py-5">
                        <div className="flex items-center gap-3">
                            <Initials name={drawer.name} />
                            <div>
                                <h2 className="font-semibold text-gray-900">{drawer.name}</h2>
                                <p className="text-xs text-gray-400">{drawer.email}</p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {drawer.is_admin && (
                                        <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">ADMIN</span>
                                    )}
                                    {drawer.is_suspended
                                        ? <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">SUSPENDED</span>
                                        : <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">ACTIVE</span>
                                    }
                                    {!drawer.email_verified_at && (
                                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">UNVERIFIED</span>
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <button onClick={reload} className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Refresh">
                                <RefreshCw className="h-4 w-4" />
                            </button>
                            <button onClick={closeDrawer} className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <div className="flex flex-col gap-6 p-6">

                        {/* ── Profile ───────────────────────────────────── */}
                        <section>
                            <div className="mb-3 flex items-center gap-2">
                                <UserIcon className="h-4 w-4 text-gray-400" />
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">Profile</h3>
                            </div>
                            <div className="space-y-3 rounded-xl bg-gray-50 p-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Name</label>
                                        <Input value={profileName} onChange={(e) => setProfileName(e.target.value)} />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Email</label>
                                        <Input type="email" value={profileEmail} onChange={(e) => setProfileEmail(e.target.value)} />
                                    </div>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-gray-600">Admin access</p>
                                        <p className="text-[11px] text-gray-400">Can access the admin panel</p>
                                    </div>
                                    <button
                                        onClick={() => setProfileAdmin(a => !a)}
                                        className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none ${profileAdmin ? 'bg-indigo-600' : 'bg-gray-200'}`}
                                    >
                                        <span className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform ${profileAdmin ? 'translate-x-4' : 'translate-x-0'}`} />
                                    </button>
                                </div>
                                <Button size="sm" onClick={saveProfile} disabled={saving} className="w-full gap-1.5">
                                    <SaveIcon className="h-3.5 w-3.5" /> Save Profile
                                </Button>
                            </div>
                        </section>

                        {/* ── Subscription ──────────────────────────────── */}
                        <section>
                            <div className="mb-3 flex items-center gap-2">
                                <Crown className="h-4 w-4 text-gray-400" />
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">Subscription</h3>
                            </div>
                            <div className="space-y-3 rounded-xl bg-gray-50 p-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Current plan</span>
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${TIER_COLORS[drawer.subscription_tier] ?? TIER_COLORS.free}`}>
                                        {drawer.subscription_plan ?? drawer.subscription_tier}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">Status</span>
                                    <span className="capitalize text-gray-700">{drawer.subscription_status}</span>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-gray-600">Assign plan</label>
                                    <Select value={selectedPlan} onValueChange={setSelectedPlan}>
                                        <SelectTrigger className="bg-white">
                                            <SelectValue placeholder="Choose a plan…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {plans.map(p => (
                                                <SelectItem key={p.id} value={p.slug}>
                                                    <span className="font-medium">{p.name}</span>
                                                    <span className="ml-2 text-gray-400 text-xs">
                                                        ${Number(p.price).toFixed(2)}/{p.billing_cycle} · {p.credits_limit} credits
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedPlan && (() => {
                                        const p = plans.find(pl => pl.slug === selectedPlan);
                                        return p ? (
                                            <p className="mt-1.5 text-[11px] text-gray-400">
                                                {p.credits_limit} credits · {p.max_projects} projects max
                                            </p>
                                        ) : null;
                                    })()}
                                </div>
                                <Button size="sm" onClick={changePlan} disabled={!selectedPlan || saving} className="w-full gap-1.5">
                                    <Crown className="h-3.5 w-3.5" /> Assign Plan
                                </Button>
                            </div>
                        </section>

                        {/* ── Credits ───────────────────────────────────── */}
                        <section>
                            <div className="mb-3 flex items-center gap-2">
                                <CreditCard className="h-4 w-4 text-gray-400" />
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">Credits</h3>
                            </div>
                            <div className="space-y-3 rounded-xl bg-gray-50 p-4">
                                <div>
                                    <div className="mb-1 flex items-center justify-between text-xs text-gray-500">
                                        <span>Balance: <strong className="text-gray-900">{drawer.credits_remaining}</strong> / {drawer.credits_limit}</span>
                                        <span>{creditPct}% remaining</span>
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                        <div className={`h-2 rounded-full transition-all ${creditPct > 50 ? 'bg-emerald-400' : creditPct > 20 ? 'bg-amber-400' : 'bg-red-400'}`}
                                            style={{ width: `${creditPct}%` }} />
                                    </div>
                                    <p className="mt-1 text-[11px] text-gray-400">Used: {drawer.credits_used}</p>
                                </div>
                                {drawer.has_subscription ? (
                                    <>
                                        <div className="flex gap-2">
                                            <Button size="sm" variant={creditOp === 'add' ? 'default' : 'outline'}
                                                className={`flex-1 gap-1 text-xs ${creditOp === 'add' ? 'bg-emerald-600 hover:bg-emerald-700' : ''}`}
                                                onClick={() => setCreditOp('add')}>
                                                <Plus className="h-3 w-3" /> Add
                                            </Button>
                                            <Button size="sm" variant={creditOp === 'deduct' ? 'default' : 'outline'}
                                                className={`flex-1 gap-1 text-xs ${creditOp === 'deduct' ? 'bg-red-600 hover:bg-red-700' : ''}`}
                                                onClick={() => setCreditOp('deduct')}>
                                                <Minus className="h-3 w-3" /> Deduct
                                            </Button>
                                        </div>
                                        <div className="flex gap-2">
                                            <Input type="number" min="1" max="99999" placeholder="Amount…"
                                                value={creditAmt} onChange={(e) => setCreditAmt(e.target.value)}
                                                onKeyDown={(e) => e.key === 'Enter' && adjustCredits()}
                                                className="bg-white" />
                                            <Button size="sm" onClick={adjustCredits} disabled={!creditAmt || Number(creditAmt) < 1 || saving}
                                                className={`px-4 ${creditOp === 'add' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'}`}>
                                                Apply
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-xs text-gray-400 italic">Assign a plan first to manage credits.</p>
                                )}
                            </div>
                        </section>

                        {/* ── Account Actions ───────────────────────────── */}
                        <section>
                            <div className="mb-3 flex items-center gap-2">
                                <Zap className="h-4 w-4 text-gray-400" />
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500">Account Actions</h3>
                            </div>
                            <div className="space-y-2 rounded-xl bg-gray-50 p-4">
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" className="flex-1 gap-1.5 text-xs"
                                        onClick={() => setConfirmImpersonate(true)} disabled={drawer.is_admin}>
                                        <UserCheck className="h-3.5 w-3.5" /> Impersonate
                                    </Button>
                                    <Button size="sm" variant="outline" className="flex-1 gap-1.5 text-xs"
                                        onClick={doPasswordReset}>
                                        <KeyRound className="h-3.5 w-3.5" /> Reset Password
                                    </Button>
                                </div>
                                <div className="flex gap-2">
                                    {drawer.is_suspended ? (
                                        <Button size="sm" variant="outline" className="flex-1 gap-1.5 text-xs text-emerald-600 border-emerald-200 hover:bg-emerald-50"
                                            onClick={doReactivate}>
                                            <CheckCircle className="h-3.5 w-3.5" /> Reactivate
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="outline" className="flex-1 gap-1.5 text-xs text-amber-600 border-amber-200 hover:bg-amber-50"
                                            onClick={() => setShowSuspendInput(s => !s)} disabled={drawer.is_admin}>
                                            <Ban className="h-3.5 w-3.5" /> Suspend
                                        </Button>
                                    )}
                                    <Button size="sm" variant="outline" className="flex-1 gap-1.5 text-xs text-red-600 border-red-200 hover:bg-red-50"
                                        onClick={() => setConfirmDelete(true)} disabled={drawer.is_admin}>
                                        <Trash2 className="h-3.5 w-3.5" /> Delete
                                    </Button>
                                </div>
                                {showSuspendInput && !drawer.is_suspended && (
                                    <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                        <Textarea placeholder="Reason for suspension (optional)…"
                                            value={suspendReason} onChange={(e) => setSuspendReason(e.target.value)}
                                            rows={2} className="bg-white text-xs" />
                                        <Button size="sm" variant="destructive" className="w-full text-xs" onClick={doSuspend}>
                                            Confirm Suspension
                                        </Button>
                                    </div>
                                )}
                                {drawer.is_suspended && drawer.suspension_reason && (
                                    <p className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-[11px] text-red-600">
                                        Reason: {drawer.suspension_reason}
                                    </p>
                                )}
                            </div>
                        </section>

                        {/* ── Meta ──────────────────────────────────────── */}
                        <div className="grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-xs">
                            <div>
                                <p className="text-gray-400">Joined</p>
                                <p className="font-medium text-gray-700">{new Date(drawer.created_at).toLocaleDateString()}</p>
                            </div>
                            <div>
                                <p className="text-gray-400">Generations</p>
                                <p className="font-medium text-gray-700">{drawer.total_generations}</p>
                            </div>
                            <div>
                                <p className="text-gray-400">Last generation</p>
                                <p className="font-medium text-gray-700">{drawer.last_generation_at ? new Date(drawer.last_generation_at).toLocaleDateString() : 'Never'}</p>
                            </div>
                            <div>
                                <p className="text-gray-400">Email verified</p>
                                <p className={`font-medium ${drawer.email_verified_at ? 'text-emerald-600' : 'text-amber-500'}`}>
                                    {drawer.email_verified_at ? 'Yes' : 'No'}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirm impersonate */}
            <Dialog open={confirmImpersonate} onOpenChange={setConfirmImpersonate}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Impersonate {drawer?.name}?</DialogTitle>
                        <DialogDescription>You will be logged in as this user. Use the banner at the top to stop.</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmImpersonate(false)}>Cancel</Button>
                        <Button onClick={doImpersonate}>Impersonate</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm delete */}
            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-red-500" /> Delete {drawer?.name}?
                        </DialogTitle>
                        <DialogDescription>This is permanent and cannot be undone. All their projects and data will be removed.</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDelete(false)}>Cancel</Button>
                        <Button variant="destructive" onClick={doDelete}>Delete Permanently</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Main content */}
            <div className="space-y-4">
                <form onSubmit={handleSearch} className="flex flex-wrap gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <Input placeholder="Search name or email…" value={data.search}
                            onChange={(e) => setData('search', e.target.value)} className="pl-9 w-64" />
                    </div>
                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                        <SelectTrigger className="w-36"><SelectValue placeholder="All Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="admin">Admins</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>
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
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Joined</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {users.data.map((u) => (
                                <tr key={u.id} className={`hover:bg-gray-50/60 ${drawerId === u.id ? 'bg-indigo-50/60' : ''}`}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{u.name}</p>
                                        <p className="text-xs text-gray-400">{u.email}</p>
                                        {u.is_admin && (
                                            <span className="mt-0.5 inline-flex items-center gap-0.5 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">
                                                <ShieldCheck className="h-2.5 w-2.5" /> ADMIN
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${TIER_COLORS[u.subscription_tier] ?? TIER_COLORS.free}`}>
                                            {u.subscription_plan ?? u.subscription_tier}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        <span className="font-medium">{u.credits_remaining}</span>
                                        <span className="text-gray-400"> / {u.credits_limit}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {u.is_suspended
                                            ? <Badge variant="destructive" className="text-[10px]">Suspended</Badge>
                                            : <Badge variant="outline" className="text-[10px] text-emerald-600 border-emerald-200">Active</Badge>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-400">
                                        {new Date(u.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Button size="sm" variant={drawerId === u.id ? 'default' : 'outline'}
                                            className="h-7 gap-1 text-xs"
                                            onClick={() => drawerId === u.id ? closeDrawer() : openDrawer(u)}>
                                            <Settings2 className="h-3.5 w-3.5" />
                                            {drawerId === u.id ? 'Close' : 'Manage'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">Page {users.current_page} of {users.last_page}</span>
                        {users.current_page > 1 && (
                            <Button variant="outline" size="sm"
                                onClick={() => router.get('/admin/users', { ...filters, page: users.current_page - 1 })}>Prev</Button>
                        )}
                        {users.current_page < users.last_page && (
                            <Button variant="outline" size="sm"
                                onClick={() => router.get('/admin/users', { ...filters, page: users.current_page + 1 })}>Next</Button>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
