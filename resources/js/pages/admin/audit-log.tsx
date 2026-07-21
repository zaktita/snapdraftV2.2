import { FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search } from 'lucide-react';

interface AuditRow {
    id: number;
    action: string;
    admin: { id: number; name: string; email: string } | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
}

interface Props {
    actions: {
        data: AuditRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search?: string; action?: string };
}

export default function AuditLogPage({ actions, filters }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        action: filters.action ?? '',
    });

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        get('/admin/audit-log', { preserveState: true });
    };

    return (
        <AdminLayout title="Audit log">
            <Head title="Admin Audit Log" />
            <div className="space-y-4">
                <form onSubmit={handleSearch} className="flex flex-wrap gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input
                            placeholder="Search action or admin…"
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="w-64 pl-9"
                        />
                    </div>
                    <Input
                        placeholder="Exact action (optional)"
                        value={data.action}
                        onChange={(e) => setData('action', e.target.value)}
                        className="w-56"
                    />
                    <Button type="submit" variant="outline" size="sm">
                        Search
                    </Button>
                </form>

                <p className="text-sm text-gray-500">{actions.total} actions</p>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">When</th>
                                <th className="px-4 py-3 text-left">Admin</th>
                                <th className="px-4 py-3 text-left">Action</th>
                                <th className="px-4 py-3 text-left">Subject</th>
                                <th className="px-4 py-3 text-left">Details</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {actions.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-sm text-gray-400">
                                        No audit entries yet.
                                    </td>
                                </tr>
                            ) : (
                                actions.data.map((row) => (
                                    <tr key={row.id} className="hover:bg-gray-50/60">
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-400">
                                            {row.created_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-800">
                                                {row.admin?.name ?? 'System'}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {row.admin?.email ?? row.ip_address ?? ''}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <code className="rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-800">
                                                {row.action}
                                            </code>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-500">
                                            {row.subject_type
                                                ? `${row.subject_type} #${row.subject_id}`
                                                : '—'}
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3 font-mono text-[11px] text-gray-500">
                                            {row.metadata ? JSON.stringify(row.metadata) : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {actions.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">
                            Page {actions.current_page} of {actions.last_page}
                        </span>
                        {actions.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/admin/audit-log', {
                                        ...filters,
                                        page: actions.current_page - 1,
                                    })
                                }
                            >
                                Prev
                            </Button>
                        )}
                        {actions.current_page < actions.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/admin/audit-log', {
                                        ...filters,
                                        page: actions.current_page + 1,
                                    })
                                }
                            >
                                Next
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
