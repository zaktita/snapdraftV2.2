<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceReceiptMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * Display list of user's invoices.
     */
    public function index(): Response
    {
        $user = Auth::user();

        $invoices = Invoice::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? $invoice->id,
                    'status' => $invoice->status,
                    'amount' => $invoice->total,
                    'currency' => $invoice->currency ?? 'EUR',
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax_amount,
                    'total' => $invoice->total,
                    'issued_date' => $invoice->issued_at ?? $invoice->created_at,
                    'due_date' => $invoice->due_at ?? $invoice->created_at,
                    'paid_at' => $invoice->paid_at,
                    'invoice_url' => $invoice->meta['invoice_url'] ?? null,
                ];
            });

        return Inertia::render('billing/invoices', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display invoice details.
     */
    public function show($id): Response
    {
        $invoice = $this->findUserInvoice($id);

        return Inertia::render('billing/show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? $invoice->id,
                'status' => $invoice->status,
                'currency' => $invoice->currency ?? 'EUR',
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax_amount,
                'total' => $invoice->total,
                'issued_at' => $invoice->issued_at ?? $invoice->created_at,
                'due_at' => $invoice->due_at,
                'paid_at' => $invoice->paid_at,
                'billing_name' => $invoice->billing_name,
                'billing_email' => $invoice->billing_email,
                'billing_address' => $invoice->billing_address,
                'billing_city' => $invoice->billing_city,
                'billing_state' => $invoice->billing_state,
                'billing_postal_code' => $invoice->billing_zip,
                'billing_country' => $invoice->billing_country,
                'items' => $invoice->items ?? [],
                'invoice_url' => $invoice->meta['invoice_url'] ?? null,
                'transaction' => $invoice->transaction ? [
                    'id' => $invoice->transaction->id,
                    'amount' => $invoice->transaction->amount,
                    'status' => $invoice->transaction->status,
                    'payment_method' => $invoice->transaction->payment_method,
                ] : null,
            ],
        ]);
    }

    /**
     * Download invoice as PDF.
     */
    public function downloadPdf($id)
    {
        $invoice = $this->findUserInvoice($id);

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    /**
     * Email the Lemon Squeezy receipt/invoice link to the user.
     *
     * Lemon Squeezy does not expose a public "resend receipt email" API, so we
     * email the LS-hosted invoice/receipt URL (fetched from LS when missing).
     */
    public function resendReceipt($id)
    {
        $user = Auth::user();
        $invoice = $this->findUserInvoice($id);

        $invoiceUrl = $invoice->meta['invoice_url'] ?? null;

        if (! $invoiceUrl && $invoice->lemonsqueezy_invoice_id) {
            $invoiceUrl = $this->fetchLemonSqueezyInvoiceUrl($invoice->lemonsqueezy_invoice_id);

            if ($invoiceUrl) {
                $meta = $invoice->meta ?? [];
                $meta['invoice_url'] = $invoiceUrl;
                $invoice->update(['meta' => $meta]);
            }
        }

        if (! $invoiceUrl) {
            return back()->with('error', 'No Lemon Squeezy receipt URL is available for this invoice yet.');
        }

        try {
            Mail::to($user->email)->send(new InvoiceReceiptMail(
                userName: $user->name,
                invoiceNumber: $invoice->invoice_number,
                receiptUrl: $invoiceUrl,
                total: (string) $invoice->formattedTotal(),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to email invoice receipt', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to send receipt email. Please try again.');
        }

        return back()->with('success', 'Receipt link emailed to '.$user->email.'.');
    }

    protected function findUserInvoice($id): Invoice
    {
        $user = Auth::user();

        return Invoice::where(function ($query) use ($id) {
            $query->where('invoice_number', $id)
                ->orWhere('id', $id);
        })
            ->where('user_id', $user->id)
            ->with(['user', 'transaction'])
            ->firstOrFail();
    }

    protected function fetchLemonSqueezyInvoiceUrl(string $lemonInvoiceId): ?string
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);

            if (! config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->get('https://api.lemonsqueezy.com/v1/subscription-invoices/'.$lemonInvoiceId);

            if (! $response->successful()) {
                return null;
            }

            return $response->json('data.attributes.urls.invoice_url');
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Lemon Squeezy invoice URL', [
                'invoice_id' => $lemonInvoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
