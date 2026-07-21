import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { AlertTriangle, RotateCcw, Trash2 } from 'lucide-react';

interface FailedJob {
    id: number;
    uuid: string;
    queue: string;
    connection: string;
    display_name: string;
    exception_summary: string;
    failed_at: string;
}

interface Props {
    jobs: {
        data: FailedJob[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: { failed: number; pending: number };
}

export default function FailedJobsPage({ jobs, stats }: Props) {
    const retry = (uuid: string) => router.post(`/admin/failed-jobs/${uuid}/retry`);
    const forget = (uuid: string) => {
        if (confirm('Discard this failed job permanently?')) {
            router.delete(`/admin/failed-jobs/${uuid}`);
        }
    };
    const retryAll = () => {
        if (confirm('Retry all failed jobs?')) {
            router.post('/admin/failed-jobs/retry-all');
        }
    };

    return (
        <AdminLayout title="Failed jobs">
            <Head title="Failed Jobs" />
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex gap-4 text-sm">
                        <span className="rounded-lg bg-red-50 px-3 py-1.5 font-medium text-red-700 ring-1 ring-red-100">
                            Failed: {stats.failed}
                        </span>
                        <span className="rounded-lg bg-amber-50 px-3 py-1.5 font-medium text-amber-800 ring-1 ring-amber-100">
                            Pending queue: {stats.pending}
                        </span>
                    </div>
                    {stats.failed > 0 && (
                        <Button size="sm" variant="outline" onClick={retryAll}>
                            <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                            Retry all
                        </Button>
                    )}
                </div>

                {jobs.data.length === 0 ? (
                    <div className="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
                        <AlertTriangle className="mx-auto h-8 w-8 text-gray-300" />
                        <p className="mt-3 text-sm font-medium text-gray-700">No failed jobs</p>
                        <p className="mt-1 text-xs text-gray-400">Queue is healthy.</p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">Job</th>
                                    <th className="px-4 py-3 text-left">Queue</th>
                                    <th className="px-4 py-3 text-left">Error</th>
                                    <th className="px-4 py-3 text-left">Failed</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {jobs.data.map((job) => (
                                    <tr key={job.uuid} className="hover:bg-gray-50/60">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900">{job.display_name}</p>
                                            <p className="font-mono text-[10px] text-gray-400">{job.uuid}</p>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-500">{job.queue}</td>
                                        <td className="max-w-md px-4 py-3 text-xs text-red-700">
                                            {job.exception_summary}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-400">
                                            {new Date(job.failed_at).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 w-7 p-0"
                                                    onClick={() => retry(job.uuid)}
                                                    title="Retry"
                                                >
                                                    <RotateCcw className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-7 w-7 p-0 text-red-400 hover:bg-red-50 hover:text-red-600"
                                                    onClick={() => forget(job.uuid)}
                                                    title="Discard"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {jobs.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">
                            Page {jobs.current_page} of {jobs.last_page}
                        </span>
                        {jobs.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/admin/failed-jobs', { page: jobs.current_page - 1 })
                                }
                            >
                                Prev
                            </Button>
                        )}
                        {jobs.current_page < jobs.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/admin/failed-jobs', { page: jobs.current_page + 1 })
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
