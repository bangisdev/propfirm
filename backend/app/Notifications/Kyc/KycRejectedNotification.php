<?php

namespace App\Notifications\Kyc;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Identity verification — additional information needed')
            ->greeting("Hi {$notifiable->name},")
            ->line('We were unable to verify your identity documents.')
            ->line("Reason: {$this->reason}")
            ->line('Please submit a new verification with clearer documents.')
            ->action('Resubmit', config('app.frontend_url').'/dashboard/settings');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'KYC verification needs attention',
            'body' => $this->reason,
            'action_url' => '/dashboard/settings',
        ];
    }
}
