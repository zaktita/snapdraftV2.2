import { FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search, Trash2, Image } from 'lucide-react';

interface Project {
    id: number; name: string; title: string; status: string;
    format: string; images_count: number; created_at: string;
    user: { id: number; name: string; email: string } | null;
}

interface Props {
    projects: { data: Project[]; current_page: number; last_page: number; total: number };
    filters: { search?: string; status?: string };
}

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-700', completed: 'bg-blue-100 text-blue-700',
    draft: 'bg-gray-100 text-gray-600', generating: 'bg-amber-100 text-amber-700',
};

export default function ProjectsPage({ projects, filters }: Props) {
    const { data, setData, get } = useForm({ search: filters.search ?? '', status: filters.status ?? 'all' });
    const handleSearch = (e: FormEvent) => { e.preventDefault(); get('/admin/projects', { preserveState: true }); };
    const deleteProject = (id: number) => {
        if (confirm('Delete this project? This is permanent.')) {
            router.delete(`/admin/projects/${id}`);
        }
    };

    return (
        <AdminLayout title="Projects">
            <Head title="Projects" />
            <div className="space-y-4">
                <form onSubmit={handleSearch} className="flex flex-wrap gap-3">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <Input placeholder="Search project or user…" value={data.search}
                            onChange={(e) => setData('search', e.target.value)} className="pl-9 w-64" />
                    </div>
                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                        <SelectTrigger className="w-36"><SelectValue placeholder="All Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline" size="sm">Search</Button>
                </form>

                <p className="text-sm text-gray-500">{projects.total} projects</p>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3 text-left">Project</th>
                                <th className="px-4 py-3 text-left">Owner</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-left">Format</th>
                                <th className="px-4 py-3 text-left">Images</th>
                                <th className="px-4 py-3 text-left">Created</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {projects.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50/60">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{p.name || p.title || 'Untitled'}</p>
                                        {p.title && p.name && p.title !== p.name && <p className="text-xs text-gray-400">{p.title}</p>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-sm text-gray-700">{p.user?.name ?? '-'}</p>
                                        <p className="text-xs text-gray-400">{p.user?.email}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium capitalize ${STATUS_COLORS[p.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {p.status ?? 'unknown'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-500">{p.format ?? '-'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1 text-xs text-gray-600">
                                            <Image className="h-3.5 w-3.5 text-gray-400" /> {p.images_count}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-400">{new Date(p.created_at).toLocaleDateString()}</td>
                                    <td className="px-4 py-3">
                                        <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-red-400 hover:text-red-600 hover:bg-red-50"
                                            onClick={() => deleteProject(p.id)}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {projects.last_page > 1 && (
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-gray-500">Page {projects.current_page} of {projects.last_page}</span>
                        {projects.current_page > 1 && <Button variant="outline" size="sm" onClick={() => router.get('/admin/projects', { ...filters, page: projects.current_page - 1 })}>Prev</Button>}
                        {projects.current_page < projects.last_page && <Button variant="outline" size="sm" onClick={() => router.get('/admin/projects', { ...filters, page: projects.current_page + 1 })}>Next</Button>}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
