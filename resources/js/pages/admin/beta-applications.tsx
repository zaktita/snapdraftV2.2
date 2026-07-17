import AdminLayout from '@/layouts/admin-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useState } from 'react';
import { CheckCircle, XCircle, Eye } from 'lucide-react';

interface Application {
    id: string;
    email: string;
    role: string;
    monthly_post_volume: string;
    visual_workflow: string;
    status: string;
    invite_code: string | null;
    created_at: string;
}

interface Props {
    applications: Application[];
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-zinc-200 text-zinc-700',
};

function formatRole(role: string) {
    return role.replace(/_/g, ' ');
}

export default function BetaApplicationsPage({ applications }: Props) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [detail, setDetail] = useState<Application | null>(null);

    return (
        <AdminLayout title="Beta applications">
            <Head title="Beta applications" />
            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold text-gray-900">Beta applications</h1>
                    <p className="text-sm text-gray-500">
                        Pending requests appear first. Approve to create an invite code and email the applicant.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                        {flash.error}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">Email</th>
                                <th className="px-4 py-3 text-left">Role</th>
                                <th className="px-4 py-3 text-left">Volume / mo</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Code</th>
                                <th className="px-4 py-3 text-left">Submitted</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {applications.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-gray-500">
                                        No applications yet.
                                    </td>
                                </tr>
                            ) : (
                                applications.map((row) => (
                                    <tr key={row.id} className="hover:bg-gray-50/60">
                                        <td className="px-4 py-3 font-medium text-gray-900">{row.email}</td>
                                        <td className="px-4 py-3 text-gray-600 capitalize">{formatRole(row.role)}</td>
                                        <td className="px-4 py-3 text-gray-600">{row.monthly_post_volume}</td>
                                        <td className="px-4 py-3">
                                            <Badge className={statusStyles[row.status] ?? 'bg-gray-100'}>
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-gray-700">
                                            {row.invite_code ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-500">
                                            {new Date(row.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1.5">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-8 gap-1 text-xs"
                                                    onClick={() => setDetail(row)}
                                                >
                                                    <Eye className="h-3.5 w-3.5" />
                                                    View
                                                </Button>
                                                {row.status === 'pending' && (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 gap-1 border-emerald-200 text-xs text-emerald-700 hover:bg-emerald-50"
                                                            onClick={() => {
                                                                if (
                                                                    confirm(
                                                                        `Approve ${row.email} and send invite email?`,
                                                                    )
                                                                ) {
                                                                    router.post(
                                                                        `/admin/beta-applications/${row.id}/approve`,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <CheckCircle className="h-3.5 w-3.5" />
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 gap-1 border-red-200 text-xs text-red-700 hover:bg-red-50"
                                                            onClick={() => {
                                                                if (
                                                                    confirm(
                                                                        `Reject application from ${row.email}?`,
                                                                    )
                                                                ) {
                                                                    router.post(
                                                                        `/admin/beta-applications/${row.id}/reject`,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
                <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Application detail</DialogTitle>
                    </DialogHeader>
                    {detail && (
                        <div className="space-y-3 text-sm">
                            <p>
                                <span className="font-medium text-gray-700">Email:</span>{' '}
                                {detail.email}
                            </p>
                            <p>
                                <span className="font-medium text-gray-700">Role:</span>{' '}
                                <span className="capitalize">{formatRole(detail.role)}</span>
                            </p>
                            <p>
                                <span className="font-medium text-gray-700">Monthly volume:</span>{' '}
                                {detail.monthly_post_volume}
                            </p>
                            <div>
                                <p className="font-medium text-gray-700">Visual workflow</p>
                                <p className="mt-1 whitespace-pre-wrap rounded-md bg-gray-50 p-3 text-gray-800">
                                    {detail.visual_workflow}
                                </p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
