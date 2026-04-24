<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously on admin approval so mail delivers without a queue worker.
 * (Queued mailables are easy to miss when QUEUE_CONNECTION=database and nothing runs `queue:work`.)
 */
class BetaInviteCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $registerUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SnapDraft beta invite',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.beta-invite-code',
        );
    }
}
