import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet, Image, Lightbulb } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Projects',
        href: '/projects',
    },
    {
        title: 'New Project',
        href: '/projects/create',
    },
];

const wizardOptions = [
    {
        id: 'csv',
        title: 'CSV Wizard',
        description:
            'Upload a CSV file with content data to generate brand-consistent visuals in batch',
        icon: FileSpreadsheet,
        iconColor: 'text-gray-600 dark:text-gray-500',
        iconBg: 'bg-gray-100 dark:bg-gray-950',
        features: [
            'Batch generate from CSV data',
            'Required columns: title, description, format',
            'Upload 5-10 reference images',
            'Optional product images overlay',
        ],
        route: '/projects/create/csv',
    },
    {
        id: 'images',
        title: 'Images Wizard',
        description:
            'Start with reference images to analyze brand style and generate visuals',
        icon: Image,
        iconColor: 'text-gray-600 dark:text-gray-500',
        iconBg: 'bg-gray-100 dark:bg-gray-950',
        features: [
            'Upload 5-10 brand reference images',
            'AI analyzes colors, typography, composition',
            'Generate individual visuals',
            'Maintain brand consistency',
        ],
        route: '/projects/create/images',
    },
    {
        id: 'text',
        title: 'Text Wizard',
        description:
            'Describe your idea and let AI generate visuals based on your text input',
        icon: Lightbulb,
        iconColor: 'text-gray-600 dark:text-gray-500',
        iconBg: 'bg-gray-100 dark:bg-gray-950',
        features: [
            'Text-based content generation',
            'Describe your visual concept',
            'Optional reference images',
            'Quick and simple workflow',
        ],
        route: '/projects/create/text',
    },
];

export default function ProjectCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Project" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold">
                            Create New Project
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Choose how you want to start generating visuals
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {wizardOptions.map((option) => (
                        <Card
                            key={option.id}
                            className="group cursor-pointer transition-all hover:border-primary hover:shadow-md"
                            onClick={() => router.visit(option.route)}
                        >
                            <CardHeader className="space-y-4">
                                <div
                                    className={`flex size-12 items-center justify-center rounded-lg ${option.iconBg}`}
                                >
                                    <option.icon
                                        className={`size-6 ${option.iconColor}`}
                                    />
                                </div>
                                <div>
                                    <CardTitle className="text-xl">
                                        {option.title}
                                    </CardTitle>
                                    <CardDescription className="mt-2">
                                        {option.description}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2">
                                    {option.features.map((feature, idx) => (
                                        <li
                                            key={idx}
                                            className="flex items-start gap-2 text-sm text-muted-foreground"
                                        >
                                            <span className="mt-0.5 text-primary">
                                                •
                                            </span>
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                                <Button
                                    className="mt-6 w-full"
                                    variant="outline"
                                >
                                    Start with {option.title}
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="rounded-lg border bg-muted/30 p-4">
                    <div className="flex items-start gap-3">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <span className="text-sm font-semibold text-primary">
                                ?
                            </span>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                Not sure which wizard to choose?
                            </p>
                            <p className="text-sm text-muted-foreground">
                                <strong>CSV Wizard</strong> is best for batch
                                generation from structured data.{' '}
                                <strong>Images Wizard</strong> when you have
                                brand references and want to analyze them.{' '}
                                <strong>Text Wizard</strong> for quick,
                                text-based generation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
