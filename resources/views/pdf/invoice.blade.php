<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: #666; }
        .row { margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .totals { margin-top: 16px; width: 280px; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }
        .totals .label { color: #666; }
        .totals .amount { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Invoice #{{ $invoice->invoice_number }}</h1>
    <div class="muted row">Status: {{ ucfirst($invoice->status) }}</div>
    <div class="muted row">Issued: {{ optional($invoice->issued_at)->format('M d, Y') }}</div>
    @if($invoice->paid_at)
        <div class="muted row">Paid: {{ $invoice->paid_at->format('M d, Y') }}</div>
    @endif

    <div style="margin-top: 20px;">
        <strong>Bill to</strong><br>
        {{ $invoice->billing_name }}<br>
        {{ $invoice->billing_email }}
        @if($invoice->billing_address)
            <br>{{ $invoice->billing_address }}
        @endif
        @if($invoice->billing_city || $invoice->billing_country)
            <br>
            {{ collect([$invoice->billing_city, $invoice->billing_state, $invoice->billing_zip])->filter()->implode(', ') }}
            {{ $invoice->billing_country }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? 'Subscription' }}</td>
                    <td>{{ $item['quantity'] ?? 1 }}</td>
                    <td>{{ number_format((float) ($item['price'] ?? 0), 2) }} {{ strtoupper($invoice->currency) }}</td>
                    <td>{{ number_format((float) ($item['total'] ?? 0), 2) }} {{ strtoupper($invoice->currency) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Subscription payment</td>
                    <td>1</td>
                    <td>{{ number_format((float) $invoice->subtotal, 2) }} {{ strtoupper($invoice->currency) }}</td>
                    <td>{{ number_format((float) $invoice->total, 2) }} {{ strtoupper($invoice->currency) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="amount">{{ number_format((float) $invoice->subtotal, 2) }} {{ strtoupper($invoice->currency) }}</td>
        </tr>
        <tr>
            <td class="label">Tax</td>
            <td class="amount">{{ number_format((float) $invoice->tax_amount, 2) }} {{ strtoupper($invoice->currency) }}</td>
        </tr>
        <tr>
            <td class="label">Total</td>
            <td class="amount">{{ number_format((float) $invoice->total, 2) }} {{ strtoupper($invoice->currency) }}</td>
        </tr>
    </table>
</body>
</html>
