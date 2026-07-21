import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { 
    FileText,
    Download,
    ArrowLeft,
    CheckCircle,
    Clock,
    XCircle,
    Calendar,
    CreditCard,
    MapPin,
    Mail,
    User,
    Send
} from 'lucide-react';
import { format } from 'date-fns';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Invoices', href: '/settings/invoices' },
    { title: 'Invoice details', href: '#' },
];

interface InvoiceItem {
    description: string;
    quantity: number;
    price: number;
    total: number;
}

interface Transaction {
    id: string;
    amount: number;
    status: string;
    payment_method: string;
}

interface Invoice {
    id: string;
    invoice_number: string;
    status: string;
    currency: string;
    subtotal: number;
    tax: number;
    total: number;
    issued_at: string;
    due_at: string | null;
    paid_at: string | null;
    billing_name: string | null;
    billing_email: string | null;
    billing_address: string | null;
    billing_city: string | null;
    billing_state: string | null;
    billing_postal_code: string | null;
    billing_country: string | null;
    items: InvoiceItem[];
    invoice_url?: string | null;
    transaction: Transaction | null;
}

interface InvoiceShowProps {
    invoice: Invoice;
}

export default function InvoiceShow({ invoice }: InvoiceShowProps) {
    const [resending, setResending] = useState(false);

    const handleResend = () => {
        setResending(true);
        router.post(`/settings/invoices/${invoice.id}/resend`, {}, {
            preserveScroll: true,
            onFinish: () => setResending(false),
        });
    };

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
            <Head title={`Invoice #${invoice.invoice_number}`} />

            <SettingsLayout wide>
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <Link href="/settings/invoices">
                            <Button variant="ghost" size="sm" className="gap-2">
                                <ArrowLeft className="h-4 w-4" />
                                Back to Invoices
                            </Button>
                        </Link>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                className="gap-2"
                                onClick={handleResend}
                                disabled={resending}
                            >
                                <Send className="h-4 w-4" />
                                {resending ? 'Sending…' : 'Email receipt'}
                            </Button>
                            {invoice.invoice_url && (
                                <Button variant="outline" className="gap-2" asChild>
                                    <a href={invoice.invoice_url} target="_blank" rel="noopener noreferrer">
                                        <FileText className="h-4 w-4" />
                                        Lemon Squeezy
                                    </a>
                                </Button>
                            )}
                            <Button variant="outline" className="gap-2" asChild>
                                <a href={`/settings/invoices/${invoice.id}/download`} download>
                                    <Download className="h-4 w-4" />
                                    Download PDF
                                </a>
                            </Button>
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--sd-or-pale)]">
                                <FileText className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <h1 className="font-display text-3xl font-normal tracking-tight">
                                    Invoice #{invoice.invoice_number}
                                </h1>
                                <p className="text-sm text-muted-foreground mt-0.5">
                                    Issued {format(new Date(invoice.issued_at), 'MMMM dd, yyyy')}
                                </p>
                            </div>
                        </div>
                        {getStatusBadge(invoice.status)}
                    </div>
                </div>

                <div className="grid gap-6">
                    {/* Invoice Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoice Details</CardTitle>
                            <CardDescription>Payment and billing information</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <div className="text-sm text-muted-foreground mb-1">Invoice Number</div>
                                    <div className="font-medium">{invoice.invoice_number}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground mb-1">Status</div>
                                    <div>{getStatusBadge(invoice.status)}</div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground mb-1">Issued Date</div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">
                                            {format(new Date(invoice.issued_at), 'MMM dd, yyyy')}
                                        </span>
                                    </div>
                                </div>
                                {invoice.paid_at && (
                                    <div>
                                        <div className="text-sm text-muted-foreground mb-1">Paid Date</div>
                                        <div className="flex items-center gap-2">
                                            <CheckCircle className="h-4 w-4 text-green-600" />
                                            <span className="font-medium">
                                                {format(new Date(invoice.paid_at), 'MMM dd, yyyy')}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {invoice.transaction && (
                                <>
                                    <Separator />
                                    <div>
                                        <div className="text-sm font-medium mb-3">Payment Information</div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <div className="text-sm text-muted-foreground mb-1">Payment Method</div>
                                                <div className="flex items-center gap-2">
                                                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                                                    <span className="font-medium capitalize">
                                                        {invoice.transaction.payment_method || 'N/A'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <div className="text-sm text-muted-foreground mb-1">Transaction ID</div>
                                                <div className="font-mono text-xs">{invoice.transaction.id}</div>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Billing Address */}
                    {(invoice.billing_name || invoice.billing_email || invoice.billing_address) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Billing Information</CardTitle>
                                <CardDescription>Customer details</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {invoice.billing_name && (
                                    <div className="flex items-start gap-3">
                                        <User className="h-4 w-4 text-muted-foreground mt-0.5" />
                                        <div>
                                            <div className="text-sm text-muted-foreground">Name</div>
                                            <div className="font-medium">{invoice.billing_name}</div>
                                        </div>
                                    </div>
                                )}
                                {invoice.billing_email && (
                                    <div className="flex items-start gap-3">
                                        <Mail className="h-4 w-4 text-muted-foreground mt-0.5" />
                                        <div>
                                            <div className="text-sm text-muted-foreground">Email</div>
                                            <div className="font-medium">{invoice.billing_email}</div>
                                        </div>
                                    </div>
                                )}
                                {invoice.billing_address && (
                                    <div className="flex items-start gap-3">
                                        <MapPin className="h-4 w-4 text-muted-foreground mt-0.5" />
                                        <div>
                                            <div className="text-sm text-muted-foreground">Address</div>
                                            <div className="font-medium">
                                                {invoice.billing_address}
                                                {invoice.billing_city && <>, {invoice.billing_city}</>}
                                                {invoice.billing_state && <>, {invoice.billing_state}</>}
                                                {invoice.billing_postal_code && <> {invoice.billing_postal_code}</>}
                                                {invoice.billing_country && <><br />{invoice.billing_country}</>}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Line Items */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Items</CardTitle>
                            <CardDescription>Invoice line items</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {invoice.items && invoice.items.length > 0 ? (
                                <div className="space-y-4">
                                    <div className="border rounded-lg overflow-hidden">
                                        <table className="w-full">
                                            <thead className="bg-muted/50">
                                                <tr>
                                                    <th className="text-left p-3 text-sm font-medium">Description</th>
                                                    <th className="text-right p-3 text-sm font-medium">Qty</th>
                                                    <th className="text-right p-3 text-sm font-medium">Price</th>
                                                    <th className="text-right p-3 text-sm font-medium">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {invoice.items.map((item, index) => (
                                                    <tr key={index} className="border-t">
                                                        <td className="p-3">{item.description}</td>
                                                        <td className="p-3 text-right">{item.quantity}</td>
                                                        <td className="p-3 text-right">
                                                            {formatCurrency(item.price, invoice.currency)}
                                                        </td>
                                                        <td className="p-3 text-right font-medium">
                                                            {formatCurrency(item.total, invoice.currency)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <Separator />

                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">Subtotal</span>
                                            <span className="font-medium">
                                                {formatCurrency(invoice.subtotal, invoice.currency)}
                                            </span>
                                        </div>
                                        {invoice.tax > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">Tax</span>
                                                <span className="font-medium">
                                                    {formatCurrency(invoice.tax, invoice.currency)}
                                                </span>
                                            </div>
                                        )}
                                        <Separator />
                                        <div className="flex justify-between text-lg font-bold">
                                            <span>Total</span>
                                            <span>{formatCurrency(invoice.total, invoice.currency)}</span>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-8 text-muted-foreground">
                                    <div className="font-medium mb-2">No line items</div>
                                    <div className="text-2xl font-bold mt-4">
                                        {formatCurrency(invoice.total, invoice.currency)}
                                    </div>
                                    <div className="text-sm mt-1">Total Amount</div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
            </SettingsLayout>
        </AppLayout>
    );
}
