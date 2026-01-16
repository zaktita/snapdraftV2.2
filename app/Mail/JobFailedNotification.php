<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

class JobFailedNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $jobType,
        public string $projectName,
        public int $projectId,
        public string $errorMessage,
        public int $attemptNumber,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Image Generation Failed - {$this->projectName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $projectUrl = Route::has('projects.show')
            ? route('projects.show', $this->projectId)
            : rtrim((string) config('app.url'), '/') . '/projects/' . $this->projectId;

        $supportUrl = Route::has('settings.support')
            ? route('settings.support')
            : (Route::has('dashboard')
                ? route('dashboard')
                : (string) config('app.url'));

        return new Content(
            markdown: 'emails.job-failed',
            with: [
                'jobType' => $this->jobType,
                'projectName' => $this->projectName,
                'projectId' => $this->projectId,
                'errorMessage' => $this->errorMessage,
                'attemptNumber' => $this->attemptNumber,
                'projectUrl' => $projectUrl,
                'supportUrl' => $supportUrl,
            ],
        );
    }
}
