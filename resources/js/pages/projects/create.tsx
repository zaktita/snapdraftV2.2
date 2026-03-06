import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Projects', href: '/projects' },
    { title: 'New Project', href: '/projects/create' },
];

export default function ProjectCreate() {
    useEffect(() => {
        router.visit('/projects/create/csv', { replace: true });
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Project" />
        </AppLayout>
    );
}
