import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    FileText,
    Download,
    Eye,
    CheckCircle,
    Clock,
    XCircle,
    Calendar,
    DollarSign
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: '/billing/invoices',
    },
    {
        title: 'Invoices',
        href: '/billing/invoices',
    },
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
}

interface InvoicesProps {
    invoices: Invoice[];
}

export default function Invoices({ invoices }: InvoicesProps) {
    const getStatusBadge = (status: string) => {
        switch (status.toLowerCase()) {
            case 'paid':
                return (
                    <Badge className="bg-green-500/10 text-green-700 border-green-500/20">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Paid
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge className="bg-yellow-500/10 text-yellow-700 border-yellow-500/20">
                        <Clock className="h-3 w-3 mr-1" />
                        Pending
                    </Badge>
                );
            case 'failed':
            case 'cancelled':
                return (
                    <Badge className="bg-red-500/10 text-red-700 border-red-500/20">
                        <XCircle className="h-3 w-3 mr-1" />
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
            
            <div className="p-6 md:p-8 max-w-[1200px] mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--sd-or-pale)]">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h1 className="font-display text-3xl font-normal tracking-tight">Invoices</h1>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                View and download your billing invoices
                            </p>
                        </div>
                    </div>
                </div>

                {invoices.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>All Invoices</CardTitle>
                            <CardDescription>Your complete invoice history</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {invoices.map((invoice) => (
                                    <div 
                                        key={invoice.id}
                                        className="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50 transition-colors"
                                    >
                                        <div className="flex items-center gap-4 flex-1">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                                <FileText className="h-5 w-5" />
                                            </div>
                                            
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-medium">Invoice #{invoice.invoice_number}</span>
                                                    {getStatusBadge(invoice.status)}
                                                </div>
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    <div className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        <span>{format(new Date(invoice.issued_date), 'MMM dd, yyyy')}</span>
                                                    </div>
                                                    {invoice.paid_at && (
                                                        <div className="flex items-center gap-1">
                                                            <CheckCircle className="h-3 w-3 text-green-600" />
                                                            <span>Paid {format(new Date(invoice.paid_at), 'MMM dd, yyyy')}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <div className="font-semibold text-lg">
                                                    {formatCurrency(invoice.total, invoice.currency)}
                                                </div>
                                                {invoice.tax > 0 && (
                                                    <div className="text-xs text-muted-foreground">
                                                        (incl. {formatCurrency(invoice.tax, invoice.currency)} tax)
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 ml-4">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/billing/invoices/${invoice.id}`}>
                                                    <Eye className="h-4 w-4 mr-1" />
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={`/billing/invoices/${invoice.id}/download`} download>
                                                    <Download className="h-4 w-4 mr-1" />
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
                        <CardContent className="pt-12 pb-12 text-center">
                            <div className="flex justify-center mb-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                    <FileText className="h-8 w-8 text-muted-foreground" />
                                </div>
                            </div>
                            <h3 className="text-xl font-semibold mb-2">No Invoices Yet</h3>
                            <p className="text-muted-foreground mb-6">
                                You don't have any invoices. Invoices will appear here after you make a payment.
                            </p>
                            <Button asChild>
                                <Link href="/subscription/plans">
                                    View Subscription Plans
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
