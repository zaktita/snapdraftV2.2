<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'invoice_number',
        'lemonsqueezy_invoice_id',
        'billing_name',
        'billing_email',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_zip',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'status',
        'items',
        'meta',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'items' => 'array',
        'meta' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the user that owns the invoice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transaction associated with this invoice.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if invoice is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if invoice is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Get formatted total with currency.
     */
    public function formattedTotal(): string
    {
        return number_format($this->total, 2) . ' ' . strtoupper($this->currency);
    }
}
