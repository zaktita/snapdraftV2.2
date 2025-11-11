import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Bell, CheckCircle, Info, AlertTriangle, Sparkles } from 'lucide-react';

export default function Updates() {
    const updates = [
        {
            id: 1,
            type: 'feature',
            title: 'Admin Panel & Billing System',
            description: 'New admin dashboard with user management and subscription system with Free, Pro, and Enterprise tiers.',
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
            
            <div className="p-8 space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-semibold">Updates & Notifications</h1>
                    <p className="text-muted-foreground mt-1">Stay informed about new features and changes</p>
                </div>

                {/* Updates List */}
                <div className="space-y-4">
                    {updates.map((update) => {
                        const Icon = update.icon;
                        return (
                            <div key={update.id} className="rounded-lg bg-muted/40 p-6 hover:bg-muted/60 transition-colors">
                                <div className="flex items-start gap-4">
                                    <div className="p-3 rounded-lg bg-muted/60">
                                        <Icon className="h-6 w-6" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 className="text-lg font-semibold">{update.title}</h3>
                                                <p className="text-muted-foreground mt-1">{update.description}</p>
                                            </div>
                                            <Badge variant="outline" className="flex-shrink-0">
                                                {update.type}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground mt-3">{update.date}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Coming Soon */}
                <div className="rounded-lg bg-muted/40 p-12">
                    <div className="text-center">
                        <Bell className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
                        <h2 className="text-2xl font-semibold mb-2">More Updates Coming Soon</h2>
                        <p className="text-muted-foreground max-w-md mx-auto">
                            We're constantly improving SnapDraft. Check back here for the latest features and announcements.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
