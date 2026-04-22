import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function FeedbackThankYou() {
    return (
        <AppLayout breadcrumbs={[{ title: 'Feedback Submitted', href: '/feedback/thank-you' }]}>
            <Head title="Thank You" />

            <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 p-8 text-center">
                <CheckCircle2 className="h-14 w-14 text-green-600" />

                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">Thank you for your feedback</h1>
                    <p className="text-sm text-muted-foreground">
                        We appreciate you taking the time to share your thoughts. We are going to take your feedback into account and keep improving Snapdraft.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Button asChild>
                        <Link href="/dashboard">Back to Dashboard</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/feedback">Send more feedback</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
