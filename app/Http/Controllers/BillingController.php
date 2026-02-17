<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        
        // Get user's invoices
        $invoices = Invoice::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? $invoice->id,
                    'status' => $invoice->status,
                    'amount' => $invoice->total,
                    'currency' => $invoice->currency ?? 'EUR',
                    'subtotal' => $invoice->subtotal,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'issued_date' => $invoice->issued_at ?? $invoice->created_at,
                    'due_date' => $invoice->due_at ?? $invoice->created_at,
                    'paid_at' => $invoice->paid_at,
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
        $user = Auth::user();
        
        // Find invoice by invoice_number or id, ensuring it belongs to the user
        $invoice = Invoice::where(function($query) use ($id) {
            $query->where('invoice_number', $id)
                  ->orWhere('id', $id);
        })
        ->where('user_id', $user->id)
        ->with(['user', 'transaction'])
        ->firstOrFail();

        return Inertia::render('billing/show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? $invoice->id,
                'status' => $invoice->status,
                'currency' => $invoice->currency ?? 'EUR',
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'issued_at' => $invoice->issued_at ?? $invoice->created_at,
                'due_at' => $invoice->due_at,
                'paid_at' => $invoice->paid_at,
                'billing_name' => $invoice->billing_name,
                'billing_email' => $invoice->billing_email,
                'billing_address' => $invoice->billing_address,
                'billing_city' => $invoice->billing_city,
                'billing_state' => $invoice->billing_state,
                'billing_postal_code' => $invoice->billing_postal_code,
                'billing_country' => $invoice->billing_country,
                'items' => $invoice->items ?? [],
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
        $user = Auth::user();
        
        // Find invoice by invoice_number or id, ensuring it belongs to the user
        $invoice = Invoice::where(function($query) use ($id) {
            $query->where('invoice_number', $id)
                  ->orWhere('id', $id);
        })
        ->where('user_id', $user->id)
        ->with(['user', 'transaction'])
        ->firstOrFail();
        
        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        
        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }
}