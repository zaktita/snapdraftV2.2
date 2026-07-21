import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Calendar,
    CheckCircle,
    Clock,
    Download,
    Eye,
    FileText,
    XCircle,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Invoices', href: '/settings/invoices' },
];

interface Invoice {
    id: string;
    invoice_number: string;
    status: string;
    amount: number;
    currency: string;
    subtotal: number;
    tax: number;
    total: number;
    issued_date: string;
    due_date: string;
    paid_at: string | null;
    invoice_url?: string | null;
}

interface InvoicesProps {
    invoices: Invoice[];
}

export default function Invoices({ invoices }: InvoicesProps) {
    const getStatusBadge = (status: string) => {
        switch (status.toLowerCase()) {
            case 'paid':
                return (
                    <Badge className="border-green-500/20 bg-green-500/10 text-green-700">
                        <CheckCircle className="mr-1 h-3 w-3" />
                        Paid
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge className="border-yellow-500/20 bg-yellow-500/10 text-yellow-700">
                        <Clock className="mr-1 h-3 w-3" />
                        Pending
                    </Badge>
                );
            case 'failed':
            case 'cancelled':
                return (
                    <Badge className="border-red-500/20 bg-red-500/10 text-red-700">
                        <XCircle className="mr-1 h-3 w-3" />
                        {status.charAt(0).toUpperCase() + status.slice(1)}
                    </Badge>
                );
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const formatCurrency = (amount: number, currency: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <SettingsLayout wide>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Invoices"
                        description="View, download, and email your Lemon Squeezy receipts"
                    />

                    {invoices.length > 0 ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>All invoices</CardTitle>
                                <CardDescription>Your complete invoice history</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {invoices.map((invoice) => (
                                        <div
                                            key={invoice.id}
                                            className="flex flex-col gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="flex flex-1 items-center gap-4">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                                    <FileText className="h-5 w-5" />
                                                </div>

                                                <div className="flex-1">
                                                    <div className="mb-1 flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            Invoice #{invoice.invoice_number}
                                                        </span>
                                                        {getStatusBadge(invoice.status)}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                                        <div className="flex items-center gap-1">
                                                            <Calendar className="h-3 w-3" />
                                                            <span>
                                                                {format(new Date(invoice.issued_date), 'MMM dd, yyyy')}
                                                            </span>
                                                        </div>
                                                        {invoice.paid_at && (
                                                            <div className="flex items-center gap-1">
                                                                <CheckCircle className="h-3 w-3 text-green-600" />
                                                                <span>
                                                                    Paid {format(new Date(invoice.paid_at), 'MMM dd, yyyy')}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="text-right">
                                                    <div className="text-lg font-semibold">
                                                        {formatCurrency(invoice.total, invoice.currency)}
                                                    </div>
                                                    {invoice.tax > 0 && (
                                                        <div className="text-xs text-muted-foreground">
                                                            (incl. {formatCurrency(invoice.tax, invoice.currency)} tax)
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2 sm:ml-4">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/settings/invoices/${invoice.id}`}>
                                                        <Eye className="mr-1 h-4 w-4" />
                                                        View
                                                    </Link>
                                                </Button>
                                                <Button variant="outline" size="sm" asChild>
                                                    <a href={`/settings/invoices/${invoice.id}/download`} download>
                                                        <Download className="mr-1 h-4 w-4" />
                                                        PDF
                                                    </a>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardContent className="py-12 text-center">
                                <div className="mb-4 flex justify-center">
                                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                        <FileText className="h-8 w-8 text-muted-foreground" />
                                    </div>
                                </div>
                                <h3 className="mb-2 text-xl font-semibold">No invoices yet</h3>
                                <p className="mb-6 text-muted-foreground">
                                    Invoices appear here after a payment succeeds.
                                </p>
                                <Button asChild>
                                    <Link href="/settings/subscription">Manage subscription</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
