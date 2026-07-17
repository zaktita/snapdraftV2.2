<?php

namespace App\Notifications;

use App\Models\BetaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBetaApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BetaApplication $application,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->application;

        return (new MailMessage)
            ->subject('[SnapDraft] New beta application - '.$a->email)
            ->greeting('New beta application')
            ->line('Email: '.$a->email)
            ->line('Role: '.$a->role)
            ->line('Monthly posts: '.$a->monthly_post_volume)
            ->line('Visual workflow:')
            ->line($a->visual_workflow)
            ->salutation('- SnapDraft');
    }
}
