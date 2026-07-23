import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Bell, CheckCircle, Info, Sparkles } from 'lucide-react';

export default function Updates() {
    const updates = [
        {
            id: 1,
            type: 'feature',
            title: 'Admin Panel & Billing System',
            description: 'New admin dashboard with user management and subscription system with Launch, Growth, and Scale tiers.',
            date: 'November 3, 2025',
            icon: Sparkles,
        },
        {
            id: 2,
            type: 'success',
            title: 'Real-time Progress Tracking',
            description: 'Monitor your AI generation progress in real-time with detailed statistics.',
            date: 'November 3, 2025',
            icon: CheckCircle,
        },
        {
            id: 3,
            type: 'info',
            title: 'Credits System',
            description: 'Usage-based pricing with monthly credit allocation. Track your usage and upgrade when needed.',
            date: 'November 3, 2025',
            icon: Info,
        },
    ];

    return (
        <AppLayout>
            <Head title="Updates" />

            <div className="mx-auto max-w-[1600px] space-y-8 p-6 md:p-8">
                <div>
                    <h1 className="font-display text-3xl font-normal tracking-tight">
                        Updates & Notifications
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Stay informed about new features and changes
                    </p>
                </div>

                <div className="space-y-4">
                    {updates.map((update) => {
                        const Icon = update.icon;
                        return (
                            <div
                                key={update.id}
                                className="rounded-2xl border border-border bg-card p-6 transition-colors hover:bg-surface-1"
                            >
                                <div className="flex items-start gap-4">
                                    <div className="rounded-2xl bg-[var(--sd-or-pale)] p-3 text-primary">
                                        <Icon className="h-6 w-6" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 className="text-lg font-semibold">
                                                    {update.title}
                                                </h3>
                                                <p className="mt-1 text-muted-foreground">
                                                    {update.description}
                                                </p>
                                            </div>
                                            <Badge variant="outline" className="flex-shrink-0">
                                                {update.type}
                                            </Badge>
                                        </div>
                                        <p className="mt-3 text-sm text-muted-foreground">
                                            {update.date}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="rounded-2xl border border-border bg-card p-12">
                    <div className="text-center">
                        <Bell className="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                        <h2 className="mb-2 font-display text-2xl font-normal">
                            More Updates Coming Soon
                        </h2>
                        <p className="mx-auto max-w-md text-sm text-muted-foreground">
                            We&apos;re constantly improving SnapDraft. Check back here for the
                            latest features and announcements.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
