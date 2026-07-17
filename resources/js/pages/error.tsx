import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, FileQuestion, Home, Lock, RefreshCw, ServerCrash } from 'lucide-react';

interface Props {
    status: number;
}

const statusConfig: Record<
    number,
    { icon: React.ElementType; title: string; description: string }
> = {
    403: {
        icon: Lock,
        title: 'Access Denied',
        description: "You don't have permission to view this page.",
    },
    404: {
        icon: FileQuestion,
        title: 'Page Not Found',
        description: "The page you're looking for doesn't exist or has been moved.",
    },
    419: {
        icon: RefreshCw,
        title: 'Session Expired',
        description: 'Your session has expired. Please refresh the page and try again.',
    },
    429: {
        icon: AlertTriangle,
        title: 'Too Many Requests',
        description: "You've made too many requests. Please wait a moment and try again.",
    },
    500: {
        icon: ServerCrash,
        title: 'Server Error',
        description: 'Something went wrong on our end. We have been notified and are working on it.',
    },
    503: {
        icon: ServerCrash,
        title: 'Service Unavailable',
        description: 'We are down for maintenance. Please check back shortly.',
    },
};

export default function Error({ status }: Props) {
    const config = statusConfig[status] ?? {
        icon: AlertTriangle,
        title: 'An Error Occurred',
        description: 'An unexpected error occurred. Please try again.',
    };

    const Icon = config.icon;

    return (
        <>
            <Head title={`${status} - ${config.title}`} />

            <div className="bg-background flex min-h-screen flex-col items-center justify-center px-4">
                <div className="flex w-full max-w-md flex-col items-center gap-6 text-center">
                    {/* Icon */}
                    <div className="bg-muted flex h-20 w-20 items-center justify-center rounded-full">
                        <Icon className="text-muted-foreground h-10 w-10" />
                    </div>

                    {/* Status code */}
                    <span className="text-muted-foreground text-sm font-medium tracking-widest uppercase">
                        Error {status}
                    </span>

                    {/* Heading */}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight">{config.title}</h1>
                        <p className="text-muted-foreground text-base">{config.description}</p>
                    </div>

                    {/* Actions */}
                    <div className="flex flex-col gap-3 sm:flex-row">
                        {status === 419 ? (
                            <Button onClick={() => window.location.reload()}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Refresh Page
                            </Button>
                        ) : (
                            <>
                                <Button asChild>
                                    <Link href="/">
                                        <Home className="mr-2 h-4 w-4" />
                                        Back to Home
                                    </Link>
                                </Button>
                                <Button variant="outline" onClick={() => window.history.back()}>
                                    Go Back
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
