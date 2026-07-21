<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $invoiceNumber,
        public string $receiptUrl,
        public string $total,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your SnapDraft receipt #{$this->invoiceNumber}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-receipt',
        );
    }
}
