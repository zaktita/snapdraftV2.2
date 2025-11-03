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
            color: 'text-purple-500',
            bg: 'bg-purple-50',
        },
        {
            id: 2,
            type: 'success',
            title: 'Real-time Progress Tracking',
            description: 'Monitor your AI generation progress in real-time with detailed statistics.',
            date: 'November 3, 2025',
            icon: CheckCircle,
            color: 'text-green-500',
            bg: 'bg-green-50',
        },
        {
            id: 3,
            type: 'info',
            title: 'Credits System',
            description: 'Usage-based pricing with monthly credit allocation. Track your usage and upgrade when needed.',
            date: 'November 3, 2025',
            icon: Info,
            color: 'text-blue-500',
            bg: 'bg-blue-50',
        },
    ];

    return (
        <AppLayout>
            <Head title="Updates" />
            
            <div className="p-8 space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Updates & Notifications</h1>
                    <p className="text-gray-600 mt-1">Stay informed about new features and changes</p>
                </div>

                {/* Updates List */}
                <div className="space-y-4">
                    {updates.map((update) => {
                        const Icon = update.icon;
                        return (
                            <Card key={update.id} className="p-6 hover:shadow-lg transition-shadow">
                                <div className="flex items-start gap-4">
                                    <div className={`p-3 rounded-lg ${update.bg}`}>
                                        <Icon className={`h-6 w-6 ${update.color}`} />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 className="text-lg font-semibold text-gray-900">{update.title}</h3>
                                                <p className="text-gray-600 mt-1">{update.description}</p>
                                            </div>
                                            <Badge variant="outline" className="flex-shrink-0">
                                                {update.type}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-gray-500 mt-3">{update.date}</p>
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                {/* Coming Soon */}
                <Card className="p-12 bg-gradient-to-br from-blue-50 to-purple-50 border-blue-200">
                    <div className="text-center">
                        <Bell className="h-16 w-16 text-blue-500 mx-auto mb-4" />
                        <h2 className="text-2xl font-semibold text-gray-900 mb-2">More Updates Coming Soon</h2>
                        <p className="text-gray-600 max-w-md mx-auto">
                            We're constantly improving SnapDraft. Check back here for the latest features and announcements.
                        </p>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
