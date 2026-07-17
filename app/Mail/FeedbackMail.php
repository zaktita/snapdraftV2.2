<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $userEmail,
        public int $rating,
        public string $category,
        public string $feedbackMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SnapDraft Beta Feedback] {$this->category} - Rating {$this->rating}/10 from {$this->userName}",
            replyTo: [$this->userEmail],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback',
        );
    }
}
